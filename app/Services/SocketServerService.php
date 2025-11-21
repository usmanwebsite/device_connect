<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class SocketServerService
{
    private $server;
    private $clients = [];
    private $deviceSockets = [];
    
    private $javaApiBaseUrl = "http://127.0.0.1:8080"; // Your Java API URL

    public function startServer($port = 18887)
    {
        $this->server = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        socket_set_option($this->server, SOL_SOCKET, SO_REUSEADDR, 1);
        socket_bind($this->server, '0.0.0.0', $port);
        socket_listen($this->server);
        socket_set_nonblock($this->server);

        echo "✅ RD008 Socket Server listening on 0.0.0.0:$port\n";

        while (true) {
            $read = [$this->server];
            foreach ($this->clients as $client) {
                $read[] = $client['socket'];
            }

            $write = $except = null;
            if (socket_select($read, $write, $except, 1) < 1) {
                $this->checkPendingCommands();
                continue;
            }

            // New device connection
            if (in_array($this->server, $read)) {
                $client = socket_accept($this->server);
                if (!$client) continue;

                socket_getpeername($client, $ip);
                echo "[" . date('H:i:s') . "] 📡 Device connected from: {$ip}\n";

                socket_set_nonblock($client);

                $key = $this->sockId($client);
                $this->clients[$key] = [
                    'socket' => $client,
                    'ip' => $ip,
                    'device_id' => null
                ];

                unset($read[array_search($this->server, $read)]);
            }

            // Handle existing sockets
            foreach ($read as $sock) {
                $data = @socket_read($sock, 2048, PHP_BINARY_READ);
                if ($data === false || $data === '') {
                    $key = $this->sockId($sock);
                    if (isset($this->clients[$key])) {
                        echo "[" . date('H:i:s') . "] ⚠️ Disconnected: " . $this->clients[$key]['ip'] . "\n";

                        if (!empty($this->clients[$key]['device_id'])) {
                            unset($this->deviceSockets[$this->clients[$key]['device_id']]);
                        }

                        unset($this->clients[$key]);
                    }
                    socket_close($sock);
                    continue;
                }

                $data = trim($data);
                $key = $this->sockId($sock);
                $this->handlePacket($sock, $data, $this->clients[$key]['ip']);
            }
        }
    }

    private function handlePacket($sock, $data, $ip)
    {
        // ✅ Handle Heartbeat
        if (stripos($data, 'POST /api/Device/DoorHeart') !== false) {
            $matches = [];
            if (preg_match('/DeviceID=([0-9A-F]+)/i', $data, $matches)) {
                $deviceId = $matches[1];

                $key = $this->sockId($sock);
                $this->clients[$key]['device_id'] = $deviceId;
                $this->deviceSockets[$deviceId] = $sock;

                echo "[" . date('H:i:s') . "] ❤️ Heartbeat from device $deviceId @ $ip\n";

                DB::table('device_connections')->updateOrInsert(
                    ['device_id' => $deviceId],
                    ['ip' => $ip, 'last_heartbeat' => now()]
                );

                $doorStatus = $this->extractValue($data, 'DoorStatus');
                $version = $this->extractValue($data, 'Version');
                echo "   DoorStatus: $doorStatus\n   Version: $version\n";
                return;
            }
        }
        
        // ✅ Handle OpenDoor Request with SCode validation
        if (stripos($data, 'POST /api/Device/OpenDoor') !== false) {
            $this->handleOpenDoorRequest($sock, $data, $ip);
            return;
        }

        // Other requests
        echo "[" . date('H:i:s') . "] 📦 Raw Data: $data\n";
        echo "[" . date('H:i:s') . "] 📦 Raww Data (HEX): " . strtoupper(bin2hex($data)) . "\n";
    }

    private function handleOpenDoorRequest($sock, $data, $ip)
{
    echo "[" . date('H:i:s') . "] 🚪 OpenDoor Request Received\n";

    // Extract SCode (card scanned) and DeviceID
    $scode = $this->extractValue($data, 'SCode'); // card_no
    $deviceId = $this->extractValue($data, 'DeviceID');

    echo "[" . date('H:i:s') . "] 🔍 SCode: $scode, DeviceID: $deviceId\n";

    if (empty($scode) || empty($deviceId)) {
        echo "[" . date('H:i:s') . "] ❌ Missing SCode or DeviceID\n";
        $this->sendAlarm($sock, $deviceId, $scode); // pass SCode to save
        return;
    }

    // ✅ Step 1: Call Java API to get real staff_no
    $javaResponse = $this->callJavaStaffValidityAPI($scode);

    if (!$javaResponse || !isset($javaResponse['status']) || $javaResponse['status'] !== true) {
        echo "[" . date('H:i:s') . "] ❌ Staff visit not valid\n";
        $this->sendAlarm($sock, $deviceId, $scode);
        return;
    }

    $staffNo = $javaResponse['staffNo'] ?? $scode; // real staff_no from Java or fallback
    $locationName = $javaResponse['locationName'] ?? '';

    if (empty($locationName)) {
        echo "[" . date('H:i:s') . "] ❌ No location in Java response\n";
        $this->sendAlarm($sock, $deviceId, $scode);
        return;
    }

    // ✅ Step 2: Validate location assignment
    $location = DB::table('vendor_locations')->where('name', $locationName)->first();
    if (!$location) {
        echo "[" . date('H:i:s') . "] ❌ Location not found\n";
        $this->sendAlarm($sock, $deviceId, $scode);
        return;
    }

    $assignment = DB::table('device_location_assigns')
        ->where('device_id', $deviceId)
        ->where('location_id', $location->id)
        ->first();

    if (!$assignment) {
        echo "[" . date('H:i:s') . "] ❌ Location not assigned to device\n";
        $this->sendAlarm($sock, $deviceId, $scode);
        return;
    }

    // ✅ Step 3: Open door & log success
    echo "[" . date('H:i:s') . "] ✅ Access granted\n";
    $this->sendOpenDoorCommand($sock, $deviceId);

    DB::table('device_access_logs')->insert([
        'device_id' => $deviceId,
        'card_no' => $scode,      // scanned code from device
        'staff_no' => $staffNo,   // actual staff number from Java
        'location_name' => $locationName,
        'access_granted' => true,
        'created_at' => now(),
    ]);
}

    // private function handleOpenDoorRequest($sock, $data, $ip)
    // {
    //     echo "[" . date('H:i:s') . "] 🚪 OpenDoor Request Received\n";
        
    //     // Extract SCode and DeviceID
    //     $scode = $this->extractValue($data, 'SCode');
    //     $deviceId = $this->extractValue($data, 'DeviceID');
        
    //     echo "[" . date('H:i:s') . "] 🔍 SCode: $scode, DeviceID: $deviceId\n";
        
    //     if (empty($scode) || empty($deviceId)) {
    //         echo "[" . date('H:i:s') . "] ❌ Missing SCode or DeviceID\n";
    //         $this->sendAlarm($sock, $deviceId);
    //         return;
    //     }
        
    //     // Step 1: Call Java API to check staff validity
    //     $javaResponse = $this->callJavaStaffValidityAPI($scode);
        
    //     if (!$javaResponse || !isset($javaResponse['status'])) {
    //         echo "[" . date('H:i:s') . "] ❌ Java API call failed or invalid response\n";
    //         $this->sendAlarm($sock, $deviceId);
    //         return;
    //     }
        
    //     echo "[" . date('H:i:s') . "] 📋 Java API Response: " . json_encode($javaResponse) . "\n";
        
    //     // Step 2: Check if status is true and get location name
    //     if ($javaResponse['status'] !== true) {
    //         echo "[" . date('H:i:s') . "] ❌ Staff visit not valid: " . ($javaResponse['message'] ?? 'Unknown error') . "\n";
    //         $this->sendAlarm($sock, $deviceId);
    //         return;
    //     }
        
    //     $locationName = $javaResponse['locationName'] ?? '';
    //     if (empty($locationName)) {
    //         echo "[" . date('H:i:s') . "] ❌ No location name in Java response\n";
    //         $this->sendAlarm($sock, $deviceId);
    //         return;
    //     }
        
    //     // Step 3: Get location ID from vendor_locations table
    //     $location = DB::table('vendor_locations')->where('name', $locationName)->first();
    //     if (!$location) {
    //         echo "[" . date('H:i:s') . "] ❌ Location '$locationName' not found in vendor_locations\n";
    //         $this->sendAlarm($sock, $deviceId);
    //         return;
    //     }
        
    //     $locationId = $location->id;
    //     echo "[" . date('H:i:s') . "] 📍 Location ID: $locationId\n";
        
    //     // Step 4: Check if location is assigned to this device
    //     $assignment = DB::table('device_location_assigns')
    //     ->where('device_id', $deviceId)
    //     ->where('location_id', $locationId)
    //     ->first();
        
    //     if (!$assignment) {
    //         echo "[" . date('H:i:s') . "] ❌ Location $locationName not assigned to device $deviceId\n";
    //         $this->sendAlarm($sock, $deviceId);
    //         return;
    //     }
        
    //     // Step 5: All conditions met - Open the door
    //     echo "[" . date('H:i:s') . "] ✅ All conditions met - Opening door for device $deviceId\n";
    //     $this->sendOpenDoorCommand($sock, $deviceId);
        
    //     // Log the successful access
    //     // DB::table('access_logs')->insert([
    //     //     'device_id' => $deviceId,
    //     //     'staff_no' => $scode,
    //     //     'location_name' => $locationName,
    //     //     'access_granted' => true,
    //     //     'created_at' => now()
    //     // ]);

    //     DB::table('device_access_logs')->insert([
    //         'device_id' => $deviceId,
    //         'staff_no' => $javaResponse['staffNo'] ?? $scode, // ✅ use real staff number from Java
    //         'location_name' => $locationName,
    //         'access_granted' => true,
    //         'created_at' => now(),
    //     ]);
    // }

    private function callJavaStaffValidityAPI($staffNo)
    {
        try {
            $url = $this->javaApiBaseUrl . "/api/vendorpass/checkStaffVisitValidity";
            
            echo "[" . date('H:i:s') . "] 🌐 Calling Java API: $url\n";
            echo "[" . date('H:i:s') . "] 📤 Sending POST data: " . json_encode(['username' => $staffNo]) . "\n";
            
            $response = Http::timeout(10)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json'
                ])
                ->post($url, ['username' => $staffNo]);
            
            if ($response->successful()) {
                $responseData = $response->json();
                echo "[" . date('H:i:s') . "] ✅ Java API Response Success\n";
                return $responseData;
            } else {
                echo "[" . date('H:i:s') . "] ❌ Java API HTTP Error: " . $response->status() . "\n";
                echo "[" . date('H:i:s') . "] ❌ Java API Error Body: " . $response->body() . "\n";
                return null;
            }
        } catch (\Exception $e) {
            echo "[" . date('H:i:s') . "] ❌ Java API Call Failed: " . $e->getMessage() . "\n";
            return null;
        }
    }

    private function sendAlarm($sock, $deviceId, $scode = null)
    {
        echo "[" . date('H:i:s') . "] 🚨 Triggering alarm for device $deviceId\n";
        
        $this->triggerDeviceAlarm($sock, $deviceId);

        DB::table('device_access_logs')->insert([
            'device_id' => $deviceId,
            'card_no' => $scode,       // store scanned code even if denied
            'staff_no' => null,        // invalid/no staff
            'access_granted' => false,
            'reason' => 'Access denied - validation failed',
            'created_at' => now(),
        ]);

    }

    private function sendOpenDoorCommand($sock, $deviceId)
    {
        // Use existing command sending mechanism
        $this->sendCommandToDeviceProtocol($sock, $deviceId, 'open_door');
    }

    // ✅ Existing methods (unchanged)
    private function sockId($sock)
    {
        return spl_object_id($sock);
    }

    private function extractValue($data, $key)
    {
        return preg_match("/{$key}=([^&\r\n]+)/", $data, $m) ? $m[1] : '';
    }

    private function checkPendingCommands()
    {
        $pending = DB::table('device_commands')->where('sent', false)->get();

        foreach ($pending as $cmd) {
            if (!isset($this->deviceSockets[$cmd->device_id])) {
                echo "[" . date('H:i:s') . "] ⚠️ Device {$cmd->device_id} not connected (command skipped)\n";
                continue;
            }

            $sock = $this->deviceSockets[$cmd->device_id];
            $sent = $this->sendCommandToDeviceProtocol($sock, $cmd->device_id, $cmd->command);

            if ($sent) {
                DB::table('device_commands')->where('id', $cmd->id)->update(['sent' => true]);
                echo "[" . date('H:i:s') . "] ✅ Command '{$cmd->command}' sent to {$cmd->device_id}\n";
            } else {
                echo "[" . date('H:i:s') . "] ❌ Failed to send command to {$cmd->device_id}\n";
            }
        }
    }

    public function sendCommandToDevice($deviceId, $command)
    {
        $device = DB::table('device_connections')->where('device_id', $deviceId)->first();
        if (!$device) {
            return ['success' => false, 'message' => "❌ Device $deviceId not connected"];
        }

        DB::table('device_commands')->insert([
            'device_id' => $deviceId,
            'command' => $command,
            'sent' => false,
            'created_at' => now(),
        ]);

        echo "[" . date('H:i:s') . "] 📬 Command queued for $deviceId: $command\n";
        return ['success' => true, 'message' => '✅ Command queued'];
    }

    private function sendCommandToDeviceProtocol($sock, $deviceId, $command)
    {
        $commandCodes = [
            'open_door' => [0x50, 0x00],
        ];

        if (!isset($commandCodes[$command])) {
            echo "[" . date('H:i:s') . "] ⚠️ Unknown command: $command\n";
            return false;
        }

        [$cmdHigh, $cmdLow] = $commandCodes[$command];
        $deviceBytes = str_split($deviceId, 2);
        $deviceHex = array_map(fn($b) => hexdec($b), $deviceBytes);

        $packet = [
            0x02, 0x00, 0x0C,
            ...$deviceHex,
            0x11, $cmdHigh, $cmdLow,
            0xFF, 0x03, 0xFF, 0x03
        ];

        $binary = pack('C*', ...$packet);
        $result = @socket_write($sock, $binary, strlen($binary));

        if ($result === false) {
            echo "[" . date('H:i:s') . "] ❌ Failed to send to $deviceId: " . socket_strerror(socket_last_error($sock)) . "\n";
            return false;
        }

        echo "[" . date('H:i:s') . "] 🚀 Sent raw command to $deviceId ($command)\n";
        return true;
    }

    private function triggerDeviceAlarm($sock, $deviceId)
    {
        // Device protocol: Send Alarm Command
        $alarmCommand = "ALARM;DeviceID=$deviceId;TRIGGER=1;#";

        echo "[" . date('H:i:s') . "] 🚨 Sending Alarm Command: $alarmCommand\n";

        socket_write($sock, $alarmCommand, strlen($alarmCommand));
    }

}


// namespace App\Services;

// use Illuminate\Support\Facades\Log;
// use Illuminate\Support\Facades\DB;

// class SocketServerService
// {
//     private $server;
//     private $clients = []; 
//     private $deviceSockets = []; 

//     public function startServer($port = 18887)
//     {
//         $this->server = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
//         socket_set_option($this->server, SOL_SOCKET, SO_REUSEADDR, 1);
//         socket_bind($this->server, '0.0.0.0', $port);
//         socket_listen($this->server);
//         socket_set_nonblock($this->server);

//         echo "✅ RD008 Socket Server listening on 0.0.0.0:$port\n";

//         while (true) {
//             $read = [$this->server];
//             foreach ($this->clients as $client) {
//                 $read[] = $client['socket'];
//             }

//             $write = $except = null;
//             if (socket_select($read, $write, $except, 1) < 1) {

//                 $this->checkPendingCommands();
//                 continue;
//             }

//             if (in_array($this->server, $read)) {
//                 $client = socket_accept($this->server);
//                 if (!$client) continue;

//                 socket_getpeername($client, $ip);
//                 echo "[" . date('H:i:s') . "] 📡 Device connected from: {$ip}\n";

//                 socket_set_nonblock($client);

//                 $key = $this->sockId($client);
//                 $this->clients[$key] = [
//                     'socket' => $client,
//                     'ip' => $ip,
//                     'device_id' => null
//                 ];

//                 unset($read[array_search($this->server, $read)]);
//             }

//             foreach ($read as $sock) {
//                 $data = @socket_read($sock, 2048, PHP_BINARY_READ);
//                 if ($data === false || $data === '') {
//                     $key = $this->sockId($sock);
//                     if (isset($this->clients[$key])) {
//                         echo "[" . date('H:i:s') . "] ⚠️ Disconnected: " . $this->clients[$key]['ip'] . "\n";

//                         if (!empty($this->clients[$key]['device_id'])) {
//                             unset($this->deviceSockets[$this->clients[$key]['device_id']]);
//                         }

//                         unset($this->clients[$key]);
//                     }
//                     socket_close($sock);
//                     continue;
//                 }

//                 $data = trim($data);
//                 $key = $this->sockId($sock);
//                 $this->handlePacket($sock, $data, $this->clients[$key]['ip']);
//             }
//         }
//     }

//     private function sockId($sock)
//     {
//         return spl_object_id($sock);
//     }

//     private function handlePacket($sock, $data, $ip)
//     {
//         if (stripos($data, 'POST /api/Device/DoorHeart') !== false) {
//             $matches = [];
//             if (preg_match('/DeviceID=([0-9A-F]+)/i', $data, $matches)) {
//                 $deviceId = $matches[1];

//                 $key = $this->sockId($sock);
//                 $this->clients[$key]['device_id'] = $deviceId;
//                 $this->deviceSockets[$deviceId] = $sock;

//                 echo "[" . date('H:i:s') . "] ❤️ Heartbeat from device $deviceId @ $ip\n";

//                 DB::table('device_connections')->updateOrInsert(
//                     ['device_id' => $deviceId],
//                     ['ip' => $ip, 'last_heartbeat' => now()]
//                 );

//                 $doorStatus = $this->extractValue($data, 'DoorStatus');
//                 $version = $this->extractValue($data, 'Version');
//                 echo "   DoorStatus: $doorStatus\n   Version: $version\n";
//                 return;
//             }
//         } else {
//             echo "[" . date('H:i:s') . "] 📦 Raw Data: $data\n";
//             echo "[" . date('H:i:s') . "] 📦 Raww Data (HEX): " . strtoupper(bin2hex($data)) . "\n";
//         }
//     }

//     private function extractValue($data, $key)
//     {
//         return preg_match("/{$key}=([^&\r\n]+)/", $data, $m) ? $m[1] : '';
//     }

//     private function checkPendingCommands()
//     {
//         $pending = DB::table('device_commands')->where('sent', false)->get();

//         foreach ($pending as $cmd) {
//             if (!isset($this->deviceSockets[$cmd->device_id])) {
//                 echo "[" . date('H:i:s') . "] ⚠️ Device {$cmd->device_id} not connected (command skipped)\n";
//                 continue;
//             }

//             $sock = $this->deviceSockets[$cmd->device_id];

//             $sent = $this->sendCommandToDeviceProtocol($sock, $cmd->device_id, $cmd->command);

//             if ($sent) {
//                 DB::table('device_commands')->where('id', $cmd->id)->update(['sent' => true]);
//                 echo "[" . date('H:i:s') . "] ✅ Command '{$cmd->command}' sent to {$cmd->device_id}\n";
//             } else {
//                 echo "[" . date('H:i:s') . "] ❌ Failed to send command to {$cmd->device_id}\n";
//             }
//         }
//     }

//     public function sendCommandToDevice($deviceId, $command)
//     {
//         $device = DB::table('device_connections')->where('device_id', $deviceId)->first();
//         if (!$device) {
//             return ['success' => false, 'message' => "❌ Device $deviceId not connected"];
//         }

//         DB::table('device_commands')->insert([
//             'device_id' => $deviceId,
//             'command' => $command,
//             'sent' => false,
//             'created_at' => now(),
//         ]);

//         echo "[" . date('H:i:s') . "] 📬 Command queued for $deviceId: $command\n";
//         return ['success' => true, 'message' => '✅ Command queued'];
//     }

//     private function sendCommandToDeviceProtocol($sock, $deviceId, $command)
//     {
//         $commandCodes = [
//             'open_door' => [0x50, 0x00], 
//         ];

//         if (!isset($commandCodes[$command])) {
//             echo "[" . date('H:i:s') . "] ⚠️ Unknown command: $command\n";
//             return false;
//         }

//         [$cmdHigh, $cmdLow] = $commandCodes[$command];

//         $deviceBytes = str_split($deviceId, 2);
//         $deviceHex = array_map(fn($b) => hexdec($b), $deviceBytes);

//         $packet = [
//             0x02, 0x00, 0x0C,
//             ...$deviceHex,
//             0x11, $cmdHigh, $cmdLow,
//             0xFF, 0x03, 0xFF, 0x03
//         ];

//         $binary = pack('C*', ...$packet);
//         $result = @socket_write($sock, $binary, strlen($binary));

//         if ($result === false) {
//             echo "[" . date('H:i:s') . "] ❌ Failed to send to $deviceId: " . socket_strerror(socket_last_error($sock)) . "\n";
//             return false;
//         }

//         echo "[" . date('H:i:s') . "] 🚀 Sent raw command to $deviceId ($command)\n";
//         return true;
//     }
// } 

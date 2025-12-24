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
    
    private $javaApiBaseUrl = "http://127.0.0.1:8080";

    public function startServer($port = 18887)
    {
        $this->server = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        socket_set_option($this->server, SOL_SOCKET, SO_REUSEADDR, 1);
        socket_bind($this->server, '0.0.0.0', $port);
        socket_listen($this->server);
        socket_set_nonblock($this->server);

        echo "✅ RD008 Socket Server listening on 0.0.0.0:$port\n";

        //   $this->runTestFlow('TESTUSER123', '008825038135');

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

        // ✅ Step 1: Call Java API to get visitor details
        $javaResponse = $this->callJavaVendorApi($scode);

        if (!$javaResponse || !isset($javaResponse['status']) || $javaResponse['status'] !== 'success') {
            echo "[" . date('H:i:s') . "] ❌ Java API returned error or no data\n";
            $this->sendAlarm($sock, $deviceId, $scode, 'Java API error or no data');
            return;
        }

        $visitorData = $javaResponse['data'];
        $staffNo = $visitorData['staffNo'] ?? $scode;
        $visitorTypeId = $visitorData['visitorTypeId'] ?? null;

        echo "[" . date('H:i:s') . "] 👤 Staff No: $staffNo, Visitor Type ID: $visitorTypeId\n";

        // ✅ Step 2: Get location from device assignment
        $locationInfo = $this->getDeviceLocationInfo($deviceId);
        
        if (!$locationInfo) {
            echo "[" . date('H:i:s') . "] ❌ Device location not found\n";
            $this->sendAlarm($sock, $deviceId, $scode, 'Device location not assigned');
            return;
        }

        $locationName = $locationInfo['location_name'];
        $isType = $locationInfo['is_type']; // check_in or check_out

        echo "[" . date('H:i:s') . "] 📍 Location: $locationName, Type: $isType\n";

        // ✅ Step 3: Check if location is Turnstile (skip sequence check)
        if (strtolower($locationName) === 'turnstile') {
            echo "[" . date('H:i:s') . "] ⏭️ Turnstile location - skipping sequence check\n";
            $this->grantAccess($sock, $deviceId, $scode, $staffNo, $locationName, $isType);
            return;
        }

        // ✅ Step 4: Get visitor type and path
        if (!$visitorTypeId) {
            echo "[" . date('H:i:s') . "] ❌ No visitor type ID found\n";
            $this->sendAlarm($sock, $deviceId, $scode, 'No visitor type ID', $staffNo, $locationName);
            return;
        }

        $visitorType = DB::table('visitor_types')->where('id', $visitorTypeId)->first();
        
        if (!$visitorType || !$visitorType->path_id) {
            echo "[" . date('H:i:s') . "] ❌ Visitor type not found or no path assigned\n";
            $this->sendAlarm($sock, $deviceId, $scode, 'No path assigned to visitor type', $staffNo, $locationName);
            return;
        }

        $path = DB::table('paths')->where('id', $visitorType->path_id)->first();
        
        if (!$path) {
            echo "[" . date('H:i:s') . "] ❌ Path not found\n";
            $this->sendAlarm($sock, $deviceId, $scode, 'Path not found', $staffNo, $locationName);
            return;
        }

        // ✅ Step 5: Check door sequence
        $doorSequence = explode(',', $path->doors);
        $doorSequence = array_map('trim', $doorSequence);
        
        echo "[" . date('H:i:s') . "] 🚪 Path sequence: " . implode(' → ', $doorSequence) . "\n";

        // Check if current door is in the sequence
        if (!in_array($locationName, $doorSequence)) {
            echo "[" . date('H:i:s') . "] ❌ Current door '$locationName' not in path sequence\n";
            $this->sendAlarm($sock, $deviceId, $scode, "Door '$locationName' not in path", $staffNo, $locationName);
            return;
        }

        // ✅ Step 6: Check sequence flow if flag is ON
        $isSequenceFlag = env('isSequenceFlag', 'Off');
        
        if (strtolower($isSequenceFlag) === 'on') {
            echo "[" . date('H:i:s') . "] 🔄 Sequence check is ENABLED\n";
            
            // Get user's previous door access in this path
            $previousLogs = DB::table('device_access_logs')
                ->where('staff_no', $staffNo)
                ->where('access_granted', 1)
                ->whereIn('location_name', $doorSequence)
                ->orderBy('created_at', 'desc')
                ->get();
            
            if ($previousLogs->count() > 0) {
                $lastAccessedDoor = $previousLogs->first()->location_name;
                echo "[" . date('H:i:s') . "] 📍 Last accessed door: $lastAccessedDoor\n";
                
                $currentIndex = array_search($locationName, $doorSequence);
                $lastIndex = array_search($lastAccessedDoor, $doorSequence);
                
                echo "[" . date('H:i:s') . "] 📊 Current index: $currentIndex, Last index: $lastIndex\n";
                
                // Check if current door is the next in sequence
                if ($currentIndex !== $lastIndex + 1) {
                    echo "[" . date('H:i:s') . "] ❌ Sequence violation! Expected: " . 
                        ($doorSequence[$lastIndex + 1] ?? 'END') . ", Got: $locationName\n";
                    
                    $this->sendAlarm(
                        $sock, 
                        $deviceId, 
                        $scode, 
                        "User valid but sequence not followed. Expected: " . 
                        ($doorSequence[$lastIndex + 1] ?? 'END') . ", Got: $locationName",
                        $staffNo,
                        $locationName
                    );
                    return;
                }
                } else {
                    // First door in sequence must match
                    $firstDoor = $doorSequence[0];
                    if ($locationName !== $firstDoor) {
                        echo "[" . date('H:i:s') . "] ❌ Must start with first door: $firstDoor\n";
                        $this->sendAlarm(
                            $sock, 
                            $deviceId, 
                            $scode, 
                            "Must start with first door: $firstDoor",
                            $staffNo,
                            $locationName
                        );
                        return;
                    }
                }
                } else {
                    echo "[" . date('H:i:s') . "] ⏭️ Sequence check is DISABLED\n";
                }

                // ✅ Step 7: Grant access
                $this->grantAccess($sock, $deviceId, $scode, $staffNo, $locationName, $isType);
            }

    private function getDeviceLocationInfo($deviceId)
    {
        try {
            // Get device connection
            $deviceConnection = DB::table('device_connections')
                ->where('device_id', $deviceId)
                ->first();
                
            if (!$deviceConnection) {
                return null;
            }

            // Get device location assignment
            $deviceLocationAssign = DB::table('device_location_assigns')
                ->where('device_id', $deviceConnection->id)
                ->first();
                
            if (!$deviceLocationAssign) {
                return null;
            }

            // Get location name
            $location = DB::table('vendor_locations')
                ->where('id', $deviceLocationAssign->location_id)
                ->first();
                
            if (!$location) {
                return null;
            }

            return [
                'location_name' => $location->name,
                'is_type' => $deviceLocationAssign->is_type,
                'device_connection_id' => $deviceConnection->id
            ];
            
        } catch (\Exception $e) {
            echo "[" . date('H:i:s') . "] ❌ Error getting device location: " . $e->getMessage() . "\n";
            return null;
        }
    }

    private function callJavaVendorApi($staffNo)
    {
        try {
            $javaBaseUrl = env('JAVA_BACKEND_URL', 'http://localhost:8080');
            $token = env('JAVA_API_TOKEN', ''); // You might need to get this from session or config
            
            $url = $javaBaseUrl . '/api/vendorpass/get-visitor-details?staffNo=' . urlencode($staffNo);
            echo "[" . date('H:i:s') . "] 🌐 Calling Java API: $url\n";
            
            $response = Http::withHeaders([
                'x-auth-token' => $token,
                'Accept' => 'application/json',
            ])->timeout(10)
              ->get($url);

            echo "[" . date('H:i:s') . "] 📡 Response Status: " . $response->status() . "\n";
            
            if ($response->successful()) {
                $data = $response->json();
                echo "[" . date('H:i:s') . "] ✅ Java API Response Success\n";
                return $data;
            } else {
                echo "[" . date('H:i:s') . "] ❌ Java API HTTP Error: " . $response->status() . "\n";
                echo "[" . date('H:i:s') . "] ❌ Error body: " . $response->body() . "\n";
                return null;
            }
        } catch (\Exception $e) {
            echo "[" . date('H:i:s') . "] ❌ Java API exception: " . $e->getMessage() . "\n";
            return null;
        }
    }

    private function grantAccess($sock, $deviceId, $scode, $staffNo, $locationName, $isType)
    {
        echo "[" . date('H:i:s') . "] ✅ Access granted for $staffNo at $locationName ($isType)\n";
        
        // Send open door command to device
        $this->sendOpenDoorCommand($sock, $deviceId);

        // Log successful access
        DB::table('device_access_logs')->insert([
            'device_id' => $deviceId,
            'card_no' => $scode,
            'staff_no' => $staffNo,
            'location_name' => $locationName,
            'access_granted' => 1,
            'reason' => 'Access granted',
            'created_at' => now(),
        ]);
    }

    private function sendAlarm($sock, $deviceId, $scode = null, $reason = null, $staffNo = null, $locationName = null)
    {
        echo "[" . date('H:i:s') . "] 🚨 Triggering alarm for device $deviceId - Reason: $reason\n";
        
        // Trigger device alarm
        $this->triggerDeviceAlarm($sock, $deviceId);

        // Log denied access
        DB::table('device_access_logs')->insert([
            'device_id' => $deviceId,
            'card_no' => $scode,
            'staff_no' => $staffNo,
            'location_name' => $locationName,
            'access_granted' => 0,
            'reason' => $reason,
            'created_at' => now(),
        ]);
    }

    // private function sendOpenDoorCommand($sock, $deviceId)
    // {
    //     // Use existing command sending mechanism
    //     $this->sendCommandToDeviceProtocol($sock, $deviceId, 'open_door');
    // }

    private function sendOpenDoorCommand($sock, $deviceId)
    {
        if (!$sock) {
            echo "[" . date('H:i:s') . "] ⚠️ Test mode detected, skipping open door command for $deviceId\n";
            return;
        }

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


    //     public function runTestFlow($staffNo, $deviceId)
    // {
    //     echo "[" . date('H:i:s') . "] 🧪 Running full test flow for StaffNo: $staffNo, DeviceID: $deviceId\n";

    //     $data = "SCode={$staffNo}&DeviceID={$deviceId}";
    //     $sock = null; // No real socket needed

    //     $this->handleOpenDoorRequest($sock, $data, '127.0.0.1');
    // }

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
        $alarmCommand = "ALARM;DeviceID=$deviceId;TRIGGER=1;#";

        echo "[" . date('H:i:s') . "] 🚨 Sending Alarm Command: $alarmCommand\n";

        if (!$sock) {
            echo "[" . date('H:i:s') . "] ⚠️ Test mode detected, alarm command skipped for $deviceId\n";
            return;
        }

        socket_write($sock, $alarmCommand, strlen($alarmCommand));
    }

}



















// namespace App\Services;

// use Illuminate\Support\Facades\Log;
// use Illuminate\Support\Facades\DB;
// use Illuminate\Support\Facades\Http;

// class SocketServerService
// {
//     private $server;
//     private $clients = [];
//     private $deviceSockets = [];
    
//     private $javaApiBaseUrl = "http://127.0.0.1:8080"; 

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
//         }
        
//         if (stripos($data, 'POST /api/Device/OpenDoor') !== false) {
//             $this->handleOpenDoorRequest($sock, $data, $ip);
//             return;
//         }

//         echo "[" . date('H:i:s') . "] 📦 Raw Data: $data\n";
//         echo "[" . date('H:i:s') . "] 📦 Raww Data (HEX): " . strtoupper(bin2hex($data)) . "\n";
//     }

//     private function handleOpenDoorRequest($sock, $data, $ip)
// {
//     echo "[" . date('H:i:s') . "] 🚪 OpenDoor Request Received\n";

//     $scode = $this->extractValue($data, 'SCode'); 
//     $deviceId = $this->extractValue($data, 'DeviceID');

//     echo "[" . date('H:i:s') . "] 🔍 SCode: $scode, DeviceID: $deviceId\n";

//     if (empty($scode) || empty($deviceId)) {
//         echo "[" . date('H:i:s') . "] ❌ Missing SCode or DeviceID\n";
//         $this->sendAlarm($sock, $deviceId, $scode); 
//         return;
//     }

//     $javaResponse = $this->callJavaStaffValidityAPI($scode);

//     if (!$javaResponse || !isset($javaResponse['status']) || $javaResponse['status'] !== true) {
//         echo "[" . date('H:i:s') . "] ❌ Staff visit not valid\n";
//         $this->sendAlarm($sock, $deviceId, $scode);
//         return;
//     }

//     $staffNo = $javaResponse['staffNo'] ?? $scode;
//     $locationName = $javaResponse['locationName'] ?? '';

//     if (empty($locationName)) {
//         echo "[" . date('H:i:s') . "] ❌ No location in Java response\n";
//         $this->sendAlarm($sock, $deviceId, $scode);
//         return;
//     }

//     $location = DB::table('vendor_locations')->where('name', $locationName)->first();
//     if (!$location) {
//         echo "[" . date('H:i:s') . "] ❌ Location not found\n";
//         $this->sendAlarm($sock, $deviceId, $scode);
//         return;
//     }

//     $assignment = DB::table('device_location_assigns')
//         ->where('device_id', $deviceId)
//         ->where('location_id', $location->id)
//         ->first();

//     if (!$assignment) {
//         echo "[" . date('H:i:s') . "] ❌ Location not assigned to device\n";
//         $this->sendAlarm($sock, $deviceId, $scode);
//         return;
//     }

//     echo "[" . date('H:i:s') . "] ✅ Access granted\n";
//     $this->sendOpenDoorCommand($sock, $deviceId);

//     DB::table('device_access_logs')->insert([
//         'device_id' => $deviceId,
//         'card_no' => $scode,      
//         'staff_no' => $staffNo,   
//         'location_name' => $locationName,
//         'access_granted' => true,
//         'created_at' => now(),
//     ]);
// }


//     private function callJavaStaffValidityAPI($staffNo)
//     {
//         try {
//             $url = $this->javaApiBaseUrl . "/api/vendorpass/checkStaffVisitValidity";
            
//             echo "[" . date('H:i:s') . "] 🌐 Calling Java API: $url\n";
//             echo "[" . date('H:i:s') . "] 📤 Sending POST data: " . json_encode(['username' => $staffNo]) . "\n";
            
//             $response = Http::timeout(10)
//                 ->withHeaders([
//                     'Content-Type' => 'application/json',
//                     'Accept' => 'application/json'
//                 ])
//                 ->post($url, ['username' => $staffNo]);
            
//             if ($response->successful()) {
//                 $responseData = $response->json();
//                 echo "[" . date('H:i:s') . "] ✅ Java API Response Success\n";
//                 return $responseData;
//             } else {
//                 echo "[" . date('H:i:s') . "] ❌ Java API HTTP Error: " . $response->status() . "\n";
//                 echo "[" . date('H:i:s') . "] ❌ Java API Error Body: " . $response->body() . "\n";
//                 return null;
//             }
//         } catch (\Exception $e) {
//             echo "[" . date('H:i:s') . "] ❌ Java API Call Failed: " . $e->getMessage() . "\n";
//             return null;
//         }
//     }

//     private function sendAlarm($sock, $deviceId, $scode = null)
//     {
//         echo "[" . date('H:i:s') . "] 🚨 Triggering alarm for device $deviceId\n";
        
//         $this->triggerDeviceAlarm($sock, $deviceId);

//         DB::table('device_access_logs')->insert([
//             'device_id' => $deviceId,
//             'card_no' => $scode,       
//             'staff_no' => null,       
//             'access_granted' => false,
//             'reason' => 'Access denied - validation failed',
//             'created_at' => now(),
//         ]);

//     }

//     private function sendOpenDoorCommand($sock, $deviceId)
//     {
//         $this->sendCommandToDeviceProtocol($sock, $deviceId, 'open_door');
//     }

//     private function sockId($sock)
//     {
//         return spl_object_id($sock);
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

//     private function triggerDeviceAlarm($sock, $deviceId)
//     {
//         $alarmCommand = "ALARM;DeviceID=$deviceId;TRIGGER=1;#";

//         echo "[" . date('H:i:s') . "] 🚨 Sending Alarm Command: $alarmCommand\n";

//         socket_write($sock, $alarmCommand, strlen($alarmCommand));
//     }

// }


<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class SocketServerService
{
    private $server;
    private $clients = []; // connected devices
    private $deviceSockets = []; // device_id → socket

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
                // 👇 check pending commands in DB every second
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

    // ✅ Unique socket ID
    private function sockId($sock)
    {
        return spl_object_id($sock);
    }

    private function handlePacket($sock, $data, $ip)
    {
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
        } else {
            echo "[" . date('H:i:s') . "] 📦 Raw Data: $data\n";
echo "[" . date('H:i:s') . "] 📦 Raww Data (HEX): " . strtoupper(bin2hex($data)) . "\n";

        }
    }

    private function extractValue($data, $key)
    {
        return preg_match("/{$key}=([^&\r\n]+)/", $data, $m) ? $m[1] : '';
    }

    // 🔁 Check for new unsent commands in DB
    private function checkPendingCommands()
    {
        $pending = DB::table('device_commands')->where('sent', false)->get();

        foreach ($pending as $cmd) {
            if (!isset($this->deviceSockets[$cmd->device_id])) {
                echo "[" . date('H:i:s') . "] ⚠️ Device {$cmd->device_id} not connected (command skipped)\n";
                continue;
            }

            $sock = $this->deviceSockets[$cmd->device_id];

            // ✅ Send using actual protocol
            $sent = $this->sendCommandToDeviceProtocol($sock, $cmd->device_id, $cmd->command);

            if ($sent) {
                DB::table('device_commands')->where('id', $cmd->id)->update(['sent' => true]);
                echo "[" . date('H:i:s') . "] ✅ Command '{$cmd->command}' sent to {$cmd->device_id}\n";
            } else {
                echo "[" . date('H:i:s') . "] ❌ Failed to send command to {$cmd->device_id}\n";
            }
        }
    }

    // 🧭 Called from controller (API)
    public function sendCommandToDevice($deviceId, $command)
    {
        $device = DB::table('device_connections')->where('device_id', $deviceId)->first();
        if (!$device) {
            return ['success' => false, 'message' => "❌ Device $deviceId not connected"];
        }

        // Insert into commands queue
        DB::table('device_commands')->insert([
            'device_id' => $deviceId,
            'command' => $command,
            'sent' => false,
            'created_at' => now(),
        ]);

        echo "[" . date('H:i:s') . "] 📬 Command queued for $deviceId: $command\n";
        return ['success' => true, 'message' => '✅ Command queued'];
    }

    // ⚙️ Actual binary command sending
    private function sendCommandToDeviceProtocol($sock, $deviceId, $command)
    {
        // ✅ Step 1. Define command code mapping
        $commandCodes = [
            'open_door' => [0x50, 0x00], // Open door = 0x5000
        ];

        if (!isset($commandCodes[$command])) {
            echo "[" . date('H:i:s') . "] ⚠️ Unknown command: $command\n";
            return false;
        }

        [$cmdHigh, $cmdLow] = $commandCodes[$command];

        // ✅ Step 2. Convert device ID (like 008825038133) to 6 bytes hex
        $deviceBytes = str_split($deviceId, 2);
        $deviceHex = array_map(fn($b) => hexdec($b), $deviceBytes);

        // ✅ Step 3. Build binary packet
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
}

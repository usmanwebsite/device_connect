<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class DeviceCommunicationService
{
    private static $socket = null;
    private string $deviceIp;
    private int $port;

    public function __construct(string $deviceIp = '192.168.100.15', int $port = 9998)
    {
        $this->deviceIp = $deviceIp;
        $this->port = $port;

        // create connection once if not already
        if (self::$socket === null) {
            $this->connectToDevice();
        }
    }

    private function connectToDevice(): void
    {
        self::$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if (self::$socket === false) {
            throw new \Exception("Socket creation failed: " . socket_strerror(socket_last_error()));
        }

        $connected = @socket_connect(self::$socket, $this->deviceIp, $this->port);
        if ($connected === false) {
            $error = socket_strerror(socket_last_error(self::$socket));
            self::$socket = null;
            throw new \Exception("Connection failed: " . $error);
        }

        Log::info("✅ Connected to device {$this->deviceIp}:{$this->port}");
    }

    public function sendDeviceCommand(string $command, array $data = []): array
    {
        try {
            if (self::$socket === null) {
                $this->connectToDevice();
            }

            $payload = strtoupper($command);

            socket_write(self::$socket, $payload, strlen($payload));
            $response = socket_read(self::$socket, 2048);

            return [
                'success' => true,
                'command' => $payload,
                'response' => trim($response),
            ];
        } catch (\Exception $e) {
            Log::error("Device command error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function closeConnection(): void
    {
        if (self::$socket !== null) {
            socket_close(self::$socket);
            self::$socket = null;
            Log::info("🔒 Device connection closed.");
        }
    }
}

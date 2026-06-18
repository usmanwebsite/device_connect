<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class JavaAPIService
{
    protected $baseUrl;
    protected $timeout;
    protected $retryAttempts;

    public function __construct()
    {
        $config = config('device.java_api');
        $this->baseUrl = rtrim($config['base_url'], '/'); 
        $this->timeout = $config['timeout'];
        $this->retryAttempts = $config['retry_attempts'];
    }

    /**
     * Get device status from Java backend
     */
    public function getDeviceStatus($deviceMac = null)
    {
        $endpoint = "{$this->baseUrl}/device/status";
        $params = $deviceMac ? ['deviceMac' => $deviceMac] : [];

        try {
            Log::info("Calling Java API → {$endpoint}", $params);

            $response = Http::timeout($this->timeout)
                ->retry($this->retryAttempts, 1000)
                ->get($endpoint, $params);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error("Java API error: {$response->status()} → " . $response->body());
            return ['success' => false, 'status_code' => $response->status()];
        } catch (\Exception $e) {
            Log::error("Java API connection failed: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function sendDeviceCommand(string $command, array $data = [], string $deviceIp = '192.168.100.15', int $port = 9998)
    {
        try {
            // create TCP socket
            $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
            if ($socket === false) {
                throw new \Exception("Socket creation failed: " . socket_strerror(socket_last_error()));
            }

            // connect
            $connected = @socket_connect($socket, $deviceIp, $port);
            if ($connected === false) {
                throw new \Exception("Connection failed: " . socket_strerror(socket_last_error($socket)));
            }

            // create payload (depends on your device command protocol)
            // For now let's just send command name in plain text
            $payload = strtoupper($command);

            // send
            socket_write($socket, $payload, strlen($payload));

            // read response
            $response = socket_read($socket, 2048);

            socket_close($socket);

            return [
                'success' => true,
                'sent' => $payload,
                'response' => trim($response)
            ];
        } catch (\Exception $e) {
            Log::error("Device command error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }


    /**
     * Validate QR Code
     */
    public function validateQRCode(string $qrData, string $readerId)
    {
        $endpoint = "{$this->baseUrl}/qr/validate";
        $payload = [
            'qr_data' => $qrData,
            'reader_id' => $readerId,
        ];

        try {
            Log::info("Validating QR with Java API → {$endpoint}", $payload);

            $response = Http::timeout($this->timeout)
                ->retry($this->retryAttempts, 1000)
                ->post($endpoint, $payload);

            return $response->json();
        } catch (\Exception $e) {
            Log::error("QR validation failed: " . $e->getMessage());
            return ['valid' => false, 'error' => $e->getMessage()];
        }
    }
}

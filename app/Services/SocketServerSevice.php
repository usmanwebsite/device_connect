<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SocketServerService
{
    protected $javaBaseUrl;

    public function __construct()
    {
        $this->javaBaseUrl = config('device.java_api.base_url');
    }

    /**
     * Send command to Java socket server
     */
    public function sendCommand(string $command, array $data = [])
    {
        try {
            $response = Http::timeout(30)
                ->post("{$this->javaBaseUrl}/api/socket/command", [
                    'command' => $command,
                    'data' => $data,
                ]);

            return $response->successful() ? $response->json() : null;

        } catch (\Exception $e) {
            Log::error('Socket server command failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Process QR code through Java socket server
     */
    public function processQRCode(string $qrData, string $readerId)
    {
        return $this->sendCommand('process_qr', [
            'qr_data' => $qrData,
            'reader_id' => $readerId,
        ]);
    }

    /**
     * Get connected devices status
     */
    public function getConnectedDevices()
    {
        return $this->sendCommand('get_devices');
    }
}
<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class JavaAPIService
{
    protected $baseUrl;
    protected $timeout;

    public function __construct()
    {
        $this->baseUrl = config('device.java_api.base_url');
        $this->timeout = config('device.java_api.timeout');
    }

    /**
     * Validate QR Code with Java backend
     */
    public function validateQRCode(string $qrData, string $readerId)
    {
        try {
            $response = Http::timeout($this->timeout)
                ->post("{$this->baseUrl}/api/qr/validate", [
                    'qr_data' => $qrData,
                    'reader_id' => $readerId,
                    'timestamp' => now()->toISOString(),
                ]);

            return $response->successful() ? $response->json() : null;
            
        } catch (\Exception $e) {
            Log::error('Java API QR validation failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Send command to device via Java backend
     */
    public function sendDeviceCommand(string $command, array $data = [])
    {
        try {
            $response = Http::timeout($this->timeout)
                ->post("{$this->baseUrl}/api/device/command", [
                    'command' => $command,
                    'data' => $data,
                    'timestamp' => now()->toISOString(),
                ]);

            return $response->successful() ? $response->json() : null;
            
        } catch (\Exception $e) {
            Log::error('Java API device command failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get device status from Java backend
     */
    public function getDeviceStatus(string $deviceMac = null)
    {
        try {
            $response = Http::timeout($this->timeout)
                ->post("{$this->baseUrl}/api/device/status", [
                    'device_mac' => $deviceMac,
                ]);

            return $response->successful() ? $response->json() : null;
            
        } catch (\Exception $e) {
            Log::error('Java API status check failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Upload cards to device via Java backend
     */
    public function uploadCardsToDevice(array $cardsData)
    {
        try {
            $response = Http::timeout($this->timeout)
                ->post("{$this->baseUrl}/api/device/upload-cards", [
                    'cards' => $cardsData,
                    'batch_size' => 16, // As per your Java code
                    'timestamp' => now()->toISOString(),
                ]);

            return $response->successful() ? $response->json() : null;
            
        } catch (\Exception $e) {
            Log::error('Java API card upload failed: ' . $e->getMessage());
            return null;
        }
    }
}
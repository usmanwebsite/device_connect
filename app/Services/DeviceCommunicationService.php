<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Events\DoorAccessEvent;

class DeviceCommunicationService
{
    protected $javaAPIService;

    public function __construct(JavaAPIService $javaAPIService)
    {
        $this->javaAPIService = $javaAPIService;
    }

    /**
     * Open door via Java backend
     */
    public function openDoor(string $readerId, string $passId = null)
    {
        Log::info("Opening door for reader: {$readerId}, pass: {$passId}");

        $result = $this->javaAPIService->sendDeviceCommand('open_door', [
            'reader_id' => $readerId,
            'pass_id' => $passId,
        ]);

        if ($result && $result['success']) {
            event(new DoorAccessEvent('open', $readerId, $passId, true));
            return true;
        }

        event(new \App\Events\DoorAccessEvent('open', $readerId, $passId, false));
        return false;
    }

    /**
     * Trigger alarm via Java backend
     */
    public function triggerAlarm(string $readerId)
    {
        Log::warning("Triggering alarm for reader: {$readerId}");

        $result = $this->javaAPIService->sendDeviceCommand('trigger_alarm', [
            'reader_id' => $readerId,
        ]);

        event(new DoorAccessEvent('alarm', $readerId, null, (bool)$result));
        return $result;
    }

    /**
     * Clear all cards from device
     */
    public function clearAllCards()
    {
        Log::info("Clearing all cards from device");

        $result = $this->javaAPIService->sendDeviceCommand('clear_cards');

        return $result && $result['success'];
    }

    /**
     * Get device status
     */
    public function getDeviceStatus(string $deviceMac = null)
    {
        return $this->javaAPIService->getDeviceStatus($deviceMac);
    }

    /**
     * Process QR code scan - Main entry point for QR validation
     */
    public function processQRCodeScan(string $qrData, string $readerId)
    {
        Log::info("Processing QR scan - Data: {$qrData}, Reader: {$readerId}");

        // Validate QR code through Java backend
        $validationResult = $this->javaAPIService->validateQRCode($qrData, $readerId);

        if ($validationResult && $validationResult['valid']) {
            // Open door
            $openResult = $this->openDoor($readerId, $validationResult['pass_id'] ?? null);
            
            event(new \App\Events\QRCodeScanned($qrData, $readerId, true, $validationResult));
            return [
                'success' => true,
                'access_granted' => $openResult,
                'message' => 'Access granted',
                'data' => $validationResult,
            ];
        }

        // Trigger alarm for invalid QR
        $this->triggerAlarm($readerId);
        
        event(new \App\Events\QRCodeScanned($qrData, $readerId, false, $validationResult));
        return [
            'success' => false,
            'access_granted' => false,
            'message' => 'Invalid QR code or access denied',
            'data' => $validationResult,
        ];
    }
}
<?php

namespace App\Http\Controllers;

use App\Http\Requests\DeviceCommandRequest;
use App\Services\DeviceCommunicationService;
use Illuminate\Http\JsonResponse;

class DeviceController extends Controller
{
    protected $deviceService;

    public function __construct(DeviceCommunicationService $deviceService)
    {
        $this->deviceService = $deviceService;
    }

    /**
     * Get device status
     */
    public function getStatus(): JsonResponse
    {
        $status = $this->deviceService->getDeviceStatus();
        
        return response()->json([
            'success' => true,
            'status' => $status,
        ]);
    }

    /**
     * Send command to device
     */
    public function sendCommand(DeviceCommandRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $result = $this->deviceService->sendDeviceCommand(
            $validated['command'],
            $validated['data'] ?? []
        );

        return response()->json([
            'success' => (bool)$result,
            'result' => $result,
        ]);
    }

    /**
     * Open door remotely
     */
    public function openDoor(): JsonResponse
    {
        $result = $this->deviceService->openDoor('remote', 'manual_override');

        return response()->json([
            'success' => $result,
            'message' => $result ? 'Door open command sent' : 'Failed to open door',
        ]);
    }

    /**
     * Clear all cards from device
     */
    public function clearCards(): JsonResponse
    {
        $result = $this->deviceService->clearAllCards();

        return response()->json([
            'success' => $result,
            'message' => $result ? 'Cards cleared successfully' : 'Failed to clear cards',
        ]);
    }
}
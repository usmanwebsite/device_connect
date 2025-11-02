<?php

namespace App\Http\Controllers;

use App\Http\Requests\QRValidationRequest;
use App\Services\DeviceCommunicationService;
use Illuminate\Http\JsonResponse;

class QRCodeController extends Controller
{
    protected $deviceService;

    public function __construct(DeviceCommunicationService $deviceService)
    {
        $this->deviceService = $deviceService;
    }

    /**
     * Validate QR code and control door access
     */
    public function validateQR(QRValidationRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $result = $this->deviceService->processQRCodeScan(
            $validated['qr_data'],
            $validated['reader_id']
        );

        return response()->json($result);
    }

    /**
     * Get QR code scan history
     */
    public function getScanHistory(): JsonResponse
    {
        // This would typically call Java backend for history data
        return response()->json([
            'message' => 'Scan history would be retrieved from Java backend',
        ]);
    }
}
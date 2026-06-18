<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Services\SocketServerService;

class DeviceController extends Controller
{
    public function sendCommand(Request $request): JsonResponse
    {
        $deviceId = $request->get('deviceId');
        $command = $request->get('command', 'open_door');

        $server = app(\App\Services\SocketServerService::class);
        $result = $server->sendCommandToDevice($deviceId, $command);

        return response()->json($result);
    }

    public function openDoor(): JsonResponse
    {
        $result = SocketServerService::sendToDevice('OPEN_DOOR');
        return response()->json($result);
    }

    public function closeDoor(): JsonResponse
    {
        $result = SocketServerService::sendToDevice('CLOSE_DOOR');
        return response()->json($result);
    }

    public function disconnect(): JsonResponse
    {
        SocketServerService::disconnect();
        return response()->json(['success' => true, 'message' => 'Socket disconnected']);
    }
}
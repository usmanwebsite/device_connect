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
    private $encryptionService;
    
    private $javaApiBaseUrl = "http://127.0.0.1:8080";

    public function __construct()
    {
        $this->encryptionService = app(EncryptionService::class);
    }

    public function startServer($port = 18887)
    {
        $this->server = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        socket_set_option($this->server, SOL_SOCKET, SO_REUSEADDR, 1);
        socket_bind($this->server, '0.0.0.0', $port);
        socket_listen($this->server);
        socket_set_nonblock($this->server);

        echo "✅ RD008 Socket Server listening on 0.0.0.0:$port\n";

        //   $this->runTestFlow('lK98+suwMhrtbbebbrU85Q==:cp2WJfAUpfR9q6qA:wm/S7Q==:6YcYBuylypwFanhDe9rxig==', '123456789');

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
    $encryptedCardId = $this->extractValue($data, 'SCode');
    $deviceId = $this->extractValue($data, 'DeviceID');

    echo "[" . date('H:i:s') . "] 🔍 Encrypted Card ID: $encryptedCardId, DeviceID: $deviceId\n";

    if (empty($encryptedCardId) || empty($deviceId)) {
        echo "[" . date('H:i:s') . "] ❌ Missing SCode or DeviceID\n";
        $this->sendAlarm($sock, $deviceId, $encryptedCardId);
        return;
    }

    // ✅ STEP 1: DECRYPT Card ID
    $decryptedCardId = $this->encryptionService->decrypt($encryptedCardId);

    if (!$decryptedCardId) {
        echo "[" . date('H:i:s') . "] ❌ Failed to decrypt Card ID\n";
        $this->sendAlarm($sock, $deviceId, $encryptedCardId, 'Card ID decryption failed');
        return;
    }

    echo "[" . date('H:i:s') . "] 🔓 Decrypted Card ID: $decryptedCardId\n";

    // ✅ STEP 2: Call Java API with DECRYPTED Card ID
    $javaResponse = $this->callJavaVendorApi($decryptedCardId);

    if (!$javaResponse || !isset($javaResponse['status']) || $javaResponse['status'] !== 'success') {
        echo "[" . date('H:i:s') . "] ❌ Java API returned error or no data\n";
        $this->sendAlarm($sock, $deviceId, $decryptedCardId, 'Java API error or no data');
        return;
    }

    $apiData = $javaResponse['data'];
    
    // Extract IC No from API response
    $icNo = $apiData['icNo'] ?? null;
    
    if (!$icNo) {
        echo "[" . date('H:i:s') . "] ❌ IC No not found in API response\n";
        $this->sendAlarm($sock, $deviceId, $decryptedCardId, 'IC No not found');
        return;
    }
    
    $staffNo = $apiData['staffNo'] ?? null;
    $visitorData = $apiData['visitorData'] ?? [];
    $visitorTypeId = $visitorData['visitorTypeId'] ?? null;
    
    echo "[" . date('H:i:s') . "] 👤 IC No: $icNo, Staff No: $staffNo, Visitor Type ID: $visitorTypeId\n";

    // ✅ Step 3: Get location from device assignment
    $locationInfo = $this->getDeviceLocationInfo($deviceId);
    
    if (!$locationInfo) {
        echo "[" . date('H:i:s') . "] ❌ Device location not found\n";
        $this->sendAlarm($sock, $deviceId, $decryptedCardId, 'Device location not assigned', $icNo);
        return;
    }

    $locationName = $locationInfo['location_name'];
    $isType = $locationInfo['is_type']; // check_in or check_out

    echo "[" . date('H:i:s') . "] 📍 Location: $locationName, Type: $isType\n";

    // ✅ IMPORTANT: Turnstile check-in verification (SIMPLIFIED)
    if (strtolower($locationName) === 'turnstile') {
        if ($isType === 'check_out') {
            echo "[" . date('H:i:s') . "] 🚪 Processing Turnstile Check-Out\n";
            // Allow check-out without requiring check-in first
        } else {
            echo "[" . date('H:i:s') . "] 🚪 Processing Turnstile Check-In\n";
            // Log Turnstile check-in
        }
    } else {
        // For non-Turnstile locations, check if user has checked in at Turnstile first
        echo "[" . date('H:i:s') . "] 🔍 Checking if user checked in at Turnstile first\n";
        
        // ✅ SIMPLIFIED CHECK: Only check if user has any successful access at Turnstile today
        $hasCheckedIn = DB::table('device_access_logs')
            ->where('staff_no', $icNo)
            ->where('location_name', 'Turnstile')
            ->where('access_granted', 1)
            ->whereDate('created_at', now()->toDateString())
            ->exists();
        
        echo "[" . date('H:i:s') . "] 📊 Simple check query executed\n";
        
        // Debug: Show all Turnstile logs for this user today
        $turnstileLogs = DB::table('device_access_logs')
            ->where('staff_no', $icNo)
            ->where('location_name', 'Turnstile')
            ->whereDate('created_at', now()->toDateString())
            ->orderBy('created_at', 'desc')
            ->get();
        
        echo "[" . date('H:i:s') . "] 📋 Turnstile logs for user $icNo:\n";
        foreach ($turnstileLogs as $log) {
            echo "  - " . $log->location_name . " at " . $log->created_at . 
                 " (access: " . $log->access_granted . ", staff_no: " . $log->staff_no . ")\n";
        }
        
        if (!$hasCheckedIn) {
            echo "[" . date('H:i:s') . "] ❌ User has not checked in at Turnstile yet\n";
            $this->sendAlarm(
                $sock,
                $deviceId,
                $decryptedCardId,
                "Must check in at Turnstile first before accessing $locationName",
                $icNo,
                $locationName
            );
            return;
        }
        
        echo "[" . date('H:i:s') . "] ✅ User has checked in at Turnstile (found " . $turnstileLogs->count() . " logs)\n";
    }

    // ✅ Step 4: Check visitor type and path
    if (!$visitorTypeId) {
        echo "[" . date('H:i:s') . "] ❌ No visitor type ID found\n";
        $this->sendAlarm($sock, $deviceId, $decryptedCardId, 'No visitor type ID', $icNo, $locationName);
        return;
    }

    $visitorType = DB::table('visitor_types')->where('id', $visitorTypeId)->first();
    
    if (!$visitorType || !$visitorType->path_id) {
        echo "[" . date('H:i:s') . "] ❌ Visitor type not found or no path assigned\n";
        $this->sendAlarm($sock, $deviceId, $decryptedCardId, 'No path assigned to visitor type', $icNo, $locationName);
        return;
    }

    $path = DB::table('paths')->where('id', $visitorType->path_id)->first();
    
    if (!$path) {
        echo "[" . date('H:i:s') . "] ❌ Path not found\n";
        $this->sendAlarm($sock, $deviceId, $decryptedCardId, 'Path not found', $icNo, $locationName);
        return;
    }

    // ✅ Step 5: Get door sequence
    $doorSequence = explode(',', $path->doors);
    $doorSequence = array_map('trim', $doorSequence);
    
    echo "[" . date('H:i:s') . "] 🚪 Path sequence: " . implode(' → ', $doorSequence) . "\n";

    // Check if current door is in the sequence
    if (!in_array($locationName, $doorSequence)) {
        echo "[" . date('H:i:s') . "] ❌ Current door '$locationName' not in path sequence\n";
        $this->sendAlarm($sock, $deviceId, $decryptedCardId, "Door '$locationName' not in path", $icNo, $locationName);
        return;
    }

    // ✅ Step 6: Sequence check
    $isSequenceFlag = env('isSequenceFlag', 'Off');
    
if (strtolower($isSequenceFlag) === 'on') {
    echo "[" . date('H:i:s') . "] 🔄 Sequence check is ENABLED\n";

    $previousLogs = DB::table('device_access_logs')
        ->where('staff_no', $icNo)
        ->where('access_granted', 1)
        ->whereIn('location_name', $doorSequence)
        ->orderBy('created_at', 'desc')
        ->get();

    if ($previousLogs->count() > 0) {
        $lastAccessedDoor = $previousLogs->first()->location_name;
        $lastAccessType = $previousLogs->first()->reason ?? '';
        
        echo "[" . date('H:i:s') . "] 📍 Last accessed door: $lastAccessedDoor\n";
        echo "[" . date('H:i:s') . "] 📝 Last access reason: $lastAccessType\n";

        // Case-insensitive array_search
        $currentIndex = $this->caseInsensitiveArraySearch($locationName, $doorSequence);
        $lastIndex = $this->caseInsensitiveArraySearch($lastAccessedDoor, $doorSequence);

        echo "[" . date('H:i:s') . "] 📊 Current index: $currentIndex, Last index: $lastIndex\n";

        if ($currentIndex === false) {
            echo "[" . date('H:i:s') . "] ❌ Current location not found in sequence\n";
            $this->sendAlarm($sock, $deviceId, $decryptedCardId, 
                "Location not in sequence", $icNo, $locationName);
            return;
        }

        // ✅ IMPORTANT: Allow special cases for circular path
        $firstDoor = $doorSequence[0];
        $lastDoor = $doorSequence[count($doorSequence) - 1];
        
        // DEBUG: Check if this is a new visit session
        $todayCheckIns = DB::table('device_access_logs')
            ->where('staff_no', $icNo)
            ->where('location_name', 'Turnstile')
            ->where('access_granted', 1)
            ->whereDate('created_at', now()->toDateString())
            ->count();
        
        $todayCheckOuts = DB::table('device_access_logs')
            ->where('staff_no', $icNo)
            ->where('location_name', 'Turnstile')
            ->where('access_granted', 1)
            ->whereDate('created_at', now()->toDateString())
            ->where(function($query) {
                $query->where('reason', 'like', '%check-out%')
                      ->orWhere('reason', 'like', '%Check-Out%');
            })
            ->count();
        
        echo "[" . date('H:i:s') . "] 📊 Today's stats - Check-ins: $todayCheckIns, Check-outs: $todayCheckOuts\n";
        
        // Case 1: Starting new session (first check-in of the day)
        if ($todayCheckIns === 0 && $todayCheckOuts === 0 && 
            strtolower($locationName) === strtolower($firstDoor) && 
            $isType === 'check_in') {
            echo "[" . date('H:i:s') . "] ✅ First check-in of the day - starting new session\n";
        }
        // Case 2: User completed previous session and wants to start new one
        elseif ($todayCheckIns > 0 && $todayCheckOuts > 0 && 
                $todayCheckIns === $todayCheckOuts &&
                strtolower($locationName) === strtolower($firstDoor) && 
                $isType === 'check_in') {
            echo "[" . date('H:i:s') . "] 🔄 Previous session completed, starting new session\n";
        }
        // Case 3: User checking out from Turnstile (completing sequence)
        elseif (strtolower($locationName) === strtolower($lastDoor) && 
                $isType === 'check_out' &&
                $lastIndex === count($doorSequence) - 2) { // Last index should be second last door
            echo "[" . date('H:i:s') . "] ✅ User completed all rooms, allowing check-out from Turnstile\n";
        }
        // Case 4: Normal sequence progression
        elseif ($currentIndex === $lastIndex + 1) {
            echo "[" . date('H:i:s') . "] ✅ Normal sequence progression\n";
        }
elseif ($currentIndex === $lastIndex) {
    // Check if re-entry is allowed for this door
    if (strtolower($locationName) === 'turnstile' && $isType === 'check_in') {
        // Allow Turnstile re-entry only if previous was check-out
        $lastTurnstileLog = DB::table('device_access_logs')
            ->where('staff_no', $icNo)
            ->where('location_name', 'Turnstile')
            ->where('access_granted', 1)
            ->whereDate('created_at', now()->toDateString())
            ->orderBy('created_at', 'desc')
            ->first();
        
        // ✅ FIXED: Check multiple conditions for check-out
        if ($lastTurnstileLog) {
            $lastReason = strtolower($lastTurnstileLog->reason ?? '');
            $lastIsType = $this->getIsTypeFromDeviceAssignment($lastTurnstileLog->device_id);
            
            $isPreviousCheckOut = (
                strpos($lastReason, 'check-out') !== false || 
                strpos($lastReason, 'check out') !== false ||
                $lastIsType === 'check_out'
            );
            
            if ($isPreviousCheckOut) {
                echo "[" . date('H:i:s') . "] 🔄 Previous was check-out, allowing new check-in\n";
            } else {
                echo "[" . date('H:i:s') . "] ❌ Cannot check-in at Turnstile again. Previous was not check-out.\n";
                echo "[" . date('H:i:s') . "] ❌ Last reason: {$lastTurnstileLog->reason}, IsType: $lastIsType\n";
                $this->sendAlarm(
                    $sock,
                    $deviceId,
                    $decryptedCardId,
                    "Cannot check-in again. Complete the sequence first.",
                    $icNo,
                    $locationName
                );
                return;
            }
        } else {
            echo "[" . date('H:i:s') . "] ❌ No previous Turnstile log found\n";
            $this->sendAlarm(
                $sock,
                $deviceId,
                $decryptedCardId,
                "No previous session found",
                $icNo,
                $locationName
            );
            return;
        }
    } else {
        echo "[" . date('H:i:s') . "] ❌ Cannot re-enter same door without completing sequence\n";
        $this->sendAlarm(
            $sock,
            $deviceId,
            $decryptedCardId,
            "Cannot re-enter same location",
            $icNo,
            $locationName
        );
        return;
    }
}
        // Case 6: Sequence violation
        else {
            echo "[" . date('H:i:s') . "] ❌ Sequence violation! Expected: " .
                ($doorSequence[$lastIndex + 1] ?? 'END') . ", Got: $locationName\n";

            $this->sendAlarm(
                $sock,
                $deviceId,
                $decryptedCardId,
                "User valid but sequence not followed. Expected: " .
                ($doorSequence[$lastIndex + 1] ?? 'END') . ", Got: $locationName",
                $icNo,
                $locationName
            );
            return;
        }
    } else {
        // First door must be first in sequence - CASE INSENSITIVE COMPARISON
        $firstDoor = $doorSequence[0];
        
        // Case-insensitive comparison
        if (strtolower($locationName) !== strtolower($firstDoor)) {
            echo "[" . date('H:i:s') . "] ❌ Must start with first door: $firstDoor (case-insensitive check)\n";
            $this->sendAlarm(
                $sock,
                $deviceId,
                $decryptedCardId,
                "Must start with first door: $firstDoor",
                $icNo,
                $locationName
            );
            return;
        }
        
        echo "[" . date('H:i:s') . "] ✅ Starting sequence from first door: $firstDoor\n";
        
        // ✅ ALSO CHECK: If this is check_in Turnstile, allow access
        if (strtolower($locationName) === 'turnstile' && $isType === 'check_in') {
            echo "[" . date('H:i:s') . "] ✅ Check-in Turnstile - allowing access\n";
        }
    }
} else {
    echo "[" . date('H:i:s') . "] ⏭️ Sequence check is DISABLED\n";
}

    // ✅ Step 7: Grant access
    $this->grantAccess($sock, $deviceId, $decryptedCardId, $icNo, $locationName, $isType, [
        'staffNo' => $staffNo,
        'fullName' => $visitorData['fullName'] ?? null,
        'companyName' => $visitorData['companyName'] ?? null
    ]);
}
private function getIsTypeFromDeviceAssignment($deviceId)
{
    $deviceConnection = DB::table('device_connections')
        ->where('device_id', $deviceId)
        ->first();
    
    if (!$deviceConnection) return null;
    
    $assignment = DB::table('device_location_assigns')
        ->where('device_id', $deviceConnection->id)
        ->first();
    
    return $assignment->is_type ?? null;
}

    private function caseInsensitiveArraySearch($needle, $haystack)
    {
        $needle = strtolower($needle);
        foreach ($haystack as $key => $value) {
            if (strtolower($value) === $needle) {
                return $key;
            }
        }
        return false;
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

    private function callJavaVendorApi($cardId)
    {
        try {
            $javaBaseUrl = env('JAVA_BACKEND_URL', 'http://127.0.0.1:8080');
            $token = env('JAVA_API_TOKEN', '');

            echo "[" . date('H:i:s') . "] 🔹 STEP 1: Getting vendor by Card ID: $cardId\n";

            // 🔹 STEP 1: Get vendor by Card ID
            $vendorResponse = Http::withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])->timeout(10)->post(
                $javaBaseUrl . '/api/vendorpass/getVendorByCardId',
                ['cardId' => $cardId]
            );

            echo "[" . date('H:i:s') . "] 📡 Vendor API Status: " . $vendorResponse->status() . "\n";

            if (!$vendorResponse->successful()) {
                echo "[" . date('H:i:s') . "] ❌ getVendorByCardId failed with status: " . $vendorResponse->status() . "\n";
                echo "[" . date('H:i:s') . "] ❌ Error: " . $vendorResponse->body() . "\n";
                return null;
            }

            $vendorData = $vendorResponse->json();
            echo "[" . date('H:i:s') . "] 📋 Vendor API Response: " . json_encode($vendorData) . "\n";

            if (empty($vendorData['icNo'])) {
                echo "[" . date('H:i:s') . "] ❌ IC No not found for card\n";
                return null;
            }

            $icNo = $vendorData['icNo'];
            $vendorStaffNo = $vendorData['staffNo'] ?? null;
            $status = $vendorData['status'] ?? false;
            
            if ($status !== true) {
                echo "[" . date('H:i:s') . "] ❌ Card status is not active\n";
                return null;
            }

            echo "[" . date('H:i:s') . "] 🆔 IC No Found: $icNo, Staff No from vendor: $vendorStaffNo\n";

            // 🔹 STEP 2: Get visitor details by IC No
            echo "[" . date('H:i:s') . "] 🔹 STEP 2: Getting visitor details by IC No: $icNo\n";

            $visitorResponse = Http::withHeaders([
                'Accept' => 'application/json',
            ])->timeout(10)->get(
                $javaBaseUrl . '/api/vendorpass/get-visitor-details-by-icno-or-staffno',
                ['icNo' => $icNo]
            );

            echo "[" . date('H:i:s') . "] 📡 Visitor API Status: " . $visitorResponse->status() . "\n";

            if (!$visitorResponse->successful()) {
                echo "[" . date('H:i:s') . "] ❌ Visitor details API failed with status: " . $visitorResponse->status() . "\n";
                echo "[" . date('H:i:s') . "] ❌ Error: " . $visitorResponse->body() . "\n";
                return null;
            }

            $visitorJson = $visitorResponse->json();
            echo "[" . date('H:i:s') . "] 📋 Visitor API Response: " . json_encode($visitorJson) . "\n";

            if (empty($visitorJson['status']) || $visitorJson['status'] !== 'success') {
                echo "[" . date('H:i:s') . "] ❌ Visitor API status not success\n";
                return null;
            }

            if (empty($visitorJson['data']) || empty($visitorJson['data'][0])) {
                echo "[" . date('H:i:s') . "] ❌ No visitor data found\n";
                return null;
            }

            $visitorData = $visitorJson['data'][0];
            $visitorStaffNo = $visitorData['staffNo'] ?? $vendorStaffNo;
            
            echo "[" . date('H:i:s') . "] 👤 Visitor Details Found:\n";
            echo "[" . date('H:i:s') . "]   - Staff No: $visitorStaffNo\n";
            echo "[" . date('H:i:s') . "]   - Full Name: " . ($visitorData['fullName'] ?? 'N/A') . "\n";
            echo "[" . date('H:i:s') . "]   - Company: " . ($visitorData['companyName'] ?? 'N/A') . "\n";
            echo "[" . date('H:i:s') . "]   - IC No: " . ($visitorData['icNo'] ?? 'N/A') . "\n";
            echo "[" . date('H:i:s') . "] ✅ Both APIs processed successfully\n";

            // Return combined data
            return [
                'status' => 'success',
                'data' => [
                    'staffNo' => $visitorStaffNo,
                    'cardId' => $cardId, // Original decrypted card ID
                    'icNo' => $icNo,
                    'visitorData' => $visitorData,
                    'vendorData' => $vendorData
                ]
            ];

        } catch (\Exception $e) {
            echo "[" . date('H:i:s') . "] ❌ Java API Exception: " . $e->getMessage() . "\n";
            return null;
        }
    }

    private function grantAccess($sock, $deviceId, $cardId, $icNo, $locationName, $isType, $additionalData = [])
{
    echo "[" . date('H:i:s') . "] ✅ Access granted for IC No: $icNo at $locationName ($isType)\n";
    echo "[" . date('H:i:s') . "] 💳 Card ID used: $cardId\n";
    
    $this->sendOpenDoorCommand($sock, $deviceId);

    // ✅ Log successful access with card_id and ic_no
    DB::table('device_access_logs')->insert([
        'device_id' => $deviceId,
        'card_no' => $cardId,
        'staff_no' => $icNo, 
        'location_name' => $locationName,
        'access_granted' => 1,
        'reason' => 'Access granted',
        'created_at' => now(),
    ]);
    
    echo "[" . date('H:i:s') . "] 💾 Log stored - Card ID: $cardId, IC No: $icNo\n";
}


private function sendAlarm($sock, $deviceId, $cardId = null, $reason = null, $icNo = null, $locationName = null)
{
    echo "[" . date('H:i:s') . "] 🚨 Triggering alarm for device $deviceId - Reason: $reason\n";
    echo "[" . date('H:i:s') . "] 💳 Card ID: $cardId, IC No: $icNo\n";
    
    // Trigger device alarm
    $this->triggerDeviceAlarm($sock, $deviceId);

    // ✅ Log denied access with card_id and ic_no
    DB::table('device_access_logs')->insert([
        'device_id' => $deviceId,
        'card_no' => $cardId,      // ✅ Decrypted Card ID (or null)
        'staff_no' => $icNo,          // ✅ IC No (or null)
        'location_name' => $locationName,
        'access_granted' => 0,
        'reason' => $reason,
        'created_at' => now(),
    ]);
}

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


    public function runTestFlow($staffNo, $deviceId)
    {
        echo "[" . date('H:i:s') . "] 🧪 Running full test flow for StaffNo: $staffNo, DeviceID: $deviceId\n";

        $data = "SCode={$staffNo}&DeviceID={$deviceId}";
        $sock = null; // No real socket needed

        $this->handleOpenDoorRequest($sock, $data, '127.0.0.1');
    }

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

    private function isIpInRange($ip)
    {
        $range = DB::table('ip_ranges')->first();

        // Agar IP range configured hi nahi
        if (!$range) {
            echo "[" . date('H:i:s') . "] ⚠️ IP Range not configured – skipping IP check\n";
            return true; // fail-open OR change to false if strict
        }

        $ipLong   = ip2long($ip);
        $fromLong = ip2long($range->ip_range_from);
        $toLong   = ip2long($range->ip_range_to);

        if ($ipLong === false || $fromLong === false || $toLong === false) {
            return false;
        }

        return ($ipLong >= $fromLong && $ipLong <= $toLong);
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





// private function callJavaVendorApi($decryptedStaffNo)
// {
//     try {
//         $javaBaseUrl = env('JAVA_BACKEND_URL', 'http://localhost:8080');
//         $token = env('JAVA_API_TOKEN', '');
        
//         echo "[" . date('H:i:s') . "] 🌐 Calling Java API with decrypted staffNo: $decryptedStaffNo\n";
        
//         $url = $javaBaseUrl . '/api/vendorpass/get-visitor-details?staffNo=' . urlencode($decryptedStaffNo);
        
//         $response = Http::withHeaders([
//             'x-auth-token' => $token,
//             'Accept' => 'application/json',
//         ])->timeout(10)->get($url);

//         echo "[" . date('H:i:s') . "] 📡 Response Status: " . $response->status() . "\n";
        
//         if ($response->successful()) {
//             $data = $response->json();
//             echo "[" . date('H:i:s') . "] ✅ Java API Response Success\n";
//             return $data;
//         } else {
//             echo "[" . date('H:i:s') . "] ❌ Java API HTTP Error: " . $response->status() . "\n";
//             echo "[" . date('H:i:s') . "] ❌ Error body: " . $response->body() . "\n";
//             return null;
//         }
//     } catch (\Exception $e) {
//         echo "[" . date('H:i:s') . "] ❌ Java API exception: " . $e->getMessage() . "\n";
//         return null;
//     }
// }








    //if ($scode === 'TEST1234') {
      //  echo "[" . date('H:i:s') . "] 🧪 Test card detected! Granting immediate access.\n";

        // Get location info (optional, for logging)
        //$locationInfo = $this->getDeviceLocationInfo($deviceId);
        //$locationName = $locationInfo['location_name'] ?? 'Test Door';
        //$isType = $locationInfo['is_type'] ?? 'check_in';

       // $this->grantAccess($sock, $deviceId, $scode, $scode, $locationName, $isType);
    //    return;
    //}
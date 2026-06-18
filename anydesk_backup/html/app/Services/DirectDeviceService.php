<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class DirectDeviceService
{
    /**
     * Direct TCP communication with device using fsockopen
     */
    public function connectToDevice($deviceIp, $port = 9998, $timeout = 10)
    {
        try {
            Log::info("🔌 Attempting to connect to device: {$deviceIp}:{$port}");

            // Use fsockopen instead of socket_create
            $socket = fsockopen($deviceIp, $port, $errno, $errstr, $timeout);
            
            if (!$socket) {
                throw new \Exception("Connection failed [{$errno}]: {$errstr}");
            }
            
            // Set stream timeout
            stream_set_timeout($socket, $timeout);
            
            Log::info("✅ Successfully connected to device: {$deviceIp}:{$port}");
            return $socket;
            
        } catch (\Exception $e) {
            Log::error("❌ Device connection error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Build proper command frame as per your Java protocol
     */
    private function buildCommandFrame($commandCode, $data = '')
    {
        // According to your Java protocol format:
        // 02 00 0B FF FF FF FF FF FF 11 [COMMAND] FF 03
        
        $header = "\x02"; // STX
        $mac = "\xFF\xFF\xFF\xFF\xFF\xFF"; // Default MAC
        $control = "\x11"; // Control byte
        
        $commandData = $commandCode . $data;
        $dataLength = strlen($commandData);
        $totalLength = $dataLength + 8; // Including MAC + control
        
        $lengthBytes = pack('n', $totalLength); // 2-byte length (big-endian)
        
        $footer = "\xFF\x03"; // ETX
        
        // Build complete frame
        $frame = $header . $lengthBytes . $mac . $control . $commandData . $footer;
        
        return $frame;
    }
    
    /**
     * Send 4100 command to get device status (Run_StatusCode)
     */
    public function getDeviceStatusDirect($deviceIp, $port = 18887)
    {
        Log::info("🔧 Sending 4100 command to device: {$deviceIp}:{$port}");
        
        $socket = $this->connectToDevice($deviceIp, $port);
        
        if (!$socket) {
            return [
                'success' => false, 
                'error' => 'Connection failed - Device not responding',
                'device_ip' => $deviceIp,
                'port' => $port,
                'suggestions' => [
                    'Check device power and network cable',
                    'Verify device IP address: 192.168.100.15',
                    'Check if device is in TCP Server mode',
                    'Try telnet 192.168.100.15 18887 in command prompt'
                ]
            ];
        }
        
        try {
            // Build 4100 command (Run_StatusCode) as per Java protocol
            $commandCode = "\x41\x00"; // 4100 command
            $commandData = "\xFF"; // Additional data
            
            $frame = $this->buildCommandFrame($commandCode, $commandData);
            
            Log::info("📤 Sending command frame: " . bin2hex($frame));
            
            // Send command using fwrite
            $written = fwrite($socket, $frame, strlen($frame));
            
            if ($written === false) {
                throw new \Exception("Failed to send command to device");
            }
            
            Log::info("✅ Command sent successfully, waiting for response...");
            
            // Read response
            $response = '';
            
            // Read with timeout
            while (!feof($socket)) {
                $buffer = fread($socket, 1024);
                if ($buffer === false) {
                    break;
                }
                $response .= $buffer;
                
                // Check if we have complete frame (ends with FF03)
                if (substr($response, -2) === "\xFF\x03") {
                    break;
                }
                
                // Check timeout
                $info = stream_get_meta_data($socket);
                if ($info['timed_out']) {
                    break;
                }
            }
            
            fclose($socket);
            
            if (empty($response)) {
                throw new \Exception("No response from device - Check device configuration");
            }
            
            Log::info("📥 Device response: " . bin2hex($response));
            
            return [
                'success' => true,
                'response_hex' => bin2hex($response),
                'response_length' => strlen($response),
                'message' => 'Device communication successful'
            ];
            
        } catch (\Exception $e) {
            Log::error("❌ Device communication error: " . $e->getMessage());
            if (isset($socket)) {
                fclose($socket);
            }
            return [
                'success' => false, 
                'error' => $e->getMessage(),
                'device_ip' => $deviceIp
            ];
        }
    }
    
    /**
     * Simple port check using fsockopen
     */
    public function checkDevicePort($deviceIp, $port = 18887, $timeout = 5)
    {
        try {
            Log::info("🔍 Checking port {$port} on {$deviceIp}");
            
            $socket = @fsockopen($deviceIp, $port, $errno, $errstr, $timeout);
            
            if ($socket) {
                fclose($socket);
                Log::info("✅ Port {$port} is OPEN on {$deviceIp}");
                return [
                    'success' => true,
                    'message' => "Port {$port} is open on {$deviceIp}",
                    'device_ip' => $deviceIp,
                    'port' => $port
                ];
            } else {
                Log::error("❌ Port {$port} is CLOSED on {$deviceIp}: {$errstr}");
                return [
                    'success' => false,
                    'error' => "Port {$port} is closed or not responding",
                    'errno' => $errno,
                    'errstr' => $errstr,
                    'device_ip' => $deviceIp,
                    'port' => $port
                ];
            }
        } catch (\Exception $e) {
            Log::error("❌ Port check error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
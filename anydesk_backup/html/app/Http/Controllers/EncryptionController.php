<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class EncryptionController extends Controller
{
    private int $iterations = 100000;
    private string $secret;

    public function __construct()
    {
        // ✅ Get secret from .env file
        $this->secret = env('ENCRYPTION_SECRET', 'M&RV!$it0@');
        
        if (empty($this->secret)) {
            throw new \Exception('ENCRYPTION_SECRET is not configured in .env file');
        }
    }

    /**
     * Encrypt (NEW - AES-256-GCM)
     */
    public function encrypt(Request $request)
    {
        $request->validate([
            'value'  => 'required|string'
            // ✅ Remove secret from validation - use from .env
            // 'secret' => 'required|string|min:8'
        ]);

        $salt = random_bytes(16);
        $iv   = random_bytes(12);
        $tag  = '';

        $key = hash_pbkdf2(
            'sha256',
            $this->secret, // ✅ Use from .env
            $salt,
            $this->iterations,
            32,
            true
        );

        $ciphertext = openssl_encrypt(
            $request->value,
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        return response()->json([
            'ciphertext' => implode(':', [
                base64_encode($salt),
                base64_encode($iv),
                base64_encode($ciphertext),
                base64_encode($tag),
            ]),
            'message' => 'Encrypted using secret from .env'
        ]);
    }

    /**
     * Decrypt (AUTO-DETECT OLD + NEW)
     * ✅ No need to send secret in request
     */
    public function decrypt(Request $request)
    {
        $request->validate([
            'ciphertext' => 'required|string'
            // ✅ Remove secret from validation
            // 'secret'     => 'required|string|min:8'
        ]);

        $parts = explode(':', $request->ciphertext);

        return match (count($parts)) {
            3 => $this->decryptOldClient($parts),
            4 => $this->decryptNewSystem($parts),
            default => response()->json(['error' => 'Invalid ciphertext format'], 400),
        };
    }

    /**
     * OLD CLIENT DECRYPT (AES-256-CBC)
     */
    private function decryptOldClient(array $parts)
    {
        [$saltHex, $ivHex, $cipherBase64] = $parts;

        $salt       = hex2bin($saltHex);
        $iv         = hex2bin($ivHex);
        $ciphertext = base64_decode($cipherBase64);

        $key = hash_pbkdf2(
            'sha256',
            $this->secret, // ✅ Use from .env
            $salt,
            1000,          // OLD client iterations
            32,
            true
        );

        $decrypted = openssl_decrypt(
            $ciphertext,
            'AES-256-CBC',
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );

        if ($decrypted === false) {
            return response()->json([
                'error' => 'Old client decryption failed',
                'note' => 'Using secret from .env: ' . substr($this->secret, 0, 3) . '...'
            ], 400);
        }

        return response()->json([
            'value' => $decrypted,
            'source' => 'old-client',
            'message' => 'Decrypted using .env secret'
        ]);
    }

    /**
     * NEW SYSTEM DECRYPT (AES-256-GCM)
     */
    private function decryptNewSystem(array $parts)
    {
        [$salt, $iv, $ciphertext, $tag] = array_map('base64_decode', $parts);

        $key = hash_pbkdf2(
            'sha256',
            $this->secret, // ✅ Use from .env
            $salt,
            $this->iterations,
            32,
            true
        );

        $decrypted = openssl_decrypt(
            $ciphertext,
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($decrypted === false) {
            return response()->json([
                'error' => 'New system decryption failed',
                'note' => 'Using secret from .env: ' . substr($this->secret, 0, 3) . '...'
            ], 400);
        }

        return response()->json([
            'value' => $decrypted,
            'source' => 'new-system',
            'message' => 'Decrypted using .env secret'
        ]);
    }

    /**
     * ✅ Optional: Get encryption info (for debugging)
     */
    public function getInfo()
    {
        return response()->json([
            'secret_length' => strlen($this->secret),
            'secret_preview' => substr($this->secret, 0, 3) . '...',
            'iterations' => $this->iterations,
            'environment' => app()->environment()
        ]);
    }
}





// namespace App\Http\Controllers;

// use Illuminate\Http\Request;

// class EncryptionController extends Controller
// {
//     private int $iterations = 100000; 

//     public function encrypt(Request $request)
//     {
//         $request->validate([
//             'value'  => 'required|string',
//             'secret' => 'required|string|min:8'
//         ]);

//         $value  = $request->value;
//         $secret = $request->secret;

//         $salt = random_bytes(16); 
//         $iv   = random_bytes(12); 

//         $key = hash_pbkdf2(
//             'sha256',
//             $secret,
//             $salt,
//             $this->iterations,
//             32,
//             true
//         );

//         $tag = '';

//         $ciphertext = openssl_encrypt(
//             $value,
//             'aes-256-gcm',
//             $key,
//             OPENSSL_RAW_DATA,
//             $iv,
//             $tag
//         );

//         if ($ciphertext === false) {
//             return response()->json([
//                 'error' => 'Encryption failed'
//             ], 500);
//         }

//         $result = implode(':', [
//             base64_encode($salt),
//             base64_encode($iv),
//             base64_encode($ciphertext),
//             base64_encode($tag)
//         ]);

//         return response()->json([
//             'ciphertext' => $result
//         ]);
//     }

//     public function decrypt(Request $request)
//     {
//         $request->validate([
//             'ciphertext' => 'required|string',
//             'secret'     => 'required|string|min:8'
//         ]);

//         $secret = $request->secret;
//         $parts  = explode(':', $request->ciphertext);

//         if (count($parts) !== 4) {
//             return response()->json([
//                 'error' => 'Invalid ciphertext format'
//             ], 400);
//         }

//         [$salt, $iv, $ciphertext, $tag] = array_map('base64_decode', $parts);

//         $key = hash_pbkdf2(
//             'sha256',
//             $secret,
//             $salt,
//             $this->iterations,
//             32,
//             true
//         );

//         $decrypted = openssl_decrypt(
//             $ciphertext,
//             'aes-256-gcm',
//             $key,
//             OPENSSL_RAW_DATA,
//             $iv,
//             $tag
//         );

//         if ($decrypted === false) {
//             return response()->json([
//                 'error' => 'Invalid secret or corrupted data'
//             ], 400);
//         }

//         return response()->json([
//             'value' => $decrypted
//         ]);
//     }
// }


<?php

namespace App\Services;

class EncryptionService
{
    private string $secret;
    private int $newIterations = 100000;
    private int $oldIterations = 1000;

    public function __construct()
    {
        $this->secret = env('ENCRYPTION_SECRET', 'default-secret');
    }

    public function decrypt(string $ciphertext): ?string
    {
        $parts = explode(':', $ciphertext);

        return match (count($parts)) {
            3 => $this->decryptOldClient($parts),
            4 => $this->decryptNewSystem($parts),
            default => null,
        };
    }

    private function decryptOldClient(array $parts): ?string
    {
        [$saltHex, $ivHex, $cipherBase64] = $parts;

        $salt = hex2bin($saltHex);
        $iv = hex2bin($ivHex);
        $ciphertext = base64_decode($cipherBase64);

        $key = hash_pbkdf2(
            'sha256',
            $this->secret,
            $salt,
            $this->oldIterations,
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

        return $decrypted !== false ? $decrypted : null;
    }

    private function decryptNewSystem(array $parts): ?string
    {
        [$salt, $iv, $ciphertext, $tag] = array_map('base64_decode', $parts);

        $key = hash_pbkdf2(
            'sha256',
            $this->secret,
            $salt,
            $this->newIterations,
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

        return $decrypted !== false ? $decrypted : null;
    }
} 


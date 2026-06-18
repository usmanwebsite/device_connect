<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class QRCodeController extends Controller
{
    public function generateEncryptedString()
    {
        $data = '123456789';
        $encrypted = encrypt($data);

        return response()->json([
            'original' => $data,
            'encrypted' => $encrypted
        ]);
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class QRValidationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'qr_data' => 'required|string|min:1|max:500',
            'reader_id' => 'required|string|max:50',
        ];
    }
}
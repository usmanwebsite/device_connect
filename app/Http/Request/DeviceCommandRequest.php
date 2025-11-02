<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DeviceCommandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'command' => 'required|string|in:open_door,close_door,trigger_alarm,clear_cards,get_status',
            'data' => 'sometimes|array',
        ];
    }
}
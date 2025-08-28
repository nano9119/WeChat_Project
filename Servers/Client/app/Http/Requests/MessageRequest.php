<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MessageRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'conversation_id' => 'required|exists:conversations,id',
            'sender_id' => 'required|exists:users,id',
            'content' => 'required|string',
        ];
    }
}
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TranscribeAssistantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'audio' => ['required', 'file', 'max:10240', 'mimetypes:audio/webm,audio/wav,audio/mpeg,audio/mp4,video/webm'],
        ];
    }
}

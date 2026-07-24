<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ContentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'prompt' => 'required|string|max:1000',
            'content_type' => 'required|in:social_media,ad_copy,blog_post,email',
            'tone' => 'nullable|string|max:50',
            'style' => 'nullable|string|max:50',
        ];
    }

    public function messages(): array
    {
        return [
            'prompt.required' => 'Petunjuk / Prompt konten wajib diisi.',
            'content_type.required' => 'Jenis konten wajib dipilih.',
        ];
    }
}

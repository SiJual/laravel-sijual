<?php

return [
    'gemini' => [
        'api_key' => env('GEMINI_API_KEY', ''),
        'model' => env('GEMINI_MODEL', 'gemini-2.0-flash'),
    ],
    'openai' => [
        'api_key' => env('OPENAI_API_KEY', ''),
        'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
    ],
    'flux' => [
        'endpoint' => env('FLUX_ENDPOINT', 'http://localhost:8080'),
    ],
    'whisper' => [
        'api_key' => env('WHISPER_API_KEY', ''),
    ],
];

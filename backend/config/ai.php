<?php

return [
    'provider' => env('AI_PROVIDER', 'heuristic'),
    'api_key' => env('AI_API_KEY'),
    'model' => env('AI_MODEL', 'gpt-4o-mini'),
    'base_url' => env('AI_BASE_URL', 'https://api.openai.com/v1'),
    'monthly_request_cap' => (int) env('AI_MONTHLY_REQUEST_CAP', 200),
    'rate_per_minute' => (int) env('AI_RATE_PER_MINUTE', 10),
    'speech' => [
        'provider' => env('SPEECH_TO_TEXT_PROVIDER'),
        'api_key' => env('SPEECH_TO_TEXT_API_KEY', env('AI_API_KEY')),
        'model' => env('SPEECH_TO_TEXT_MODEL', 'whisper-1'),
        'base_url' => env('SPEECH_TO_TEXT_BASE_URL', env('AI_BASE_URL', 'https://api.openai.com/v1')),
    ],
];

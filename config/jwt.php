<?php

return [
    'secret' => env('JWT_SECRET', ''),
    'ttl' => (int) env('JWT_TTL', 1440),
    'remember_ttl' => (int) env('JWT_REMEMBER_TTL', 43200),
    'cookie' => 'sijual_token',
];

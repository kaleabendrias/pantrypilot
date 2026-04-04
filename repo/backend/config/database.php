<?php

$env = static function (string $key, string $fallback = ''): string {
    $value = getenv($key);
    return (is_string($value) && trim($value) !== '') ? trim($value) : $fallback;
};

return [
    'default' => 'mysql',
    'connections' => [
        'mysql' => [
            'type' => 'mysql',
            'hostname' => $env('DB_HOST', 'mysql'),
            'database' => $env('DB_NAME', 'pantrypilot'),
            'username' => $env('DB_USER', 'pantry'),
            'password' => $env('DB_PASS', 'pantrypass'),
            'hostport' => $env('DB_PORT', '3306'),
            'charset' => 'utf8mb4',
            'prefix' => '',
            'debug' => false,
            'break_reconnect' => true,
        ],
    ],
];

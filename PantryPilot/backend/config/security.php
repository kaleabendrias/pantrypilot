<?php

$env = static function (string $key, string $fallback): string {
    $value = getenv($key);
    if (!is_string($value) || trim($value) === '') {
        return $fallback;
    }
    return trim($value);
};

return [
    'auth' => [
        'critical_reauth_ttl_seconds' => 300,
    ],
    'gateway' => [
        'merchant_id' => 'LOCAL-MERCHANT-001',
        'hmac_secret' => $env('PANTRYPILOT_GATEWAY_HMAC_SECRET', 'local_gateway_hmac_secret_change_me'),
        'order_auto_cancel_minutes' => 10,
    ],
    'messaging' => [
        'daily_marketing_cap' => 2,
        'quiet_hours_start' => '21:00',
        'quiet_hours_end' => '08:00',
    ],
    'files' => [
        'max_size_bytes' => 10485760,
        'allowed_mime_types' => [
            'image/png',
            'image/jpeg',
            'application/pdf',
            'text/csv',
        ],
        'download_url_ttl_seconds' => 300,
        'retention_days' => 180,
    ],
    'crypto' => [
        'cipher' => 'AES-256-CBC',
        'key' => $env('PANTRYPILOT_CRYPTO_KEY', 'local_sensitive_data_key_32bytes_long!'),
        'iv' => $env('PANTRYPILOT_CRYPTO_IV', '1234567890abcdef'),
    ],
];

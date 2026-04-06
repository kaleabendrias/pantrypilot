<?php

$env = static function (string $key, string $fallback = ''): string {
    $value = getenv($key);
    if (!is_string($value) || trim($value) === '') {
        return $fallback;
    }
    return trim($value);
};

$hmacSecret = $env('PANTRYPILOT_GATEWAY_HMAC_SECRET', 'insecure-default-hmac-secret-replace-in-production');
$cryptoKey = $env('PANTRYPILOT_CRYPTO_KEY', 'insecure-default-key-32b!!!!');
$cryptoIv = $env('PANTRYPILOT_CRYPTO_IV', 'insecure-iv-16b!');

if (strlen($cryptoKey) < 16) {
    $cryptoKey = str_pad($cryptoKey, 16, '!');
}
if (strlen($cryptoIv) < 16) {
    $cryptoIv = str_pad($cryptoIv, 16, '!');
}

return [
    'auth' => [
        'critical_reauth_ttl_seconds' => 300,
    ],
    'gateway' => [
        'merchant_id' => $env('PANTRYPILOT_MERCHANT_ID', 'LOCAL-MERCHANT-001'),
        'hmac_secret' => $hmacSecret,
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
        'key' => $cryptoKey,
        'iv' => $cryptoIv,
    ],
];

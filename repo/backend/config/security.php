<?php

$env = static function (string $key, string $fallback = ''): string {
    $value = getenv($key);
    if (!is_string($value) || trim($value) === '') {
        return $fallback;
    }
    return trim($value);
};

$requireEnv = static function (string $key, string $description) use ($env): string {
    $value = $env($key);
    if ($value === '') {
        throw new \RuntimeException(
            "Required environment variable {$key} ({$description}) is not set. "
            . "Set it in your environment or docker-compose.yml before starting."
        );
    }
    return $value;
};

$hmacSecret = $requireEnv('PANTRYPILOT_GATEWAY_HMAC_SECRET', 'HMAC-SHA256 secret for payment gateway callback verification');
$cryptoKey = $requireEnv('PANTRYPILOT_CRYPTO_KEY', 'AES-256-CBC encryption key for sensitive data at rest');
$cryptoIv = $requireEnv('PANTRYPILOT_CRYPTO_IV', '16-byte AES initialization vector (used for legacy decryption; new encryption uses random IVs)');

if (strlen($cryptoKey) < 16) {
    throw new \RuntimeException('PANTRYPILOT_CRYPTO_KEY must be at least 16 characters (will be hashed to 32-byte key internally)');
}
if (strlen($cryptoIv) < 16) {
    throw new \RuntimeException('PANTRYPILOT_CRYPTO_IV must be at least 16 characters');
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

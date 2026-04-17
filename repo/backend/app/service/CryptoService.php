<?php
declare(strict_types=1);

namespace app\service;

use think\facade\Config;

final class CryptoService
{
    private const GCM_PREFIX  = 'g2:';
    private const NONCE_BYTES = 12;
    private const TAG_BYTES   = 16;

    public function encrypt(string $plain): string
    {
        if ($plain === '') {
            return '';
        }

        $key   = $this->derivedKey();
        $nonce = random_bytes(self::NONCE_BYTES);
        $tag   = '';

        $enc = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag, '', self::TAG_BYTES);
        if ($enc === false) {
            throw new \RuntimeException('Encryption failed');
        }

        // Layout: nonce[12] | tag[16] | ciphertext
        return self::GCM_PREFIX . base64_encode($nonce . $tag . $enc);
    }

    public function decrypt(string $encrypted): string
    {
        if ($encrypted === '') {
            return '';
        }

        if (str_starts_with($encrypted, self::GCM_PREFIX)) {
            return $this->decryptGcm(substr($encrypted, strlen(self::GCM_PREFIX)));
        }

        return $this->decryptLegacyCbc($encrypted);
    }

    public function mask(string $value): string
    {
        if ($value === '') {
            return '';
        }
        $len = strlen($value);
        if ($len <= 4) {
            return str_repeat('*', $len);
        }
        return substr($value, 0, 2) . str_repeat('*', $len - 4) . substr($value, -2);
    }

    private function decryptGcm(string $b64): string
    {
        $raw = base64_decode($b64, true);
        if ($raw === false || strlen($raw) < self::NONCE_BYTES + self::TAG_BYTES + 1) {
            return '';
        }

        $key        = $this->derivedKey();
        $nonce      = substr($raw, 0, self::NONCE_BYTES);
        $tag        = substr($raw, self::NONCE_BYTES, self::TAG_BYTES);
        $ciphertext = substr($raw, self::NONCE_BYTES + self::TAG_BYTES);

        // openssl_decrypt returns false when the authentication tag does not match,
        // ensuring any tampered ciphertext is strictly rejected.
        $dec = openssl_decrypt($ciphertext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag);
        return $dec === false ? '' : $dec;
    }

    private function decryptLegacyCbc(string $encrypted): string
    {
        $cipher = (string) Config::get('security.crypto.cipher', 'AES-256-CBC');
        $key    = $this->derivedKey();

        $raw = base64_decode($encrypted, true);
        if ($raw === false) {
            return '';
        }

        if (strlen($raw) < 17) {
            $legacyIv = substr((string) Config::get('security.crypto.iv'), 0, 16);
            $legacy   = openssl_decrypt($raw, $cipher, $key, OPENSSL_RAW_DATA, $legacyIv);
            return $legacy === false ? '' : $legacy;
        }

        $iv         = substr($raw, 0, 16);
        $ciphertext = substr($raw, 16);
        $dec        = openssl_decrypt($ciphertext, $cipher, $key, OPENSSL_RAW_DATA, $iv);
        return $dec === false ? '' : $dec;
    }

    private function derivedKey(): string
    {
        return substr(hash('sha256', (string) Config::get('security.crypto.key'), true), 0, 32);
    }
}

<?php
declare(strict_types=1);

namespace app\service;

use think\facade\Config;

final class CryptoService
{
    public function encrypt(string $plain): string
    {
        if ($plain === '') {
            return '';
        }

        $cipher = (string) Config::get('security.crypto.cipher');
        $key = substr(hash('sha256', (string) Config::get('security.crypto.key'), true), 0, 32);
        $iv = random_bytes(16);

        $enc = openssl_encrypt($plain, $cipher, $key, OPENSSL_RAW_DATA, $iv);
        if ($enc === false) {
            throw new \RuntimeException('Encryption failed');
        }

        return base64_encode($iv . $enc);
    }

    public function decrypt(string $encrypted): string
    {
        if ($encrypted === '') {
            return '';
        }

        $cipher = (string) Config::get('security.crypto.cipher');
        $key = substr(hash('sha256', (string) Config::get('security.crypto.key'), true), 0, 32);

        $raw = base64_decode($encrypted, true);
        if ($raw === false) {
            return '';
        }

        if (strlen($raw) < 17) {
            $legacyIv = substr((string) Config::get('security.crypto.iv'), 0, 16);
            $legacy = openssl_decrypt($raw, $cipher, $key, OPENSSL_RAW_DATA, $legacyIv);
            return $legacy === false ? '' : $legacy;
        }

        $iv = substr($raw, 0, 16);
        $ciphertext = substr($raw, 16);

        $dec = openssl_decrypt($ciphertext, $cipher, $key, OPENSSL_RAW_DATA, $iv);
        return $dec === false ? '' : $dec;
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
}

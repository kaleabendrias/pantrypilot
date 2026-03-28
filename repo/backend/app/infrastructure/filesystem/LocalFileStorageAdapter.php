<?php
declare(strict_types=1);

namespace app\infrastructure\filesystem;

final class LocalFileStorageAdapter
{
    private string $baseDir;

    public function __construct(string $baseDir)
    {
        $this->baseDir = rtrim($baseDir, '/');
    }

    public function saveBase64(string $filename, string $contentBase64): array
    {
        if (!is_dir($this->baseDir)) {
            mkdir($this->baseDir, 0775, true);
        }

        $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename) ?: uniqid('file_', true);
        $target = $this->baseDir . '/' . date('Ymd_His_') . $safeName;
        $bytes = base64_decode($contentBase64, true);

        if ($bytes === false) {
            throw new \RuntimeException('Invalid base64 payload');
        }

        file_put_contents($target, $bytes);

        return [
            'storage_path' => str_replace('/var/www/html/', '', $target),
            'size_bytes' => strlen($bytes),
            'bytes' => $bytes,
        ];
    }

    public function absolutePath(string $storagePath): string
    {
        return '/var/www/html/' . ltrim($storagePath, '/');
    }

    public function read(string $storagePath): string
    {
        $path = $this->absolutePath($storagePath);
        if (!is_file($path)) {
            throw new \RuntimeException('File not found');
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new \RuntimeException('File read failed');
        }

        return $contents;
    }

    public function delete(string $storagePath): bool
    {
        $path = $this->absolutePath($storagePath);
        return is_file($path) ? unlink($path) : false;
    }
}

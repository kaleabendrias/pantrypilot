<?php
declare(strict_types=1);

namespace app\service;

use app\exception\ForbiddenException;
use app\exception\NotFoundException;
use app\exception\ValidationException;
use app\infrastructure\filesystem\LocalFileStorageAdapter;
use app\repository\FileRepository;
use think\facade\Config;
use think\facade\Log;

final class FileService
{
    public function __construct(
        private readonly FileRepository $fileRepository,
        private readonly LocalFileStorageAdapter $fileStorageAdapter
    ) {
    }

    public function uploadBase64(array $payload): array
    {
        $filename = (string) ($payload['filename'] ?? 'upload.bin');
        $mimeType = (string) ($payload['mime_type'] ?? 'application/octet-stream');
        $maxSize = (int) Config::get('security.files.max_size_bytes', 5242880);
        $allowed = Config::get('security.files.allowed_mime_types', []);

        if (!in_array($mimeType, $allowed, true)) {
            throw new ValidationException('Unsupported file type');
        }

        $stored = $this->fileStorageAdapter->saveBase64($payload['filename'], $payload['content_base64']);
        if ((int) $stored['size_bytes'] > $maxSize) {
            $this->fileStorageAdapter->delete((string) $stored['storage_path']);
            throw new ValidationException('File too large');
        }

        $storagePath = (string) $stored['storage_path'];
        $bytes = (string) ($stored['bytes'] ?? '');
        $magicVerified = $this->verifyMagicBytes($mimeType, $bytes);
        if (!$magicVerified) {
            $this->fileStorageAdapter->delete($storagePath);
            throw new ValidationException('Magic-byte verification failed');
        }

        $watermarked = (bool) ($payload['watermark'] ?? false);
        if ($watermarked) {
            if (!in_array($mimeType, ['image/png', 'image/jpeg'], true)) {
                $this->fileStorageAdapter->delete($storagePath);
                throw new ValidationException('Watermarking is only supported for PNG/JPEG images');
            }
            try {
                $bytes = $this->renderImageWatermark($mimeType, $bytes);
            } catch (\Throwable $e) {
                $this->fileStorageAdapter->delete($storagePath);
                throw $e;
            }
            if (!$this->verifyMagicBytes($mimeType, $bytes)) {
                $this->fileStorageAdapter->delete($storagePath);
                throw new ValidationException('Watermarked image failed magic-byte verification');
            }
            file_put_contents($this->fileStorageAdapter->absolutePath($storagePath), $bytes);
            $stored['size_bytes'] = strlen($bytes);
            if ((int) $stored['size_bytes'] > $maxSize) {
                $this->fileStorageAdapter->delete($storagePath);
                throw new ValidationException('File too large');
            }
        }

        $sha256 = hash('sha256', $bytes);
        $id = $this->fileRepository->addAttachment([
            'owner_type' => $payload['owner_type'] ?? 'general',
            'owner_id' => (int) ($payload['owner_id'] ?? 0),
            'filename' => $filename,
            'mime_type' => $mimeType,
            'storage_path' => $stored['storage_path'],
            'size_bytes' => $stored['size_bytes'],
            'sha256' => $sha256,
            'magic_verified' => $magicVerified ? 1 : 0,
            'watermarked' => $watermarked ? 1 : 0,
        ]);

        Log::info('files.upload_base64.stored', ['attachment_id' => $id, 'owner_type' => $payload['owner_type'] ?? 'general', 'owner_id' => (int) ($payload['owner_id'] ?? 0), 'mime_type' => $mimeType, 'size_bytes' => (int) $stored['size_bytes']]);

        return ['id' => $id, 'storage_path' => $stored['storage_path'], 'sha256' => $sha256];
    }

    public function list(array $scopes = [], array $authUser = []): array
    {
        $items = $this->fileRepository->listAttachmentsScoped($scopes, $authUser);
        foreach ($items as &$item) {
            $item['sha256'] = $this->maskHash((string) ($item['sha256'] ?? ''));
        }
        return $items;
    }

    public function createSignedDownloadUrl(int $attachmentId, array $scopes = [], array $authUser = []): array
    {
        $attachment = $this->fileRepository->byId($attachmentId);
        if (!$attachment) {
            throw new NotFoundException('Attachment not found');
        }
        if (!$this->fileRepository->attachmentInScope($attachmentId, $scopes, $authUser)) {
            throw new ForbiddenException('Forbidden');
        }

        $token = hash('sha256', $attachment['id'] . '|' . random_bytes(12) . '|' . date('c'));
        $expireAt = date('Y-m-d H:i:s', time() + (int) Config::get('security.files.download_url_ttl_seconds', 300));
        $this->fileRepository->updateSignedToken($attachmentId, $token, $expireAt);

        return [
            'attachment_id' => $attachmentId,
            'download_url' => '/api/v1/files/download/' . $attachmentId . '?token=' . $token,
            'expire_at' => $expireAt,
        ];
    }

    public function validateDownloadToken(int $attachmentId, string $token, array $scopes = [], array $authUser = []): array
    {
        $attachment = $this->fileRepository->byId($attachmentId);
        if (!$attachment) {
            throw new NotFoundException('Attachment not found');
        }
        if (!$this->fileRepository->attachmentInScope($attachmentId, $scopes, $authUser)) {
            throw new ForbiddenException('Forbidden');
        }

        if ((string) ($attachment['hotlink_token'] ?? '') !== $token) {
            throw new ForbiddenException('Invalid hotlink token');
        }

        if (strtotime((string) ($attachment['signed_url_expire_at'] ?? '1970-01-01 00:00:00')) <= time()) {
            throw new ForbiddenException('Signed URL expired');
        }

        return [
            'filename' => $attachment['filename'],
            'mime_type' => $attachment['mime_type'],
            'content_base64' => base64_encode($this->fileStorageAdapter->read((string) $attachment['storage_path'])),
        ];
    }

    public function cleanupLifecycle(array $scopes = [], array $authUser = []): array
    {
        $retentionDays = (int) Config::get('security.files.retention_days', 180);
        $expired = $this->fileRepository->expiredAttachments($retentionDays, $scopes, $authUser);

        $deletedRecords = 0;
        $deletedFiles = 0;
        $missingFiles = 0;
        $errors = 0;

        foreach ($expired as $row) {
            $id = (int) ($row['id'] ?? 0);
            $storagePath = (string) ($row['storage_path'] ?? '');
            if ($id < 1 || $storagePath === '') {
                continue;
            }

            try {
                $absolutePath = $this->fileStorageAdapter->absolutePath($storagePath);
                if (is_file($absolutePath)) {
                    if ($this->fileStorageAdapter->delete($storagePath)) {
                        $deletedFiles++;
                    } else {
                        throw new \RuntimeException('failed to delete file path: ' . $storagePath);
                    }
                } else {
                    $missingFiles++;
                    Log::info('files.cleanup.missing_file', ['attachment_id' => $id, 'storage_path' => $storagePath]);
                }

                if ($this->fileRepository->deleteAttachment($id)) {
                    $deletedRecords++;
                }
            } catch (\Throwable $e) {
                $errors++;
                Log::info('files.cleanup.error', ['attachment_id' => $id, 'storage_path' => $storagePath, 'error' => $e->getMessage()]);
            }
        }

        return [
            'deleted_records' => $deletedRecords,
            'deleted_files' => $deletedFiles,
            'missing_files' => $missingFiles,
            'errors' => $errors,
            'retention_days' => $retentionDays,
        ];
    }

    private function verifyMagicBytes(string $mimeType, string $bytes): bool
    {
        $prefix = substr($bytes, 0, 4);
        if ($mimeType === 'application/pdf') {
            return str_starts_with($bytes, '%PDF');
        }

        if ($mimeType === 'image/png') {
            return $prefix === "\x89PNG";
        }

        if ($mimeType === 'image/jpeg') {
            return str_starts_with($bytes, "\xFF\xD8\xFF");
        }

        if ($mimeType === 'text/csv') {
            return mb_check_encoding($bytes, 'UTF-8');
        }

        return false;
    }

    private function maskHash(string $hash): string
    {
        if ($hash === '' || strlen($hash) < 12) {
            return $hash;
        }

        return substr($hash, 0, 6) . str_repeat('*', 52) . substr($hash, -6);
    }

    private function renderImageWatermark(string $mimeType, string $bytes): string
    {
        if (!function_exists('imagecreatefromstring') || !function_exists('imagestring')) {
            throw new ValidationException('Image watermarking requires GD extension support');
        }

        $image = imagecreatefromstring($bytes);
        if ($image === false) {
            throw new ValidationException('Invalid image payload for watermark rendering');
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $label = 'PantryPilot';
        $font = 3;
        $textWidth = imagefontwidth($font) * strlen($label);
        $textHeight = imagefontheight($font);
        $x = max(5, $width - $textWidth - 8);
        $y = max(5, $height - $textHeight - 8);

        if (function_exists('imagealphablending')) {
            imagealphablending($image, true);
        }
        $color = imagecolorallocatealpha($image, 255, 255, 255, 70);
        imagestring($image, $font, $x, $y, $label, $color);

        ob_start();
        $written = false;
        if ($mimeType === 'image/png') {
            if (function_exists('imagesavealpha')) {
                imagesavealpha($image, true);
            }
            $written = imagepng($image);
        } else {
            $written = imagejpeg($image, null, 90);
        }
        $rendered = (string) ob_get_clean();
        imagedestroy($image);

        if (!$written || $rendered === '') {
            throw new ValidationException('Watermark rendering failed');
        }

        return $rendered;
    }
}

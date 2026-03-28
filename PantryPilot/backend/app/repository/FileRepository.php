<?php
declare(strict_types=1);

namespace app\repository;

use think\facade\Db;

final class FileRepository
{
    public function addAttachment(array $data): int
    {
        return (int) Db::name('attachments')->insertGetId([
            'owner_type' => $data['owner_type'],
            'owner_id' => (int) $data['owner_id'],
            'filename' => $data['filename'],
            'mime_type' => $data['mime_type'],
            'storage_path' => $data['storage_path'],
            'size_bytes' => (int) $data['size_bytes'],
            'sha256' => $data['sha256'] ?? null,
            'magic_verified' => (int) ($data['magic_verified'] ?? 0),
            'watermarked' => (int) ($data['watermarked'] ?? 0),
            'hotlink_token' => $data['hotlink_token'] ?? null,
            'signed_url_expire_at' => $data['signed_url_expire_at'] ?? null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function listAttachments(): array
    {
        return Db::name('attachments')->order('id', 'desc')->select()->toArray();
    }

    public function listAttachmentsScoped(array $scopes = [], array $authUser = []): array
    {
        $query = Db::name('attachments')->alias('a')
            ->leftJoin('bookings b', "a.owner_type='booking' AND b.id = a.owner_id")
            ->leftJoin('payments p', "a.owner_type='payment' AND p.id = a.owner_id")
            ->order('a.id', 'desc')
            ->field('a.*');

        $this->applyAttachmentScopeConditions($query, $scopes, $authUser);

        return $query->select()->toArray();
    }

    public function updateSignedToken(int $id, string $hotlinkToken, string $expiresAt): void
    {
        Db::name('attachments')->where('id', $id)->update([
            'hotlink_token' => $hotlinkToken,
            'signed_url_expire_at' => $expiresAt,
        ]);
    }

    public function byId(int $id): ?array
    {
        $row = Db::name('attachments')->where('id', $id)->find();
        return $row ?: null;
    }

    public function attachmentInScope(int $id, array $scopes = [], array $authUser = []): bool
    {
        $query = Db::name('attachments')->alias('a')
            ->leftJoin('bookings b', "a.owner_type='booking' AND b.id = a.owner_id")
            ->leftJoin('payments p', "a.owner_type='payment' AND p.id = a.owner_id")
            ->where('a.id', $id)
            ->field('a.id');

        $this->applyAttachmentScopeConditions($query, $scopes, $authUser);

        return $query->count() > 0;
    }

    private function applyAttachmentScopeConditions($query, array $scopes, array $authUser): void
    {
        $store = array_map('strval', $scopes['store'] ?? []);
        $warehouse = array_map('strval', $scopes['warehouse'] ?? []);
        $department = array_map('strval', $scopes['department'] ?? []);
        $authId = (int) ($authUser['id'] ?? 0);
        $authStore = (string) ($authUser['store_id'] ?? '');
        $authWarehouse = (string) ($authUser['warehouse_id'] ?? '');
        $authDepartment = (string) ($authUser['department_id'] ?? '');

        $query->where(function ($scopeQuery) use ($store, $warehouse, $department, $authId, $authStore, $authWarehouse, $authDepartment) {
            $scopeQuery->where(function ($qUser) use ($authId) {
                $qUser->where('a.owner_type', 'user')->where('a.owner_id', $authId);
            });

            $scopeQuery->whereOr(function ($qBooking) use ($store, $warehouse, $department, $authStore, $authWarehouse, $authDepartment) {
                $qBooking->where('a.owner_type', 'booking');
                if ($store !== []) {
                    $qBooking->whereIn('b.store_id', $store);
                } elseif ($authStore !== '') {
                    $qBooking->where('b.store_id', $authStore);
                }
                if ($warehouse !== []) {
                    $qBooking->whereIn('b.warehouse_id', $warehouse);
                } elseif ($authWarehouse !== '') {
                    $qBooking->where('b.warehouse_id', $authWarehouse);
                }
                if ($department !== []) {
                    $qBooking->whereIn('b.department_id', $department);
                } elseif ($authDepartment !== '') {
                    $qBooking->where('b.department_id', $authDepartment);
                }
            });

            $scopeQuery->whereOr(function ($qPayment) use ($store, $warehouse, $department, $authStore, $authWarehouse, $authDepartment) {
                $qPayment->where('a.owner_type', 'payment');
                if ($store !== []) {
                    $qPayment->whereIn('p.store_id', $store);
                } elseif ($authStore !== '') {
                    $qPayment->where('p.store_id', $authStore);
                }
                if ($warehouse !== []) {
                    $qPayment->whereIn('p.warehouse_id', $warehouse);
                } elseif ($authWarehouse !== '') {
                    $qPayment->where('p.warehouse_id', $authWarehouse);
                }
                if ($department !== []) {
                    $qPayment->whereIn('p.department_id', $department);
                } elseif ($authDepartment !== '') {
                    $qPayment->where('p.department_id', $authDepartment);
                }
            });
        });
    }

    public function cleanupExpired(int $retentionDays): int
    {
        $threshold = date('Y-m-d H:i:s', strtotime('-' . $retentionDays . ' days'));
        return Db::name('attachments')->where('created_at', '<', $threshold)->delete();
    }

    public function expiredAttachments(int $retentionDays, array $scopes = [], array $authUser = []): array
    {
        $threshold = date('Y-m-d H:i:s', strtotime('-' . $retentionDays . ' days'));
        $query = Db::name('attachments')->alias('a')
            ->leftJoin('bookings b', "a.owner_type='booking' AND b.id = a.owner_id")
            ->leftJoin('payments p', "a.owner_type='payment' AND p.id = a.owner_id")
            ->where('a.created_at', '<', $threshold)
            ->field('a.id,a.storage_path,a.owner_type,a.owner_id');

        if ((string) ($authUser['role'] ?? '') !== 'admin') {
            $this->applyAttachmentScopeConditions($query, $scopes, $authUser);
        }

        return $query->select()->toArray();
    }

    public function deleteAttachment(int $id): bool
    {
        return Db::name('attachments')->where('id', $id)->delete() > 0;
    }
}

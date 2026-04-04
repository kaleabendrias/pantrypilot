<?php
declare(strict_types=1);

namespace app\infrastructure\notification;

use think\facade\Db;

final class LocalNotificationAdapter
{
    public function queue(string $eventType, string $channel, array $payload, ?string $storeId = null, ?string $warehouseId = null, ?string $departmentId = null): int
    {
        return (int) Db::name('message_events')->insertGetId([
            'event_type' => $eventType,
            'channel' => $channel,
            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'state' => 'queued',
            'store_id' => $storeId,
            'warehouse_id' => $warehouseId,
            'department_id' => $departmentId,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }
}

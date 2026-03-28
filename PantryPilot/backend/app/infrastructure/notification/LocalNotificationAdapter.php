<?php
declare(strict_types=1);

namespace app\infrastructure\notification;

use think\facade\Db;

final class LocalNotificationAdapter
{
    public function queue(string $eventType, string $channel, array $payload): int
    {
        return (int) Db::name('message_events')->insertGetId([
            'event_type' => $eventType,
            'channel' => $channel,
            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'state' => 'queued',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }
}

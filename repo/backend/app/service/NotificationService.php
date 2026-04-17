<?php
declare(strict_types=1);

namespace app\service;

use app\exception\ForbiddenException;
use app\infrastructure\time\ClockInterface;
use app\infrastructure\notification\LocalNotificationAdapter;
use think\facade\Config;
use think\facade\Db;

final class NotificationService
{
    public function __construct(
        private readonly LocalNotificationAdapter $notificationAdapter,
        private readonly ClockInterface $clock
    )
    {
    }

    public function enqueue(array $payload): array
    {
        $id = $this->notificationAdapter->queue(
            $payload['event_type'] ?? 'generic',
            $payload['channel'] ?? 'kiosk',
            $payload['payload'] ?? [],
            $payload['store_id'] ?? null,
            $payload['warehouse_id'] ?? null,
            $payload['department_id'] ?? null
        );

        return ['id' => $id];
    }

    public function events(array $scopes = [], array $authUser = []): array
    {
        if (ScopeHelper::isGlobalAdmin($authUser)) {
            return Db::name('message_events')->order('id', 'desc')->limit(200)->select()->toArray();
        }
        $query = Db::name('message_events')->alias('me');
        $store = $scopes['store'] ?? [];
        $warehouse = $scopes['warehouse'] ?? [];
        $department = $scopes['department'] ?? [];
        // NULL scope rows are global/cross-tenant events visible only to admins.
        // All non-admin queries must use strict equality — never OR-with-NULL.
        if ($store !== []) {
            $query->whereIn('me.store_id', $store);
        } elseif (!empty($authUser['store_id'])) {
            $query->where('me.store_id', (string) $authUser['store_id']);
        } else {
            $query->whereNotNull('me.store_id');
        }
        if ($warehouse !== []) {
            $query->whereIn('me.warehouse_id', $warehouse);
        } elseif (!empty($authUser['warehouse_id'])) {
            $query->where('me.warehouse_id', (string) $authUser['warehouse_id']);
        } else {
            $query->whereNotNull('me.warehouse_id');
        }
        if ($department !== []) {
            $query->whereIn('me.department_id', $department);
        } elseif (!empty($authUser['department_id'])) {
            $query->where('me.department_id', (string) $authUser['department_id']);
        } else {
            $query->whereNotNull('me.department_id');
        }
        return $query->order('id', 'desc')->limit(200)->select()->toArray();
    }

    public function setOptOut(int $userId, bool $optOut): array
    {
        $optOutInt = $optOut ? 1 : 0;
        $now = $this->nowString();

        try {
            Db::name('user_message_preferences')->insert([
                'user_id'           => $userId,
                'marketing_opt_out' => $optOutInt,
                'created_at'        => $now,
                'updated_at'        => $now,
            ]);
        } catch (\Throwable) {
            Db::name('user_message_preferences')
                ->where('user_id', $userId)
                ->update(['marketing_opt_out' => $optOutInt, 'updated_at' => $now]);
        }

        return ['user_id' => $userId, 'marketing_opt_out' => $optOut];
    }

    public function sendMessage(array $payload, array $scopes = [], array $authUser = []): array
    {
        $userId = (int) ($payload['user_id'] ?? 0);
        if ($userId < 1) {
            throw new \InvalidArgumentException('user_id is required');
        }

        $recipient = Db::name('users')->where('id', $userId)->field('id,store_id,warehouse_id,department_id')->find();
        if (!$recipient) {
            throw new \RuntimeException('Recipient not found');
        }

        if (!ScopeHelper::isGlobalAdmin($authUser) && !$this->recipientInScope($recipient, $scopes, $authUser)) {
            throw new ForbiddenException('Forbidden');
        }

        $isMarketing = (bool) ($payload['is_marketing'] ?? false);
        if ($isMarketing) {
            $pref = Db::name('user_message_preferences')->where('user_id', $userId)->find();
            if ($pref && (int) $pref['marketing_opt_out'] === 1) {
                throw new \RuntimeException('User opted out of marketing messages');
            }

            $cap = (int) Config::get('security.messaging.daily_marketing_cap', 2);
            $countToday = Db::name('message_center')
                ->where('user_id', $userId)
                ->where('is_marketing', 1)
                ->whereDay('sent_at', $this->todayString())
                ->count();
            if ($countToday >= $cap) {
                throw new \RuntimeException('Daily marketing cap reached');
            }

            $start = (string) Config::get('security.messaging.quiet_hours_start', '21:00');
            $end = (string) Config::get('security.messaging.quiet_hours_end', '08:00');
            $now = $this->hourMinute();
            if ($now >= $start || $now < $end) {
                throw new \RuntimeException('Quiet hours active (21:00-08:00)');
            }
        }

        $nowString = $this->nowString();

        $id = (int) Db::name('message_center')->insertGetId([
            'user_id' => $userId,
            'template_id' => $payload['template_id'] ?? null,
            'title' => (string) ($payload['title'] ?? 'Message'),
            'body' => (string) ($payload['body'] ?? ''),
            'is_marketing' => $isMarketing ? 1 : 0,
            'sent_at' => $nowString,
            'created_at' => $nowString,
        ]);

        return ['id' => $id];
    }

    public function recipientInScope(array $recipient, array $scopes = [], array $authUser = []): bool
    {
        $store = array_map('strval', $scopes['store'] ?? []);
        $warehouse = array_map('strval', $scopes['warehouse'] ?? []);
        $department = array_map('strval', $scopes['department'] ?? []);

        $recipientStore = (string) ($recipient['store_id'] ?? '');
        $recipientWarehouse = (string) ($recipient['warehouse_id'] ?? '');
        $recipientDepartment = (string) ($recipient['department_id'] ?? '');

        if ($store !== [] && !in_array($recipientStore, $store, true)) {
            return false;
        }
        if ($warehouse !== [] && !in_array($recipientWarehouse, $warehouse, true)) {
            return false;
        }
        if ($department !== [] && !in_array($recipientDepartment, $department, true)) {
            return false;
        }

        if ($store === [] && !empty($authUser['store_id']) && $recipientStore !== (string) $authUser['store_id']) {
            return false;
        }
        if ($warehouse === [] && !empty($authUser['warehouse_id']) && $recipientWarehouse !== (string) $authUser['warehouse_id']) {
            return false;
        }
        if ($department === [] && !empty($authUser['department_id']) && $recipientDepartment !== (string) $authUser['department_id']) {
            return false;
        }

        return true;
    }

    public function inbox(int $userId): array
    {
        return Db::name('message_center')->where('user_id', $userId)->order('id', 'desc')->select()->toArray();
    }

    public function markRead(int $messageId): array
    {
        Db::name('message_center')->where('id', $messageId)->update(['read_at' => date('Y-m-d H:i:s')]);
        return ['id' => $messageId, 'read' => true];
    }

    public function markClick(int $messageId): array
    {
        Db::name('message_center')->where('id', $messageId)->update(['clicked_at' => date('Y-m-d H:i:s')]);
        return ['id' => $messageId, 'clicked' => true];
    }

    public function markReadForUser(int $messageId, int $userId): array
    {
        if (!$this->messageBelongsToUser($messageId, $userId)) {
            throw new \RuntimeException('Forbidden');
        }
        return $this->markRead($messageId);
    }

    public function markClickForUser(int $messageId, int $userId): array
    {
        if (!$this->messageBelongsToUser($messageId, $userId)) {
            throw new \RuntimeException('Forbidden');
        }
        return $this->markClick($messageId);
    }

    public function analytics(array $scopes = [], array $authUser = []): array
    {
        $sentQ = Db::name('message_center')->alias('m')->join('users u', 'u.id = m.user_id');
        $readQ = Db::name('message_center')->alias('m')->join('users u', 'u.id = m.user_id')->whereNotNull('m.read_at');
        $clickedQ = Db::name('message_center')->alias('m')->join('users u', 'u.id = m.user_id')->whereNotNull('m.clicked_at');

        if (!ScopeHelper::isGlobalAdmin($authUser)) {
            $this->applyUserDataScopes($sentQ, 'u', $scopes, $authUser);
            $this->applyUserDataScopes($readQ, 'u', $scopes, $authUser);
            $this->applyUserDataScopes($clickedQ, 'u', $scopes, $authUser);
        }

        $sent = (int) $sentQ->count();
        $read = (int) $readQ->count();
        $clicked = (int) $clickedQ->count();
        return [
            'sent' => $sent,
            'read_rate' => $sent > 0 ? round(($read / $sent) * 100, 2) : 0,
            'click_rate' => $sent > 0 ? round(($clicked / $sent) * 100, 2) : 0,
        ];
    }

    private function applyUserDataScopes($query, string $alias, array $scopes, array $authUser): void
    {
        $store = $scopes['store'] ?? [];
        $warehouse = $scopes['warehouse'] ?? [];
        $department = $scopes['department'] ?? [];

        if ($store !== []) {
            $query->whereIn($alias . '.store_id', $store);
        } elseif (!empty($authUser['store_id'])) {
            $query->where($alias . '.store_id', (string) $authUser['store_id']);
        }

        if ($warehouse !== []) {
            $query->whereIn($alias . '.warehouse_id', $warehouse);
        } elseif (!empty($authUser['warehouse_id'])) {
            $query->where($alias . '.warehouse_id', (string) $authUser['warehouse_id']);
        }

        if ($department !== []) {
            $query->whereIn($alias . '.department_id', $department);
        } elseif (!empty($authUser['department_id'])) {
            $query->where($alias . '.department_id', (string) $authUser['department_id']);
        }
    }

    private function messageBelongsToUser(int $messageId, int $userId): bool
    {
        if ($messageId < 1 || $userId < 1) {
            return false;
        }
        $owner = Db::name('message_center')->where('id', $messageId)->value('user_id');
        return (int) $owner === $userId;
    }

    private function nowString(): string
    {
        return $this->clock->now()->format('Y-m-d H:i:s');
    }

    private function todayString(): string
    {
        return $this->clock->now()->format('Y-m-d');
    }

    private function hourMinute(): string
    {
        return $this->clock->now()->format('H:i');
    }
}

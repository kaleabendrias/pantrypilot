<?php
declare(strict_types=1);

namespace app\repository;

use think\facade\Db;

final class TagRepository
{
    public function list(): array
    {
        return Db::name('tags')->order('name')->select()->toArray();
    }

    public function create(array $payload): int
    {
        return (int) Db::name('tags')->insertGetId([
            'name' => $payload['name'],
            'color' => $payload['color'] ?? '#3a7afe',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }
}

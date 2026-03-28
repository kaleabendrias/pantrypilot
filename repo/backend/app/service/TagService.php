<?php
declare(strict_types=1);

namespace app\service;

use app\repository\TagRepository;

final class TagService
{
    public function __construct(private readonly TagRepository $tagRepository)
    {
    }

    public function list(): array
    {
        return $this->tagRepository->list();
    }

    public function create(array $payload): array
    {
        $id = $this->tagRepository->create($payload);
        return ['id' => $id];
    }
}

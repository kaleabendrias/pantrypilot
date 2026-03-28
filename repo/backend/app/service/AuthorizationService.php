<?php
declare(strict_types=1);

namespace app\service;

use app\repository\AuthorizationRepository;

final class AuthorizationService
{
    public function __construct(private readonly AuthorizationRepository $authorizationRepository)
    {
    }

    public function can(int $userId, string $resourceCode, string $permissionCode): bool
    {
        return $this->authorizationRepository->hasPermission($userId, $resourceCode, $permissionCode);
    }

    public function dataScopes(int $userId): array
    {
        return $this->authorizationRepository->scopesByUser($userId);
    }
}

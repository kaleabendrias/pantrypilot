<?php
declare(strict_types=1);

namespace app\service;

use app\repository\OperationsRepository;

final class OperationsService
{
    public function __construct(private readonly OperationsRepository $operationsRepository)
    {
    }

    public function campaigns(array $scopes = [], array $authUser = []): array
    {
        return $this->operationsRepository->listCampaigns($scopes, $authUser);
    }

    public function createCampaign(array $payload): array
    {
        $id = $this->operationsRepository->createCampaign($payload);
        return ['id' => $id];
    }

    public function updateHomepageModule(string $moduleKey, array $payload, int $updatedBy): array
    {
        $allowed = ['carousel_banners', 'campaign_slots', 'hot_rankings'];
        if (!in_array($moduleKey, $allowed, true)) {
            throw new \InvalidArgumentException('Unsupported module key');
        }

        $this->operationsRepository->upsertHomepageModule($moduleKey, $payload, $updatedBy);
        return ['module_key' => $moduleKey, 'saved' => true];
    }

    public function homepageModules(array $scopes = [], array $authUser = []): array
    {
        return $this->operationsRepository->homepageModules($scopes, $authUser);
    }

    public function saveMessageTemplate(array $payload): array
    {
        if (empty($payload['template_code']) || empty($payload['title']) || empty($payload['content'])) {
            throw new \InvalidArgumentException('template_code, title and content are required');
        }
        $id = $this->operationsRepository->upsertMessageTemplate($payload);
        return ['id' => $id];
    }

    public function messageTemplates(array $scopes = [], array $authUser = []): array
    {
        return $this->operationsRepository->listMessageTemplates($scopes, $authUser);
    }

    public function dashboardMetrics(array $scopes = [], array $authUser = []): array
    {
        return $this->operationsRepository->dashboardMetrics($scopes, $authUser);
    }
}

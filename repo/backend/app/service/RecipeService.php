<?php
declare(strict_types=1);

namespace app\service;

use app\domain\recipes\RecipeDomainPolicy;
use app\repository\RecipeRepository;

final class RecipeService
{
    public function __construct(
        private readonly RecipeRepository $recipeRepository,
        private readonly RecipeDomainPolicy $recipeDomainPolicy
    )
    {
    }

    public function list(array $filters = []): array
    {
        $scopes = $filters['_scopes'] ?? [];
        $authUser = $filters['_auth_user'] ?? [];
        $page = (int) ($filters['page'] ?? 1);
        $perPage = (int) ($filters['per_page'] ?? 20);
        if ($page < 1) {
            $page = 1;
        }
        if ($perPage < 1) {
            $perPage = 20;
        }
        unset($filters['_scopes'], $filters['_auth_user']);
        return $this->recipeRepository->list($filters, $scopes, $authUser, $page, $perPage);
    }

    public function create(array $payload): array
    {
        if (($payload['status'] ?? 'draft') === 'published') {
            $this->recipeDomainPolicy->validateDraftToPublished($payload);
        }

        $payload['code'] = $payload['code'] ?? 'RCP-' . strtoupper(bin2hex(random_bytes(3)));
        $id = $this->recipeRepository->create($payload);
        return ['id' => $id, 'code' => $payload['code']];
    }

    public function search(array $filters = []): array
    {
        $limit = (int) ($filters['limit'] ?? 20);
        if ($limit < 1) {
            $limit = 20;
        }

        $criteria = [
            'q' => trim((string) ($filters['q'] ?? '')),
            'prep_under' => (int) ($filters['prep_under'] ?? 0),
            'step_count_max' => (int) ($filters['step_count_max'] ?? 0),
            'difficulty' => trim((string) ($filters['difficulty'] ?? '')),
            'max_calories' => (int) ($filters['max_calories'] ?? 0),
            'max_budget' => (float) ($filters['max_budget'] ?? 0),
            'rank_mode' => (string) ($filters['rank_mode'] ?? 'popular'),
            'limit' => $limit,
        ];

        if ($criteria['prep_under'] !== 0 && $criteria['prep_under'] < 1) {
            throw new \InvalidArgumentException('prep_under must be positive');
        }

        $ingredientTerms = $this->splitTerms((string) ($filters['ingredient'] ?? ''));
        $keywordTerms = $this->splitTerms($criteria['q']);
        $criteria['ingredient_terms'] = $this->recipeRepository->resolveIngredientTerms(array_merge($ingredientTerms, $keywordTerms));
        $criteria['cookware_terms'] = $this->splitTerms((string) ($filters['cookware'] ?? ''));
        $criteria['exclude_allergens'] = $this->splitTerms((string) ($filters['exclude_allergens'] ?? ''));
        $criteria['tag_terms'] = $this->splitTerms((string) ($filters['tags'] ?? ''));

        $scopes = $filters['_scopes'] ?? [];
        $authUser = $filters['_auth_user'] ?? [];

        return $this->recipeRepository->search($criteria, $scopes, $authUser);
    }

    private function splitTerms(string $terms): array
    {
        if ($terms === '') {
            return [];
        }

        $parts = preg_split('/[\s,]+/', strtolower(trim($terms))) ?: [];
        $parts = array_map(static fn (string $v): string => trim($v), $parts);
        return array_values(array_unique(array_filter($parts, static fn (string $v): bool => $v !== '')));
    }
}

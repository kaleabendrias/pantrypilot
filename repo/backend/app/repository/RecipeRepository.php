<?php
declare(strict_types=1);

namespace app\repository;

use think\facade\Db;

final class RecipeRepository
{
    public function list(array $filters = [], array $scopes = [], array $authUser = [], int $page = 1, int $perPage = 20): array
    {
        $query = Db::name('recipes')->alias('r')->leftJoin('users u', 'u.id = r.created_by')
            ->field('r.*,u.display_name as created_by_name');

        if (!empty($filters['status'])) {
            $query->where('r.status', $filters['status']);
        }

        $this->applyDataScopes($query, $scopes, $authUser);
        $page = max(1, $page);
        $perPage = max(1, min($perPage, 200));
        $total = (int) (clone $query)->count();
        $items = $query->order('r.id', 'desc')->page($page, $perPage)->select()->toArray();

        return [
            'items' => $items,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => (int) ceil($total / $perPage),
            ],
        ];
    }

    public function create(array $data): int
    {
        $recipeId = (int) Db::name('recipes')->insertGetId([
            'code' => $data['code'],
            'name' => $data['name'],
            'description' => $data['description'] ?? '',
            'prep_minutes' => (int) ($data['prep_minutes'] ?? 0),
            'step_count' => (int) ($data['step_count'] ?? 0),
            'difficulty' => $data['difficulty'] ?? 'easy',
            'calories' => (int) ($data['calories'] ?? 0),
            'estimated_cost' => (float) ($data['estimated_cost'] ?? 0),
            'popularity_score' => (int) ($data['popularity_score'] ?? 0),
            'servings' => (int) ($data['servings'] ?? 1),
            'status' => $data['status'] ?? 'draft',
            'created_by' => $data['created_by'] ?? null,
            'store_id' => $data['store_id'] ?? null,
            'warehouse_id' => $data['warehouse_id'] ?? null,
            'department_id' => $data['department_id'] ?? null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $ingredients = $data['ingredients'] ?? [];
        foreach ($ingredients as $ingredient) {
            $norm = $this->normalizeTerm((string) $ingredient);
            if ($norm === '') {
                continue;
            }

            Db::name('recipe_ingredients')->insert([
                'recipe_id' => $recipeId,
                'ingredient_name' => (string) $ingredient,
                'ingredient_name_norm' => $norm,
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            Db::name('ingredients')->insert([
                'display_name' => (string) $ingredient,
                'normalized_name' => $norm,
                'created_at' => date('Y-m-d H:i:s'),
            ], true);
        }

        foreach (($data['cookware'] ?? []) as $cookware) {
            $norm = $this->normalizeTerm((string) $cookware);
            if ($norm === '') {
                continue;
            }
            Db::name('recipe_cookware')->insert([
                'recipe_id' => $recipeId,
                'cookware_norm' => $norm,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }

        foreach (($data['allergens'] ?? []) as $allergen) {
            $norm = $this->normalizeTerm((string) $allergen);
            if ($norm === '') {
                continue;
            }
            Db::name('recipe_allergens')->insert([
                'recipe_id' => $recipeId,
                'allergen_norm' => $norm,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }

        return $recipeId;
    }

    public function search(array $criteria, array $scopes = [], array $authUser = []): array
    {
        $query = Db::name('recipes')->alias('r')
            ->leftJoin('users u', 'u.id = r.created_by')
            ->field('r.*,u.display_name as created_by_name')
            ->group('r.id');

        $this->applyDataScopes($query, $scopes, $authUser);

        $statusFilter = (string) ($criteria['status'] ?? '');
        if ($statusFilter !== '') {
            $query->where('r.status', $statusFilter);
        } elseif (\app\service\ScopeHelper::isGlobalAdmin($authUser) === false) {
            $query->where('r.status', 'published');
        }

        if (!empty($criteria['ingredient_terms'])) {
            $query->join('recipe_ingredients ri', 'ri.recipe_id = r.id')
                ->whereIn('ri.ingredient_name_norm', $criteria['ingredient_terms']);
        }

        if (!empty($criteria['q'])) {
            $keyword = '%' . trim((string) $criteria['q']) . '%';
            $query->where(function ($subQuery) use ($keyword) {
                $subQuery->whereLike('r.name', $keyword)->whereOr('r.description', 'like', $keyword);
            });
        }

        if (!empty($criteria['prep_under'])) {
            $query->where('r.prep_minutes', '<', (int) $criteria['prep_under']);
        }
        if (!empty($criteria['step_count_max'])) {
            $query->where('r.step_count', '<=', (int) $criteria['step_count_max']);
        }
        if (!empty($criteria['difficulty'])) {
            $query->where('r.difficulty', (string) $criteria['difficulty']);
        }
        if (!empty($criteria['max_calories'])) {
            $query->where('r.calories', '<=', (int) $criteria['max_calories']);
        }
        if (!empty($criteria['max_budget'])) {
            $query->where('r.estimated_cost', '<=', (float) $criteria['max_budget']);
        }

        if (!empty($criteria['cookware_terms'])) {
            $query->join('recipe_cookware rc', 'rc.recipe_id = r.id')
                ->whereIn('rc.cookware_norm', $criteria['cookware_terms']);
        }

        if (!empty($criteria['exclude_allergens'])) {
            $blockedRecipeIds = Db::name('recipe_allergens')
                ->whereIn('allergen_norm', $criteria['exclude_allergens'])
                ->column('recipe_id');
            if ($blockedRecipeIds !== []) {
                $query->whereNotIn('r.id', $blockedRecipeIds);
            }
        }

        if (!empty($criteria['tag_terms'])) {
            $query->join('recipe_tags rt', 'rt.recipe_id = r.id')
                ->join('tags t', 't.id = rt.tag_id')
                ->whereIn('t.name', $criteria['tag_terms']);
        }

        $rank = (string) ($criteria['rank_mode'] ?? 'popular');
        if ($rank === 'time-saving') {
            $query->order('r.prep_minutes', 'asc')->order('r.step_count', 'asc');
        } elseif ($rank === 'budget') {
            $query->order('r.estimated_cost', 'asc')->order('r.prep_minutes', 'asc');
        } elseif ($rank === 'low-calorie') {
            $query->order('r.calories', 'asc')->order('r.prep_minutes', 'asc');
        } else {
            $query->order('r.popularity_score', 'desc')->order('r.id', 'desc');
        }

        $limit = max(1, min((int) ($criteria['limit'] ?? 20), 100));
        return $query->limit($limit)->select()->toArray();
    }

    public function resolveIngredientTerms(array $terms): array
    {
        $terms = array_values(array_unique(array_filter(array_map([$this, 'normalizeTerm'], $terms))));
        if ($terms === []) {
            return [];
        }

        $rows = Db::name('search_synonyms')->whereIn('synonym', $terms)->select()->toArray();
        $map = [];
        foreach ($rows as $row) {
            $map[(string) $row['synonym']] = (string) $row['canonical_term'];
        }

        $resolved = [];
        foreach ($terms as $term) {
            $resolved[] = $map[$term] ?? $term;
        }

        $dictionary = Db::name('ingredients')->limit(1000)->column('normalized_name');
        foreach ($resolved as $term) {
            foreach ($dictionary as $candidate) {
                if ($candidate === $term) {
                    continue;
                }
                if (abs(strlen($candidate) - strlen($term)) > 2) {
                    continue;
                }
                if (levenshtein($candidate, $term) <= 2) {
                    $resolved[] = $candidate;
                }
            }
        }

        return array_values(array_unique($resolved));
    }

    private function normalizeTerm(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9\s-]/', '', $value) ?? '';
        return preg_replace('/\s+/', ' ', $value) ?? '';
    }

    private function applyDataScopes($query, array $scopes, array $authUser): void
    {
        $store = $scopes['store'] ?? [];
        $warehouse = $scopes['warehouse'] ?? [];
        $department = $scopes['department'] ?? [];

        if ($store !== []) {
            $query->whereIn('r.store_id', $store);
        } elseif (!empty($authUser['store_id'])) {
            $query->where('r.store_id', $authUser['store_id']);
        }

        if ($warehouse !== []) {
            $query->whereIn('r.warehouse_id', $warehouse);
        } elseif (!empty($authUser['warehouse_id'])) {
            $query->where('r.warehouse_id', $authUser['warehouse_id']);
        }

        if ($department !== []) {
            $query->whereIn('r.department_id', $department);
        } elseif (!empty($authUser['department_id'])) {
            $query->where('r.department_id', $authUser['department_id']);
        }
    }
}

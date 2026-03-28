<?php
declare(strict_types=1);

namespace app\controller\api\v1;

use app\BaseController;
use app\common\JsonResponse;
use app\service\RecipeService;

final class RecipeController extends BaseController
{
    public function __construct(\think\App $app, private readonly RecipeService $recipeService)
    {
        parent::__construct($app);
    }

    public function index()
    {
        $filters = $this->collectFilters();
        $filters['_scopes'] = $this->request->middleware('data_scopes', []);
        $filters['_auth_user'] = $this->request->middleware('auth_user', []);
        return JsonResponse::success($this->recipeService->list($filters));
    }

    public function search()
    {
        try {
            $filters = $this->collectFilters();
            $filters['_scopes'] = $this->request->middleware('data_scopes', []);
            $filters['_auth_user'] = $this->request->middleware('auth_user', []);
            return JsonResponse::success(['items' => $this->recipeService->search($filters)]);
        } catch (\Throwable $e) {
            return JsonResponse::error($e->getMessage(), 422);
        }
    }

    public function create()
    {
        try {
            $payload = $this->request->post();
            $authUser = $this->request->middleware('auth_user', []);
            $payload['store_id'] = $authUser['store_id'] ?? null;
            $payload['warehouse_id'] = $authUser['warehouse_id'] ?? null;
            $payload['department_id'] = $authUser['department_id'] ?? null;
            return JsonResponse::success($this->recipeService->create($payload), 'Recipe created', 201);
        } catch (\Throwable $e) {
            return JsonResponse::error($e->getMessage(), 422);
        }
    }

    private function collectFilters(): array
    {
        $query = [];
        parse_str((string) ($_SERVER['QUERY_STRING'] ?? ''), $query);
        $pick = function (string $key) use ($query): string {
            if (array_key_exists($key, $query)) {
                return trim((string) $query[$key]);
            }
            return trim((string) $this->request->param($key, ''));
        };

        return [
            'status' => $pick('status'),
            'q' => $pick('q'),
            'ingredient' => $pick('ingredient'),
            'cookware' => $pick('cookware'),
            'exclude_allergens' => $pick('exclude_allergens'),
            'tags' => $pick('tags'),
            'prep_under' => $pick('prep_under'),
            'step_count_max' => $pick('step_count_max'),
            'difficulty' => $pick('difficulty'),
            'max_calories' => $pick('max_calories'),
            'max_budget' => $pick('max_budget'),
            'rank_mode' => $pick('rank_mode'),
            'limit' => $pick('limit'),
            'page' => $pick('page'),
            'per_page' => $pick('per_page'),
        ];
    }
}

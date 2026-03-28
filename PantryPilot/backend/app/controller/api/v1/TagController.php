<?php
declare(strict_types=1);

namespace app\controller\api\v1;

use app\BaseController;
use app\common\JsonResponse;
use app\service\TagService;

final class TagController extends BaseController
{
    public function __construct(\think\App $app, private readonly TagService $tagService)
    {
        parent::__construct($app);
    }

    public function index()
    {
        return JsonResponse::success(['items' => $this->tagService->list()]);
    }

    public function create()
    {
        return JsonResponse::success($this->tagService->create($this->request->post()), 'Tag created', 201);
    }
}

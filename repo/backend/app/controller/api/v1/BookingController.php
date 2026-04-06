<?php
declare(strict_types=1);

namespace app\controller\api\v1;

use app\BaseController;
use app\common\JsonResponse;
use app\service\BookingService;

final class BookingController extends BaseController
{
    public function __construct(\think\App $app, private readonly BookingService $bookingService)
    {
        parent::__construct($app);
    }

    public function index()
    {
        $page = (int) $this->request->get('page', 1);
        $perPage = (int) $this->request->get('per_page', 20);
        return JsonResponse::success([
            ...$this->bookingService->list(
                $this->request->middleware('data_scopes', []),
                $this->request->middleware('auth_user', []),
                $page,
                $perPage
            ),
        ]);
    }

    public function create()
    {
        try {
            $payload = $this->request->post();
            $authUser = $this->request->middleware('auth_user', []);
            $scopes = $this->request->middleware('data_scopes', []);
            $payload['user_id'] = (int) ($authUser['id'] ?? 0);
            if ((int) $payload['user_id'] < 1) {
                return JsonResponse::error('Unauthorized', 401);
            }
            $recipeId = (int) ($payload['recipe_id'] ?? 0);
            if ($recipeId < 1) {
                return JsonResponse::error('recipe_id is required', 422);
            }
            if (!$this->bookingService->recipeExists($recipeId)) {
                return JsonResponse::error('Recipe not found', 404);
            }
            if (!$this->bookingService->recipeBookable($recipeId)) {
                return JsonResponse::error('Recipe is not available for booking', 422);
            }
            if (!$this->bookingService->canAccessRecipe($recipeId, $scopes, $authUser)) {
                return JsonResponse::error('Forbidden', 403);
            }
            $pickupPointId = (int) ($payload['pickup_point_id'] ?? 0);
            if ($pickupPointId < 1) {
                return JsonResponse::error('pickup_point_id is required', 422);
            }
            $point = $this->bookingService->pickupPointById($pickupPointId);
            if (!$point) {
                return JsonResponse::error('Pickup point not found', 404);
            }
            if ((int) ($point['active'] ?? 0) !== 1) {
                return JsonResponse::error('Pickup point is not active', 422);
            }
            if (!$this->bookingService->pickupPointInScope($pickupPointId, $scopes, $authUser)) {
                return JsonResponse::error('Pickup point is not accessible', 403);
            }
            $payload['store_id'] = $point['store_id'] ?? $authUser['store_id'] ?? null;
            $payload['warehouse_id'] = $point['warehouse_id'] ?? $authUser['warehouse_id'] ?? null;
            $payload['department_id'] = $point['department_id'] ?? $authUser['department_id'] ?? null;
            return JsonResponse::success($this->bookingService->create($payload), 'Booking created', 201);
        } catch (\Throwable $e) {
            return $this->respondException($e, 422);
        }
    }

    public function pickupPoints()
    {
        $scopes = $this->request->middleware('data_scopes', []);
        $authUser = $this->request->middleware('auth_user', []);
        return JsonResponse::success(['items' => $this->bookingService->pickupPoints($scopes, $authUser)]);
    }

    public function recipeDetail(int $recipeId)
    {
        try {
            $scopes = $this->request->middleware('data_scopes', []);
            $authUser = $this->request->middleware('auth_user', []);
            if (!$this->bookingService->recipeExists($recipeId)) {
                return JsonResponse::error('Recipe not found', 404);
            }
            if (!$this->bookingService->canAccessRecipe($recipeId, $scopes, $authUser)) {
                return JsonResponse::error('Forbidden', 403);
            }
            return JsonResponse::success($this->bookingService->recipeDetail($recipeId));
        } catch (\Throwable $e) {
            return $this->respondException($e, 404);
        }
    }

    public function slotCapacity()
    {
        $pickupPointId = (int) $this->request->get('pickup_point_id', 0);
        $slotStart = (string) $this->request->get('slot_start', '');
        $slotEnd = (string) $this->request->get('slot_end', '');
        if ($pickupPointId < 1 || $slotStart === '' || $slotEnd === '') {
            return JsonResponse::error('pickup_point_id, slot_start and slot_end are required', 422);
        }

        $scopes = $this->request->middleware('data_scopes', []);
        $authUser = $this->request->middleware('auth_user', []);
        if (!$this->bookingService->pickupPointInScope($pickupPointId, $scopes, $authUser)) {
            return JsonResponse::error('Forbidden', 403);
        }

        return JsonResponse::success($this->bookingService->slotCapacity($pickupPointId, $slotStart, $slotEnd));
    }

    public function todayPickups()
    {
        return JsonResponse::success([
            'items' => $this->bookingService->todaysPickups(
                $this->request->middleware('data_scopes', []),
                $this->request->middleware('auth_user', [])
            ),
        ]);
    }

    public function checkIn()
    {
        $bookingId = (int) $this->request->post('booking_id', 0);
        $staffId = (int) (($this->request->middleware('auth_user', [])['id'] ?? 0));
        $scopes = $this->request->middleware('data_scopes', []);
        $authUser = $this->request->middleware('auth_user', []);
        if ($bookingId < 1) {
            return JsonResponse::error('booking_id is required', 422);
        }
        if (!$this->bookingService->bookingExists($bookingId)) {
            return JsonResponse::error('Booking not found', 404);
        }
        if (!$this->bookingService->canAccessBooking($bookingId, $scopes, $authUser)) {
            return JsonResponse::error('Forbidden', 403);
        }

        try {
            return JsonResponse::success(['checked_in' => $this->bookingService->checkIn($bookingId, $staffId)]);
        } catch (\RuntimeException $e) {
            return $this->respondException($e, 422);
        }
    }

    public function runNoShowSweep()
    {
        $scopes = $this->request->middleware('data_scopes', []);
        $authUser = $this->request->middleware('auth_user', []);
        return JsonResponse::success($this->bookingService->autoClassifyNoShow($scopes, $authUser), 'No-show sweep completed');
    }

    public function dispatchNote(int $bookingId)
    {
        try {
            $scopes = $this->request->middleware('data_scopes', []);
            $authUser = $this->request->middleware('auth_user', []);
            if (!$this->bookingService->bookingExists($bookingId)) {
                return JsonResponse::error('Booking not found', 404);
            }
            if (!$this->bookingService->canAccessBooking($bookingId, $scopes, $authUser)) {
                return JsonResponse::error('Forbidden', 403);
            }
            return JsonResponse::success($this->bookingService->printableDispatchNote($bookingId));
        } catch (\Throwable $e) {
            return $this->respondException($e, 404);
        }
    }
}

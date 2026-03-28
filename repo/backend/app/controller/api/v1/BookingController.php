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
            $payload['user_id'] = (int) ($authUser['id'] ?? 0);
            $payload['store_id'] = $authUser['store_id'] ?? null;
            $payload['warehouse_id'] = $authUser['warehouse_id'] ?? null;
            $payload['department_id'] = $authUser['department_id'] ?? null;
            if ((int) $payload['user_id'] < 1) {
                return JsonResponse::error('Unauthorized', 401);
            }
            return JsonResponse::success($this->bookingService->create($payload), 'Booking created', 201);
        } catch (\Throwable $e) {
            return $this->respondException($e, 422);
        }
    }

    public function pickupPoints()
    {
        return JsonResponse::success(['items' => $this->bookingService->pickupPoints()]);
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
            return JsonResponse::error($e->getMessage(), 404);
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

        return JsonResponse::success(['checked_in' => $this->bookingService->checkIn($bookingId, $staffId)]);
    }

    public function runNoShowSweep()
    {
        return JsonResponse::success($this->bookingService->autoClassifyNoShow(), 'No-show sweep completed');
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
            return JsonResponse::error($e->getMessage(), 404);
        }
    }
}

<?php
declare(strict_types=1);

use app\controller\api\v1\AdministrationController;
use app\controller\api\v1\BookingController;
use app\controller\api\v1\FileController;
use app\controller\api\v1\IdentityController;
use app\controller\api\v1\NotificationController;
use app\controller\api\v1\OperationsController;
use app\controller\api\v1\PaymentController;
use app\controller\api\v1\RecipeController;
use app\controller\api\v1\ReportingController;
use app\controller\api\v1\TagController;
use think\facade\Route;

Route::get('/', function () {
    return json(['service' => 'PantryPilot API', 'status' => 'running']);
});

Route::group('api/v1', function () {
    Route::post('identity/login', [IdentityController::class, 'login']);
    Route::post('identity/register', [IdentityController::class, 'register']);

    Route::get('recipes/search', [RecipeController::class, 'search']);
    Route::get('recipes', [RecipeController::class, 'index']);
    Route::post('recipes', [RecipeController::class, 'create']);
    Route::get('tags', [TagController::class, 'index']);
    Route::post('tags', [TagController::class, 'create']);

    Route::get('bookings/recipe/:recipeId', [BookingController::class, 'recipeDetail']);
    Route::get('bookings/slot-capacity', [BookingController::class, 'slotCapacity']);
    Route::get('bookings/today-pickups', [BookingController::class, 'todayPickups']);
    Route::post('bookings/check-in', [BookingController::class, 'checkIn']);
    Route::post('bookings/no-show-sweep', [BookingController::class, 'runNoShowSweep']);
    Route::get('bookings/:bookingId/dispatch-note', [BookingController::class, 'dispatchNote']);
    Route::get('pickup-points', [BookingController::class, 'pickupPoints']);
    Route::get('bookings', [BookingController::class, 'index']);
    Route::post('bookings', [BookingController::class, 'create']);

    Route::get('operations/campaigns', [OperationsController::class, 'campaigns']);
    Route::post('operations/campaigns', [OperationsController::class, 'createCampaign']);
    Route::get('operations/homepage-modules', [OperationsController::class, 'homepageModules']);
    Route::post('operations/homepage-modules', [OperationsController::class, 'updateHomepageModule']);
    Route::get('operations/message-templates', [OperationsController::class, 'messageTemplates']);
    Route::post('operations/message-templates', [OperationsController::class, 'saveMessageTemplate']);
    Route::get('operations/dashboard', [OperationsController::class, 'managerDashboard']);

    Route::post('payments/gateway/orders', [PaymentController::class, 'createGatewayOrder']);
    Route::post('payments/gateway/callback', [PaymentController::class, 'gatewayCallback']);
    Route::post('payments/gateway/auto-cancel', [PaymentController::class, 'autoCancelGatewayOrders']);
    Route::post('payments/reconcile/daily', [PaymentController::class, 'dailyReconcile']);
    Route::post('payments/reconcile/repair', [PaymentController::class, 'repairIssue']);
    Route::post('payments/reconcile/close', [PaymentController::class, 'closeBatch']);
    Route::post('payments/reconcile', [PaymentController::class, 'reconcile']);
    Route::post('payments/refund', [PaymentController::class, 'refund']);
    Route::post('payments/adjust', [PaymentController::class, 'adjust']);
    Route::get('payments', [PaymentController::class, 'index']);
    Route::post('payments', [PaymentController::class, 'create']);

    Route::get('notifications/events', [NotificationController::class, 'index']);
    Route::post('notifications/events', [NotificationController::class, 'create']);
    Route::post('notifications/preferences/opt-out', [NotificationController::class, 'setOptOut']);
    Route::post('notifications/messages/:id/read', [NotificationController::class, 'markRead']);
    Route::post('notifications/messages/:id/click', [NotificationController::class, 'markClick']);
    Route::post('notifications/messages', [NotificationController::class, 'sendMessage']);
    Route::get('notifications/inbox', [NotificationController::class, 'inbox']);
    Route::get('notifications/analytics', [NotificationController::class, 'analytics']);

    Route::post('files/upload-base64', [FileController::class, 'uploadBase64']);
    Route::get('files/:id/signed-url', [FileController::class, 'signedUrl']);
    Route::get('files/download/:id', [FileController::class, 'download']);
    Route::post('files/cleanup', [FileController::class, 'cleanup']);
    Route::get('files', [FileController::class, 'index']);

    Route::get('reporting/dashboard', [ReportingController::class, 'dashboard']);
    Route::get('reporting/anomalies', [ReportingController::class, 'anomalies']);
    Route::get('reporting/exports/bookings-csv', [ReportingController::class, 'exportBookingsCsv']);

    Route::get('admin/users', [AdministrationController::class, 'users']);
    Route::get('admin/audit-logs', [AdministrationController::class, 'auditLogs']);
    Route::post('admin/reauth', [AdministrationController::class, 'issueReauthToken']);
    Route::get('admin/roles', [AdministrationController::class, 'roles']);
    Route::post('admin/roles', [AdministrationController::class, 'createRole']);
    Route::get('admin/permissions', [AdministrationController::class, 'permissions']);
    Route::get('admin/resources', [AdministrationController::class, 'resources']);
    Route::post('admin/grants', [AdministrationController::class, 'grantRolePermissionResource']);
    Route::post('admin/user-roles', [AdministrationController::class, 'assignRoleToUser']);
    Route::post('admin/users/:userId/enable', [AdministrationController::class, 'enableUser']);
    Route::post('admin/users/:userId/disable', [AdministrationController::class, 'disableUser']);
    Route::post('admin/users/:userId/reset-password', [AdministrationController::class, 'resetUserPassword']);
    Route::post('admin/users/:userId/scopes', [AdministrationController::class, 'updateUserScopes']);
});

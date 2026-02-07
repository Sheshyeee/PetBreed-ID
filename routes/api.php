<?php

use App\Http\Controllers\Api\MobileAuthController;
use App\Http\Controllers\Api\SimulationStatusController;
use App\Http\Controllers\model\ScanResultController;
use App\Http\Controllers\NotificationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::get('/simulation-status', [SimulationStatusController::class, 'getStatus']);

// API v1 routes for mobile app
Route::prefix('v1')->group(function () {

  // ========================================
  // PUBLIC ROUTES (No authentication needed)
  // ========================================

  // Google OAuth for mobile
  Route::post('/auth/google', [MobileAuthController::class, 'mobileLogin']);

  // ========================================
  // PROTECTED ROUTES (Require authentication)
  // ========================================

  Route::middleware('auth:sanctum')->group(function () {

    // Auth endpoints
    Route::get('/auth/me', [MobileAuthController::class, 'me']);
    Route::post('/auth/logout', [MobileAuthController::class, 'logout']);

    // Analyze image (POST)
    Route::post('/analyze', [ScanResultController::class, 'analyze']);

    // Get health risk data
    Route::get('/results/{scan_id}/health-risk', [ScanResultController::class, 'getHealthRisk']);

    // Get origin history data
    Route::get('/results/{scan_id}/origin_history', [ScanResultController::class, 'getOriginHistory']);

    // Get result by scan_id (GET)
    Route::get('/results/{scan_id}', [ScanResultController::class, 'getResult']);

    // Get simulation data by scan_id
    Route::get('/results/{scan_id}/simulation', [ScanResultController::class, 'getSimulation']);

    // Poll simulation status by scan_id
    Route::get('/results/{scan_id}/simulation-status', [ScanResultController::class, 'getSimulationStatus']);

    // Get recent results (GET)
    Route::get('/results', [ScanResultController::class, 'getRecentResults']);

    // ========================================
    // NOTIFICATION ENDPOINTS
    // ========================================
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'getUnreadCount']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
    Route::delete('/notifications/{id}', [NotificationController::class, 'destroy']);
  });
});

// Legacy route for authenticated users
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
  return $request->user();
});

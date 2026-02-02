<?php

use App\Http\Controllers\Api\SimulationStatusController;
use App\Http\Controllers\model\ScanResultController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::get('/simulation-status', [SimulationStatusController::class, 'getStatus']);

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
  return $request->user();
});

// API v1 routes for mobile appp
Route::prefix('v1')->group(function () {
  // Analyze image (POST)
  Route::post('/analyze', [ScanResultController::class, 'analyze']);

  // routes/api.php
  Route::get('/results/{scan_id}/health-risk', [ScanResultController::class, 'getHealthRisk']);

  Route::get('/results/{scan_id}/origin_history', [ScanResultController::class, 'getOriginHistory']);

  // Get result by scan_id (GET)
  Route::get('/results/{scan_id}', [ScanResultController::class, 'getResult']);

  // Get simulation data by scan_id
  Route::get('/results/{scan_id}/simulation', [ScanResultController::class, 'getSimulation']);

  // Poll simulation status by scan_id
  Route::get('/results/{scan_id}/simulation-status', [ScanResultController::class, 'getSimulationStatus']);



  // Get recent results (GET) - optional
  Route::get('/results', [ScanResultController::class, 'getRecentResults']);
});

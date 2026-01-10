<?php

use App\Http\Controllers\HealthRiskController;
use App\Http\Controllers\HistoryController;
use App\Http\Controllers\model\ModelInsightsController;
use App\Http\Controllers\model\ScanResultController;
use App\Http\Controllers\model\TrainingQueueController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ResultController;
use App\Http\Controllers\ScanController;
use App\Http\Controllers\SimulationController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;
use LDAP\Result;

Route::get('/', function () {
    return Inertia::render('welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');
Route::get('/scan', [ScanController::class, "index"]);
Route::get('/scan-results', [ResultController::class, "index"]);
Route::get('/header', [PageController::class, "header"]);
Route::get('/simulation', [SimulationController::class, "index"]);
Route::get('/origin', [HistoryController::class, "index"]);
Route::get('/health-risk', [HealthRiskController::class, "index"]);

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('dashboard');
    })->name('dashboard');

    Route::get('/model/scan-results', [ScanResultController::class, "index"]);

    Route::get('/model/training-queue', [TrainingQueueController::class, "index"]);
    Route::get('/model/review-dog', [ScanResultController::class, "review"]);
});

require __DIR__ . '/settings.php';

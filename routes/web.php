<?php

use App\Http\Controllers\GoogleController;
use App\Http\Controllers\HealthRiskController;
use App\Http\Controllers\HistoryController;
use App\Http\Controllers\model\ModelInsightsController;
use App\Http\Controllers\model\ScanResultController;
use App\Http\Controllers\model\TrainingQueueController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ResultController;
use App\Http\Controllers\ScanController;
use App\Http\Controllers\SimulationController;
use App\Http\Controllers\NotificationController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    return Inertia::render('welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::get('/header', [PageController::class, "header"]);

Route::get('/auth/google', [GoogleController::class, 'redirect'])->name('google.redirect');
Route::get('/auth/google/callback', [GoogleController::class, 'callback']);

// GET route to handle accidental navigation (redirects back to scan)
Route::get('/analyze', function () {
    return redirect()->route('scan')->with('error', [
        'message' => 'Please upload an image to analyze.'
    ]);
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [ScanResultController::class, "dashboard"])->name('dashboard');

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'getUnreadCount']);
    Route::post('/notifications/{id}/mark-read', [NotificationController::class, 'markAsRead']);
    Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllAsRead']);

    Route::get('/model/scan-results', [ScanResultController::class, "index"]);
    Route::get('/scanhistory', [PageController::class, "scanhistory"]);
    Route::delete('/scanhistory/{id}', [PageController::class, "deleteScan"])->name('scanhistory.delete');

    Route::get('/scan', [ScanController::class, "index"])->name('scan');
    Route::get('/scan-results', [ResultController::class, "index"]);

    // POST route for actual analysis
    Route::post('/analyze', [ScanResultController::class, "analyze"])->name('analyze');

    Route::get('/simulation', [SimulationController::class, "index"]);
    Route::get('/simulation-status', [SimulationController::class, 'checkStatus']);
    Route::get('/ml-api/health', [ScanResultController::class, 'checkMLApiHealth']);
    Route::get('/origin', [HistoryController::class, "index"]);
    Route::post('/simulation/generate', [SimulationController::class, 'generate'])->name('simulation.generate');
    Route::get('/health-risk', [HealthRiskController::class, "index"]);

    Route::get('/model/training-queue', [TrainingQueueController::class, "index"]);
    Route::get('/model/review-dog', [ScanResultController::class, "review"]);
    Route::get('/model/review-dog/{id}', [ScanResultController::class, "preview"]);
    Route::get('/model/review-dog/{id}/delete', [ScanResultController::class, "deleteResult"]);

    Route::delete('/model-correction/{id}', [ScanResultController::class, "destroyCorrection"])->name('model.correction.delete');
    Route::post('/model/correct', [ScanResultController::class, "correctBreed"])->name('model.correct');
});

require __DIR__ . '/settings.php';

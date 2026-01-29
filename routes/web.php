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
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;
use LDAP\Result;

Route::get('/', function () {
    return Inertia::render('welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');
Route::get('/scan', [ScanController::class, "index"])->name('scan');
Route::get('/scan-results', [ResultController::class, "index"]);
Route::get('/header', [PageController::class, "header"]);
Route::get('/simulation', [SimulationController::class, "index"]);
Route::get('/origin', [HistoryController::class, "index"]);
Route::get('/health-risk', [HealthRiskController::class, "index"]);

Route::get('/auth/google', [GoogleController::class, 'redirect'])->name('google.redirect');
Route::get('/auth/google/callback', [GoogleController::class, 'callback']);

// POST route for actual analysis
Route::post('/analyze', [ScanResultController::class, "analyze"])->name('analyze');

// GET route to handle accidental navigation (redirects back to scan)
Route::get('/analyze', function () {
    return redirect()->route('scan')->with('error', [
        'message' => 'Please upload an image to analyze.'
    ]);
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [ScanResultController::class, "dashboard"])->name('dashboard');

    Route::get('/model/scan-results', [ScanResultController::class, "index"]);

    Route::get('/model/training-queue', [TrainingQueueController::class, "index"]);
    Route::get('/model/review-dog', [ScanResultController::class, "review"]);
    Route::get('/model/review-dog/{id}', [ScanResultController::class, "preview"]);

    Route::delete('/model-correction/{id}', [ScanResultController::class, "destroyCorrection"])->name('model.correction.delete');
    // Inside your auth middleware group
    Route::post('/model/correct', [ScanResultController::class, "correctBreed"])->name('model.correct');
});

require __DIR__ . '/settings.php';

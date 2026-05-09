<?php

use App\Http\Controllers\AdminNotificationController;
use App\Http\Controllers\GoogleController;
use App\Http\Controllers\HealthRiskController;
use App\Http\Controllers\HistoryController;
use App\Http\Controllers\model\ModelInsightsController;
use App\Http\Controllers\model\ScanResultController;
use App\Http\Controllers\model\TrainingQueueController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ResultController;
use App\Http\Controllers\AppointmentController;
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


    Route::post('/appointments/request', [AppointmentController::class, 'userRequest'])
    ->name('appointments.request');
    Route::post('/model/appointments', [AppointmentController::class, 'store'])
        ->name('appointments.store');
    Route::get('/model/appointmentspage', [AppointmentController::class, 'adminIndex']);

    // Normal user — view their appointments
    Route::get('/appointments', [AppointmentController::class, 'userIndex'])
        ->name('appointments.index');

    // Normal user — accept or reject an appointment
    Route::post('/appointments/{appointment}/status', [AppointmentController::class, 'updateStatus'])
        ->name('appointments.status');

    Route::delete('/appointments/{appointment}', [AppointmentController::class, 'destroy'])
    ->name('appointments.destroy');

    Route::get('/admin/notifications', [AdminNotificationController::class, 'index'])
        ->name('admin.notifications.index');

    Route::post('/admin/notifications/mark-all-read', [AdminNotificationController::class, 'markAllRead'])
        ->name('admin.notifications.markAllRead');
    Route::post('/admin/notifications/{id}/read', [AdminNotificationController::class, 'markRead'])
        ->name('admin.notifications.markRead');



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

    Route::get('/scan-results/{id}', [ResultController::class, 'show']);

    Route::get('/model/training-queue', [TrainingQueueController::class, "index"]);
    Route::get('/model/review-dog', [ScanResultController::class, "review"]);
    Route::get('/model/review-dog/{id}', [ScanResultController::class, "preview"]);
    Route::get('/model/review-dog/{id}/delete', [ScanResultController::class, "deleteResult"]);

    Route::delete('/model-correction/{id}', [ScanResultController::class, "destroyCorrection"])->name('model.correction.delete');
    Route::post('/model/correct', [ScanResultController::class, "correctBreed"])->name('model.correct');
});

require __DIR__ . '/settings.php';

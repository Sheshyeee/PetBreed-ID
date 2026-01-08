<?php

use App\Http\Controllers\HistoryController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ResultController;
use App\Http\Controllers\ScanController;
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
Route::get('/simulation', [PageController::class, "simulation"]);
Route::get('/origin', [HistoryController::class, "index"]);

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('dashboard');
    })->name('dashboard');
});

require __DIR__ . '/settings.php';

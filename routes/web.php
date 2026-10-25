<?php

use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\AssistantController;
use App\Http\Controllers\AuditController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\DemoController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\SessionController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::get('/assistant', [AssistantController::class, 'index'])->name('assistant.index');
Route::post('/assistant', [AssistantController::class, 'store'])
    ->middleware('throttle:12,1')
    ->name('assistant.store');

Route::get('/sessions/{session}', [SessionController::class, 'show'])->name('sessions.show');
Route::post('/sessions/{session}/approve', [SessionController::class, 'approve'])
    ->middleware('throttle:20,1')
    ->name('sessions.approve');
Route::post('/sessions/{session}/simulate-price-change', [SessionController::class, 'simulatePriceChange'])
    ->middleware('throttle:10,1')
    ->name('sessions.simulate-price-change');

Route::get('/approvals/{approval}', [ApprovalController::class, 'show'])->name('approvals.show');
Route::post('/approvals/{approval}/authorize', [ApprovalController::class, 'authorize'])
    ->middleware('throttle:10,1')
    ->name('approvals.authorize');
Route::post('/approvals/{approval}/simulate-price-change', [ApprovalController::class, 'simulatePriceChange'])
    ->middleware('throttle:10,1')
    ->name('approvals.simulate-price-change');

Route::get('/checkout/{order}/return', [CheckoutController::class, 'handleReturn'])->name('checkout.return');
Route::get('/checkout/{order}/cancel', [CheckoutController::class, 'cancel'])->name('checkout.cancel');
Route::get('/checkout/{order}/result', [CheckoutController::class, 'result'])->name('checkout.result');

Route::get('/audit', [AuditController::class, 'index'])->name('audit.index');
Route::get('/audit/{session}', [AuditController::class, 'show'])->name('audit.show');

Route::get('/catalog', CatalogController::class)->name('catalog.index');

Route::post('/demo/reset-catalog', [DemoController::class, 'resetCatalog'])
    ->middleware('throttle:6,1')
    ->name('demo.reset-catalog');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';

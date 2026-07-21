<?php

use App\Http\Controllers\Admin\AdditionalCellsController;
use App\Http\Controllers\Admin\EventCellsController;
use App\Http\Controllers\Admin\EventsController;
use App\Http\Controllers\Admin\HoursController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\StripeController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\BalanceController;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
})->name('welcome');

Route::middleware(['auth', 'verified', 'admin'])->group(function () {
    Route::controller(HoursController::class)->group(function () {
        Route::get('/dashboard', 'index')->name('dashboard');
        Route::post('/dashboard', 'store')->name('dashboard.store');

    });
});
Route::middleware(['auth', 'verified'])->group(function () {
    Route::controller(HoursController::class)->group(function () {
        Route::get('/api/dashboard', 'getHours')->name('/api/dashboard.getHours');
    });
    Route::controller(EventsController::class)->group(function () {
        Route::get('/events', 'getAll')->name('events.getAll');
        Route::post('/events', 'store')->name('events.store');
        Route::patch('/events/{event}', 'update')->name('events.update');
        Route::delete('/events/{id}', 'destroy')->name('events.destroy');
    });
    Route::controller(EventCellsController::class)->group(function () {
        Route::get('/event-cells', 'getAll')->name('eventcells.getAll');
        Route::post('/event-cells', 'bulkStore')->name('eventcells.bulkStore');
        Route::delete('/event-cells/{eventId}', 'destroy')->name('eventcells.destroy');
    });
    Route::controller(AdditionalCellsController::class)->group(function () {
        Route::get('/additional', 'getAll')->name('additional.getAll');
        Route::post('/additional', 'store')->name('additional.store');
        Route::delete('/additional/{val}', 'destroy')->name('additional.destroy');
    });
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth'])->group(function () {
    Route::get('/payment/success', [StripeController::class, 'success'])->name('payment.success');
    Route::get('/payment/cancel', function () {
        return redirect()->route('balance.index')->with('error', 'Оплату скасовано.');
    })->name('payment.cancel');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/balance', [BalanceController::class, 'index'])->name('balance.index');
    Route::get('/api/stripe/session-status/{sessionId}', [StripeController::class, 'getSessionStatus']);
});
Route::middleware(['auth'])->group(function () {
    Route::post('/payment/create-checkout-session', [StripeController::class, 'createSession']);
});

require __DIR__.'/auth.php';

<?php

use App\Http\Controllers\Admin\EventCellsController;
use App\Http\Controllers\Admin\EventsController;
use App\Http\Controllers\Admin\HoursController;
use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::controller(HoursController::class)->group(function () {
        Route::get('/dashboard', 'index')->name('dashboard');
        Route::post('/dashboard', 'store')->name('dashboard.store');
    });
    Route::controller(EventsController::class)->group(function () {
        Route::get('/events', 'getAll')->name('events.getAll');
        Route::post('/events', 'store')->name('events.store');
        Route::patch('/events/{event}', 'update')->name('events.update');
    });
    Route::controller(EventCellsController::class)->group(function () {
        Route::get('/event-cells', 'getAll')->name('eventcells.getAll');
        Route::post('/event-cells', 'bulkStore')->name('eventcells.bulkStore');
        Route::delete('/event-cells/{eventId}', 'destroy')->name('eventcells.destroy');
    });

});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';

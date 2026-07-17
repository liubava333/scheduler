<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\StripeController;
use App\Http\Controllers\StripeWebhookController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Endpoint for Vue to check payment status
Route::get('/stripe/session-status/{sessionId}', [StripeController::class, 'getSessionStatus']);

Route::post('/webhook', [StripeWebhookController::class, 'handleWebhook']);

Route::middleware('auth:sanctum')->get('/user/balance', function (Request $request) {
    return response()->json([
        'balance' => $request->user()->balance,
        'tariff_name' => 'Premium Single'
    ]);
});


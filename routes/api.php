<?php

use App\Http\Controllers\PaymentController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');



/*
|--------------------------------------------------------------------------
| Payment Routes
|--------------------------------------------------------------------------
|
| Multi-Gateway Payment Processing API
| - Process payments with automatic failover
| - Handle webhooks from payment gateways
| - Process refunds through original gateway
|
*/

Route::prefix('payments')->group(function () {
    // Process a new payment
    Route::post('/process', [PaymentController::class, 'processPayment'])
        ->name('payments.process');

    // Get available payment methods
    Route::get('/methods', [PaymentController::class, 'getPaymentMethods'])
        ->name('payments.methods');

    // Webhook endpoints (no auth - signature verification instead)
    Route::post('/webhook/{gateway}', [PaymentController::class, 'handleWebhook'])
        ->name('payments.webhook')
        ->withoutMiddleware(['throttle:api']);

    // Simulate webhook (debug only)
    Route::post('/simulate-webhook', [PaymentController::class, 'simulateWebhook'])
        ->name('payments.simulate-webhook');

    // Get payment status
    Route::get('/{paymentId}', [PaymentController::class, 'getPayment'])
        ->name('payments.show');

    // Process refund
    Route::post('/{paymentId}/refund', [PaymentController::class, 'processRefund'])
        ->name('payments.refund');
});

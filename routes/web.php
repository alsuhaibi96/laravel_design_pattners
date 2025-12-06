<?php

use App\Http\Controllers\CheckoutController;
use Illuminate\Support\Facades\Route;


// Checkout page
Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout');


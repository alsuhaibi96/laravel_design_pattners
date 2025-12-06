<?php

namespace App\Http\Controllers;

use App\Services\Payment\GatewayRouter;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Checkout Controller
 * 
 * Handles the checkout frontend
 */
class CheckoutController extends Controller
{
    public function __construct(
        private readonly GatewayRouter $gatewayRouter,
    ) {}

    /**
     * Show checkout page
     */
    public function index(Request $request): View
    {
        // Mock cart data
        $cart = [
            'items' => [
                [
                    'id' => 1,
                    'name' => 'Premium Wireless Headphones',
                    'price' => 149.99,
                    'quantity' => 1,
                    'image' => 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=100&h=100&fit=crop',
                ],
                [
                    'id' => 2,
                    'name' => 'Mechanical Keyboard',
                    'price' => 89.99,
                    'quantity' => 1,
                    'image' => 'https://images.unsplash.com/photo-1511467687858-23d96c32e4ae?w=100&h=100&fit=crop',
                ],
            ],
            'subtotal' => 239.98,
            'tax' => 19.20,
            'total' => 259.18,
        ];

        // Get available gateways
        $gateways = $this->gatewayRouter->getAllGatewaysConfig();

        return view('checkout', [
            'cart' => $cart,
            'gateways' => $gateways,
        ]);
    }
}


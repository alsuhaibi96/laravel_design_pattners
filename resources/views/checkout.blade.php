<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Checkout | Premium Store</title>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-primary: #0a0a0f;
            --bg-secondary: #12121a;
            --bg-tertiary: #1a1a24;
            --bg-card: #16161e;
            --accent-primary: #00d4aa;
            --accent-secondary: #00b894;
            --accent-glow: rgba(0, 212, 170, 0.3);
            --text-primary: #ffffff;
            --text-secondary: #8b8b9e;
            --text-muted: #5a5a6e;
            --border-color: #2a2a3a;
            --error-color: #ff6b6b;
            --success-color: #00d4aa;
            --warning-color: #ffd93d;
            --stripe-color: #635bff;
            --paypal-color: #0070ba;
            --bank-color: #00b894;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Space Grotesk', sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            min-height: 100vh;
            line-height: 1.6;
        }

        /* Animated background */
        .bg-pattern {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            background: 
                radial-gradient(ellipse at 20% 20%, rgba(0, 212, 170, 0.08) 0%, transparent 50%),
                radial-gradient(ellipse at 80% 80%, rgba(99, 91, 255, 0.08) 0%, transparent 50%),
                linear-gradient(180deg, var(--bg-primary) 0%, var(--bg-secondary) 100%);
        }

        .bg-grid {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            background-image: 
                linear-gradient(rgba(42, 42, 58, 0.3) 1px, transparent 1px),
                linear-gradient(90deg, rgba(42, 42, 58, 0.3) 1px, transparent 1px);
            background-size: 50px 50px;
            opacity: 0.5;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        /* Header */
        .checkout-header {
            text-align: center;
            margin-bottom: 60px;
            animation: fadeInDown 0.6s ease-out;
        }

        .checkout-header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--accent-primary), var(--stripe-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 10px;
        }

        .checkout-header p {
            color: var(--text-secondary);
            font-size: 1.1rem;
        }

        /* Main Grid */
        .checkout-grid {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 40px;
            align-items: start;
        }

        @media (max-width: 900px) {
            .checkout-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Cards */
        .card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 30px;
            animation: fadeInUp 0.6s ease-out;
        }

        .card-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .card-title .icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--accent-primary), var(--accent-secondary));
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Cart Items */
        .cart-items {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .cart-item {
            display: flex;
            gap: 16px;
            padding: 16px;
            background: var(--bg-tertiary);
            border-radius: 14px;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .cart-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.3);
        }

        .cart-item-image {
            width: 80px;
            height: 80px;
            border-radius: 10px;
            object-fit: cover;
        }

        .cart-item-details {
            flex: 1;
        }

        .cart-item-name {
            font-weight: 500;
            margin-bottom: 6px;
        }

        .cart-item-price {
            color: var(--accent-primary);
            font-family: 'JetBrains Mono', monospace;
            font-weight: 500;
        }

        .cart-item-quantity {
            color: var(--text-secondary);
            font-size: 0.875rem;
        }

        /* Payment Methods */
        .payment-methods {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-bottom: 30px;
        }

        .payment-method {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 20px;
            background: var(--bg-tertiary);
            border: 2px solid var(--border-color);
            border-radius: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .payment-method:hover {
            border-color: var(--text-muted);
            transform: translateX(4px);
        }

        .payment-method.selected {
            border-color: var(--accent-primary);
            background: rgba(0, 212, 170, 0.05);
            box-shadow: 0 0 20px var(--accent-glow);
        }

        .payment-method input {
            display: none;
        }

        .payment-method-radio {
            width: 22px;
            height: 22px;
            border: 2px solid var(--border-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
        }

        .payment-method.selected .payment-method-radio {
            border-color: var(--accent-primary);
        }

        .payment-method.selected .payment-method-radio::after {
            content: '';
            width: 10px;
            height: 10px;
            background: var(--accent-primary);
            border-radius: 50%;
        }

        .payment-method-icon {
            width: 48px;
            height: 32px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.75rem;
        }

        .payment-method-icon.stripe {
            background: var(--stripe-color);
        }

        .payment-method-icon.paypal {
            background: var(--paypal-color);
        }

        .payment-method-icon.bank {
            background: var(--bank-color);
        }

        .payment-method-info {
            flex: 1;
        }

        .payment-method-name {
            font-weight: 500;
            margin-bottom: 2px;
        }

        .payment-method-desc {
            color: var(--text-secondary);
            font-size: 0.875rem;
        }

        .payment-method-fee {
            font-family: 'JetBrains Mono', monospace;
            color: var(--text-muted);
            font-size: 0.875rem;
        }

        /* Country Select */
        .form-group {
            margin-bottom: 24px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-secondary);
            font-size: 0.875rem;
            font-weight: 500;
        }

        .form-select {
            width: 100%;
            padding: 14px 18px;
            background: var(--bg-tertiary);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            color: var(--text-primary);
            font-family: inherit;
            font-size: 1rem;
            cursor: pointer;
            transition: border-color 0.3s;
        }

        .form-select:focus {
            outline: none;
            border-color: var(--accent-primary);
        }

        /* Order Summary */
        .order-summary {
            position: sticky;
            top: 40px;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid var(--border-color);
        }

        .summary-row:last-of-type {
            border-bottom: none;
        }

        .summary-label {
            color: var(--text-secondary);
        }

        .summary-value {
            font-family: 'JetBrains Mono', monospace;
            font-weight: 500;
        }

        .summary-total {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px solid var(--border-color);
        }

        .summary-total .summary-label {
            color: var(--text-primary);
            font-size: 1.1rem;
            font-weight: 600;
        }

        .summary-total .summary-value {
            color: var(--accent-primary);
            font-size: 1.5rem;
            font-weight: 700;
        }

        /* Pay Button */
        .pay-button {
            width: 100%;
            padding: 18px 32px;
            margin-top: 24px;
            background: linear-gradient(135deg, var(--accent-primary), var(--accent-secondary));
            border: none;
            border-radius: 14px;
            color: var(--bg-primary);
            font-family: inherit;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }

        .pay-button:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 10px 40px var(--accent-glow);
        }

        .pay-button:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .pay-button .spinner {
            display: none;
            width: 20px;
            height: 20px;
            border: 2px solid transparent;
            border-top-color: var(--bg-primary);
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            margin-right: 10px;
        }

        .pay-button.loading .spinner {
            display: inline-block;
        }

        .pay-button.loading .button-text {
            opacity: 0.7;
        }

        /* Status Messages */
        .status-message {
            margin-top: 20px;
            padding: 16px 20px;
            border-radius: 12px;
            display: none;
            animation: fadeIn 0.3s ease-out;
        }

        .status-message.success {
            display: block;
            background: rgba(0, 212, 170, 0.1);
            border: 1px solid var(--success-color);
            color: var(--success-color);
        }

        .status-message.error {
            display: block;
            background: rgba(255, 107, 107, 0.1);
            border: 1px solid var(--error-color);
            color: var(--error-color);
        }

        .status-message.info {
            display: block;
            background: rgba(99, 91, 255, 0.1);
            border: 1px solid var(--stripe-color);
            color: var(--stripe-color);
        }

        /* Payment Result Modal */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            animation: fadeIn 0.3s ease-out;
        }

        .modal-overlay.show {
            display: flex;
        }

        .modal {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 24px;
            padding: 40px;
            max-width: 500px;
            width: 90%;
            text-align: center;
            animation: scaleIn 0.3s ease-out;
        }

        .modal-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
            font-size: 2.5rem;
        }

        .modal-icon.success {
            background: rgba(0, 212, 170, 0.1);
            border: 2px solid var(--success-color);
        }

        .modal-icon.error {
            background: rgba(255, 107, 107, 0.1);
            border: 2px solid var(--error-color);
        }

        .modal h2 {
            font-size: 1.5rem;
            margin-bottom: 12px;
        }

        .modal p {
            color: var(--text-secondary);
            margin-bottom: 24px;
        }

        .modal-details {
            background: var(--bg-tertiary);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 24px;
            text-align: left;
        }

        .modal-detail-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
        }

        .modal-detail-label {
            color: var(--text-secondary);
        }

        .modal-detail-value {
            font-family: 'JetBrains Mono', monospace;
            font-weight: 500;
        }

        .modal-button {
            padding: 14px 32px;
            background: var(--accent-primary);
            border: none;
            border-radius: 10px;
            color: var(--bg-primary);
            font-family: inherit;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .modal-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px var(--accent-glow);
        }

        /* Security Badge */
        .security-badge {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 20px;
            padding: 12px;
            background: var(--bg-tertiary);
            border-radius: 10px;
            color: var(--text-secondary);
            font-size: 0.875rem;
        }

        .security-badge svg {
            width: 16px;
            height: 16px;
            fill: var(--accent-primary);
        }

        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes scaleIn {
            from {
                opacity: 0;
                transform: scale(0.9);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Staggered animations */
        .cart-item:nth-child(1) { animation-delay: 0.1s; }
        .cart-item:nth-child(2) { animation-delay: 0.2s; }
        .payment-method:nth-child(1) { animation-delay: 0.15s; }
        .payment-method:nth-child(2) { animation-delay: 0.25s; }
        .payment-method:nth-child(3) { animation-delay: 0.35s; }
    </style>
</head>
<body>
    <div class="bg-pattern"></div>
    <div class="bg-grid"></div>

    <div class="container">
        <header class="checkout-header">
            <h1>Secure Checkout</h1>
            <p>Complete your purchase with our multi-gateway payment system</p>
        </header>

        <div class="checkout-grid">
            <!-- Left Column -->
            <div class="checkout-main">
                <!-- Cart Items -->
                <div class="card" style="animation-delay: 0.1s;">
                    <h2 class="card-title">
                        <span class="icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="9" cy="21" r="1"></circle>
                                <circle cx="20" cy="21" r="1"></circle>
                                <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                            </svg>
                        </span>
                        Your Cart
                    </h2>
                    <div class="cart-items">
                        @foreach($cart['items'] as $item)
                        <div class="cart-item">
                            <img src="{{ $item['image'] }}" alt="{{ $item['name'] }}" class="cart-item-image">
                            <div class="cart-item-details">
                                <div class="cart-item-name">{{ $item['name'] }}</div>
                                <div class="cart-item-price">${{ number_format($item['price'], 2) }}</div>
                                <div class="cart-item-quantity">Qty: {{ $item['quantity'] }}</div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>

                <!-- Payment Method -->
                <div class="card" style="margin-top: 30px; animation-delay: 0.2s;">
                    <h2 class="card-title">
                        <span class="icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect>
                                <line x1="1" y1="10" x2="23" y2="10"></line>
                            </svg>
                        </span>
                        Payment Method
                    </h2>

                    <div class="form-group">
                        <label class="form-label">Billing Country</label>
                        <select class="form-select" id="country-select">
                            <option value="US">United States</option>
                            <option value="GB">United Kingdom</option>
                            <option value="CA">Canada</option>
                            <option value="AU">Australia</option>
                            <option value="DE">Germany</option>
                            <option value="FR">France</option>
                            <option value="JP">Japan</option>
                            <option value="SG">Singapore</option>
                        </select>
                    </div>

                    <div class="payment-methods" id="payment-methods">
                        <label class="payment-method selected" data-gateway="stripe">
                            <input type="radio" name="payment_method" value="stripe" checked>
                            <div class="payment-method-radio"></div>
                            <div class="payment-method-icon stripe">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="white">
                                    <path d="M13.976 9.15c-2.172-.806-3.356-1.426-3.356-2.409 0-.831.683-1.305 1.901-1.305 2.227 0 4.515.858 6.09 1.631l.89-5.494C18.252.975 15.697 0 12.165 0 9.667 0 7.589.654 6.104 1.872 4.56 3.147 3.757 4.992 3.757 7.218c0 4.039 2.467 5.76 6.476 7.219 2.585.92 3.445 1.574 3.445 2.583 0 .98-.84 1.545-2.354 1.545-1.875 0-4.965-.921-6.99-2.109l-.9 5.555C5.175 22.99 8.385 24 11.714 24c2.641 0 4.843-.624 6.328-1.813 1.664-1.305 2.525-3.236 2.525-5.732 0-4.128-2.524-5.851-6.591-7.305z"/>
                                </svg>
                            </div>
                            <div class="payment-method-info">
                                <div class="payment-method-name">Credit/Debit Card</div>
                                <div class="payment-method-desc">Pay securely with Stripe</div>
                            </div>
                            <div class="payment-method-fee" id="stripe-fee">Fee: $--</div>
                        </label>

                        <label class="payment-method" data-gateway="paypal">
                            <input type="radio" name="payment_method" value="paypal">
                            <div class="payment-method-radio"></div>
                            <div class="payment-method-icon paypal">
                                <svg width="20" height="24" viewBox="0 0 24 32" fill="white">
                                    <path d="M20.067 8.478c.492.315.844.825.99 1.43.205.852.205 1.81.029 2.87-.585 3.516-2.606 5.62-5.912 6.233-.468.087-.964.131-1.482.131H12.26l-.762 4.838H8.014l-.381 2.42H4.148l.381-2.42 2.667-16.94h6.317c1.38 0 2.544.198 3.494.594.949.396 1.728.961 2.332 1.694.605.733 1.026 1.587 1.263 2.56.096.396.159.772.191 1.13.013.145.022.285.028.42.073-.066.15-.13.246-.19z"/>
                                </svg>
                            </div>
                            <div class="payment-method-info">
                                <div class="payment-method-name">PayPal</div>
                                <div class="payment-method-desc">Fast and secure checkout</div>
                            </div>
                            <div class="payment-method-fee" id="paypal-fee">Fee: $--</div>
                        </label>

                        <label class="payment-method" data-gateway="bank_transfer">
                            <input type="radio" name="payment_method" value="bank_transfer">
                            <div class="payment-method-radio"></div>
                            <div class="payment-method-icon bank">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                                    <path d="M3 21h18"></path>
                                    <path d="M3 10h18"></path>
                                    <path d="M5 6l7-3 7 3"></path>
                                    <path d="M4 10v11"></path>
                                    <path d="M20 10v11"></path>
                                    <path d="M8 14v3"></path>
                                    <path d="M12 14v3"></path>
                                    <path d="M16 14v3"></path>
                                </svg>
                            </div>
                            <div class="payment-method-info">
                                <div class="payment-method-name">Bank Transfer</div>
                                <div class="payment-method-desc">Direct bank payment (ACH)</div>
                            </div>
                            <div class="payment-method-fee" id="bank_transfer-fee">Fee: $--</div>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Right Column - Order Summary -->
            <div class="order-summary">
                <div class="card" style="animation-delay: 0.3s;">
                    <h2 class="card-title">
                        <span class="icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                <polyline points="14 2 14 8 20 8"></polyline>
                                <line x1="16" y1="13" x2="8" y2="13"></line>
                                <line x1="16" y1="17" x2="8" y2="17"></line>
                                <polyline points="10 9 9 9 8 9"></polyline>
                            </svg>
                        </span>
                        Order Summary
                    </h2>

                    <div class="summary-row">
                        <span class="summary-label">Subtotal</span>
                        <span class="summary-value">${{ number_format($cart['subtotal'], 2) }}</span>
                    </div>
                    <div class="summary-row">
                        <span class="summary-label">Tax</span>
                        <span class="summary-value">${{ number_format($cart['tax'], 2) }}</span>
                    </div>
                    <div class="summary-row">
                        <span class="summary-label">Processing Fee</span>
                        <span class="summary-value" id="processing-fee">$--</span>
                    </div>

                    <div class="summary-total">
                        <div class="summary-row">
                            <span class="summary-label">Total</span>
                            <span class="summary-value" id="total-amount">${{ number_format($cart['total'], 2) }}</span>
                        </div>
                    </div>

                    <button class="pay-button" id="pay-button">
                        <span class="spinner"></span>
                        <span class="button-text">Pay Now</span>
                    </button>

                    <div class="status-message" id="status-message"></div>

                    <div class="security-badge">
                        <svg viewBox="0 0 24 24">
                            <path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm0 10.99h7c-.53 4.12-3.28 7.79-7 8.94V12H5V6.3l7-3.11v8.8z"/>
                        </svg>
                        <span>Secure checkout with end-to-end encryption</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Result Modal -->
    <div class="modal-overlay" id="result-modal">
        <div class="modal">
            <div class="modal-icon" id="modal-icon">
                <span id="modal-icon-text"></span>
            </div>
            <h2 id="modal-title"></h2>
            <p id="modal-message"></p>
            <div class="modal-details" id="modal-details"></div>
            <button class="modal-button" id="modal-button">Continue Shopping</button>
        </div>
    </div>

    <script>
        const baseTotal = {{ $cart['total'] }};
        let selectedGateway = 'stripe';
        let processingFee = 0;

        // Fee calculation (matches backend)
        const feeStructure = {
            stripe: { percentage: 2.9, fixed: 0.30 },
            paypal: { percentage: 3.49, fixed: 0.49 },
            bank_transfer: { percentage: 0.5, fixed: 1.00 }
        };

        function calculateFee(gateway, amount) {
            const structure = feeStructure[gateway];
            return ((amount * structure.percentage / 100) + structure.fixed).toFixed(2);
        }

        function updateFees() {
            Object.keys(feeStructure).forEach(gateway => {
                const fee = calculateFee(gateway, baseTotal);
                document.getElementById(`${gateway}-fee`).textContent = `Fee: $${fee}`;
            });

            processingFee = parseFloat(calculateFee(selectedGateway, baseTotal));
            document.getElementById('processing-fee').textContent = `$${processingFee.toFixed(2)}`;
            document.getElementById('total-amount').textContent = `$${(baseTotal + processingFee).toFixed(2)}`;
        }

        // Payment method selection
        document.querySelectorAll('.payment-method').forEach(method => {
            method.addEventListener('click', function() {
                document.querySelectorAll('.payment-method').forEach(m => m.classList.remove('selected'));
                this.classList.add('selected');
                this.querySelector('input').checked = true;
                selectedGateway = this.dataset.gateway;
                updateFees();
            });
        });

        // Generate idempotency key
        function generateIdempotencyKey() {
            return 'idem_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
        }

        // Process payment
        document.getElementById('pay-button').addEventListener('click', async function() {
            const button = this;
            const statusMessage = document.getElementById('status-message');
            
            button.classList.add('loading');
            button.disabled = true;
            statusMessage.className = 'status-message';
            statusMessage.style.display = 'none';

            const countryCode = document.getElementById('country-select').value;
            const paymentMethod = document.querySelector('input[name="payment_method"]:checked').value;
            
            // Map payment method to proper format
            const methodMap = {
                'stripe': 'credit_card',
                'paypal': 'paypal', 
                'bank_transfer': 'bank_transfer'
            };

            try {
                const response = await fetch('/api/payments/process', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Idempotency-Key': generateIdempotencyKey()
                    },
                    body: JSON.stringify({
                        amount: baseTotal + processingFee,
                        currency: 'USD',
                        payment_method: methodMap[paymentMethod],
                        preferred_gateway: paymentMethod,
                        country_code: countryCode,
                        order_id: 'ORD-' + Date.now()
                    })
                });

                const data = await response.json();
                showResult(data);

            } catch (error) {
                showResult({
                    success: false,
                    message: 'Network error. Please try again.',
                    error: { message: error.message }
                });
            } finally {
                button.classList.remove('loading');
                button.disabled = false;
            }
        });

        function showResult(data) {
            const modal = document.getElementById('result-modal');
            const icon = document.getElementById('modal-icon');
            const iconText = document.getElementById('modal-icon-text');
            const title = document.getElementById('modal-title');
            const message = document.getElementById('modal-message');
            const details = document.getElementById('modal-details');

            if (data.success) {
                icon.className = 'modal-icon success';
                iconText.textContent = '✓';
                title.textContent = 'Payment Successful!';
                message.textContent = 'Your payment has been processed successfully.';
                
                details.innerHTML = `
                    <div class="modal-detail-row">
                        <span class="modal-detail-label">Payment ID</span>
                        <span class="modal-detail-value">${data.payment?.uuid || 'N/A'}</span>
                    </div>
                    <div class="modal-detail-row">
                        <span class="modal-detail-label">Gateway</span>
                        <span class="modal-detail-value">${data.payment?.gateway?.toUpperCase() || 'N/A'}</span>
                    </div>
                    <div class="modal-detail-row">
                        <span class="modal-detail-label">Transaction ID</span>
                        <span class="modal-detail-value">${data.payment?.transaction_id || 'N/A'}</span>
                    </div>
                    <div class="modal-detail-row">
                        <span class="modal-detail-label">Amount</span>
                        <span class="modal-detail-value">$${data.payment?.amount || '0.00'} ${data.payment?.currency || 'USD'}</span>
                    </div>
                `;
            } else {
                icon.className = 'modal-icon error';
                iconText.textContent = '✕';
                title.textContent = 'Payment Failed';
                message.textContent = data.message || 'Something went wrong with your payment.';
                
                details.innerHTML = `
                    <div class="modal-detail-row">
                        <span class="modal-detail-label">Error</span>
                        <span class="modal-detail-value">${data.error?.message || data.message || 'Unknown error'}</span>
                    </div>
                    ${data.error?.code ? `
                    <div class="modal-detail-row">
                        <span class="modal-detail-label">Error Code</span>
                        <span class="modal-detail-value">${data.error.code}</span>
                    </div>
                    ` : ''}
                    ${data.error?.is_retryable ? `
                    <div class="modal-detail-row">
                        <span class="modal-detail-label">Retryable</span>
                        <span class="modal-detail-value">Yes - Please try again</span>
                    </div>
                    ` : ''}
                `;
            }

            modal.classList.add('show');
        }

        // Close modal
        document.getElementById('modal-button').addEventListener('click', function() {
            document.getElementById('result-modal').classList.remove('show');
        });

        document.getElementById('result-modal').addEventListener('click', function(e) {
            if (e.target === this) {
                this.classList.remove('show');
            }
        });

        // Initialize fees
        updateFees();
    </script>
</body>
</html>


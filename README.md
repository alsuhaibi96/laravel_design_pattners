# Multi-Gateway Payment Processing System

A Laravel implementation of a multi-gateway payment processing system using the **Strategy Design Pattern**. This system supports multiple payment providers with automatic failover, geographic routing, and fee optimization.

## 📋 Task Document

**Original Requirements**: [Task 2: Multi-Gateway Payment Processing](https://docs.google.com/document/d/1q1gaKsC7oKONm_kCeed8nllXmbELV9iQ_mKFo2euRa8/edit?usp=sharing)

## 🎯 Overview

This payment system solves the common problem of managing multiple payment gateways while keeping code maintainable and extensible. Using the Strategy Pattern, each payment gateway is encapsulated in its own class, making it easy to add new gateways without modifying existing code.

## 🏗️ Architecture

### Design Pattern: Strategy Pattern

The Strategy Pattern allows us to:
- Define a family of payment algorithms (gateways)
- Encapsulate each one in a separate class
- Make them interchangeable at runtime

```
┌─────────────────────────────────────────────────────────────────┐
│                      PaymentController                          │
└─────────────────────────┬───────────────────────────────────────┘
                          │
                          ▼
┌─────────────────────────────────────────────────────────────────┐
│                      PaymentService                             │
│  - Orchestrates payment flow                                    │
│  - Handles idempotency                                          │
│  - Manages retry logic with failover                            │
└─────────────────────────┬───────────────────────────────────────┘
                          │
          ┌───────────────┼───────────────┐
          ▼               ▼               ▼
┌─────────────────┐ ┌───────────┐ ┌─────────────────┐
│  GatewayRouter  │ │ Idempotency│ │  WebhookService │
│  - Selects      │ │  Service   │ │  - Handles      │
│    gateway      │ │  - Prevents│ │    callbacks    │
│  - Orders by    │ │    dupes   │ │  - Normalizes   │
│    preference   │ └───────────┘ │    events       │
└────────┬────────┘               └─────────────────┘
         │
         ▼
┌─────────────────────────────────────────────────────────────────┐
│              PaymentGatewayContext (Strategy Context)           │
│  - Holds reference to current strategy                          │
│  - Delegates payment operations to strategy                     │
└─────────────────────────┬───────────────────────────────────────┘
                          │
                          ▼
┌─────────────────────────────────────────────────────────────────┐
│              PaymentGatewayInterface (Strategy Interface)       │
│  + processPayment()                                             │
│  + processRefund()                                              │
│  + handleWebhook()                                              │
│  + verifyWebhookSignature()                                     │
└─────────────────────────┬───────────────────────────────────────┘
                          │
         ┌────────────────┼────────────────┐
         ▼                ▼                ▼
┌─────────────┐   ┌─────────────┐   ┌─────────────────┐
│StripeGateway│   │PayPalGateway│   │BankTransferGateway│
│(Concrete    │   │(Concrete    │   │(Concrete          │
│ Strategy)   │   │ Strategy)   │   │ Strategy)         │
└─────────────┘   └─────────────┘   └─────────────────┘
```

## 📁 Project Structure

```
app/
├── DTOs/                          # Data Transfer Objects
│   ├── PaymentRequestDTO.php
│   ├── PaymentResponseDTO.php
│   ├── RefundRequestDTO.php
│   ├── RefundResponseDTO.php
│   └── WebhookPayloadDTO.php
│
├── Enums/                         # Type-safe enums
│   ├── PaymentStatus.php
│   ├── PaymentAttemptStatus.php
│   ├── PaymentGateway.php
│   ├── PaymentMethod.php
│   └── RefundStatus.php
│
├── Http/Controllers/
│   ├── PaymentController.php      # API endpoints
│   └── CheckoutController.php     # Frontend controller
│
├── Models/                        # Eloquent models
│   ├── Payment.php
│   ├── PaymentAttempt.php
│   ├── Refund.php
│   ├── IdempotencyKey.php
│   └── TransactionLog.php
│
├── PaymentGateways/              # Strategy Pattern implementation
│   ├── PaymentGatewayInterface.php    # Strategy interface
│   ├── AbstractPaymentGateway.php     # Base class with shared logic
│   ├── PaymentGatewayContext.php      # Context class
│   └── Strategies/
│       ├── StripeGateway.php          # Concrete strategy
│       ├── PayPalGateway.php          # Concrete strategy
│       └── BankTransferGateway.php    # Concrete strategy
│
└── Services/Payment/             # Business logic services
    ├── PaymentService.php        # Main orchestrator
    ├── GatewayRouter.php         # Gateway selection logic
    ├── IdempotencyService.php    # Duplicate prevention
    ├── RefundService.php         # Refund processing
    └── WebhookService.php        # Webhook handling

config/
└── payment.php                   # Gateway configuration

database/migrations/
├── create_payments_table.php
├── create_payment_attempts_table.php
├── create_refunds_table.php
├── create_idempotency_keys_table.php
└── create_transaction_logs_table.php
```

## 🚀 Installation

1. **Run migrations:**
```bash
php artisan migrate
```

2. **Configure environment (optional for mock mode):**
```env
STRIPE_ENABLED=true
STRIPE_API_KEY=sk_test_xxx
STRIPE_WEBHOOK_SECRET=whsec_xxx

PAYPAL_ENABLED=true
PAYPAL_CLIENT_ID=xxx
PAYPAL_CLIENT_SECRET=xxx

BANK_TRANSFER_ENABLED=true
```

3. **Start the server:**
```bash
php artisan serve
```

4. **Access checkout page:**
```
http://localhost:8000/checkout
```

## 📡 API Endpoints

### Process Payment
```http
POST /api/payments/process
Content-Type: application/json
Idempotency-Key: unique-key-123

{
    "amount": 99.99,
    "currency": "USD",
    "payment_method": "credit_card",
    "preferred_gateway": "stripe",
    "country_code": "US",
    "order_id": "ORD-12345"
}
```

**Response:**
```json
{
    "success": true,
    "message": "Payment processed successfully",
    "payment": {
        "uuid": "550e8400-e29b-41d4-a716-446655440000",
        "status": "success",
        "amount": "99.99",
        "currency": "USD",
        "gateway": "stripe",
        "transaction_id": "pi_xxx",
        "processed_at": "2024-01-15T10:30:00Z"
    }
}
```

### Get Payment Status
```http
GET /api/payments/{uuid}
```

### Process Refund
```http
POST /api/payments/{uuid}/refund
Content-Type: application/json

{
    "amount": 50.00,
    "reason": "Customer request"
}
```

### Handle Webhook
```http
POST /api/payments/webhook/{gateway}
```

### Get Available Payment Methods
```http
GET /api/payments/methods?country_code=US&amount=100
```

## 🔄 Payment Flow

### Sequence Diagram

```
Client              Controller         PaymentService        GatewayRouter         Context           Gateway
  │                     │                    │                    │                   │                 │
  │──POST /process─────▶│                    │                    │                   │                 │
  │                     │─processPayment()──▶│                    │                   │                 │
  │                     │                    │─checkIdempotency()─│                   │                 │
  │                     │                    │                    │                   │                 │
  │                     │                    │─createPayment()────│                   │                 │
  │                     │                    │                    │                   │                 │
  │                     │                    │─getOrderedGateways()─────────────────▶│                 │
  │                     │                    │◀─[Stripe, PayPal, Bank]───────────────│                 │
  │                     │                    │                    │                   │                 │
  │                     │                    │─setStrategy(Stripe)──────────────────────────────────▶│
  │                     │                    │─processPayment()────────────────────────────────────▶│
  │                     │                    │                    │                   │      (API call) │
  │                     │                    │◀─PaymentResponse───────────────────────────────────────│
  │                     │                    │                    │                   │                 │
  │                     │                    │ [If failed & retryable]                │                 │
  │                     │                    │─setStrategy(PayPal)──────────────────────────────────▶│
  │                     │                    │─processPayment()────────────────────────────────────▶│
  │                     │                    │                    │                   │                 │
  │                     │◀─Result────────────│                    │                   │                 │
  │◀─JSON Response──────│                    │                    │                   │                 │
```

## ✨ Features

### 1. Multi-Gateway Support
- **Stripe**: Credit/debit card payments
- **PayPal**: PayPal account payments
- **Bank Transfer**: ACH/bank transfers

### 2. Automatic Failover
When a payment fails with a retryable error (timeout, rate limit), the system automatically tries the next available gateway.

```php
// Max 2 retries + 1 primary = 3 attempts
$gateways = [$stripe, $paypal, $bankTransfer];
foreach ($gateways as $gateway) {
    $result = $gateway->processPayment($request);
    if ($result->success || !$result->isRetryable) {
        break;
    }
}
```

### 3. Geographic Routing
Gateways are filtered based on supported countries:
- Stripe: US, CA, GB, AU, DE, FR, JP, SG
- PayPal: US, CA, GB, AU, DE, FR, ES, IT, NL
- Bank Transfer: US, CA, GB, DE, FR, AU

### 4. Fee Optimization
For high-value transactions (>$1000), gateways are sorted by fee to minimize costs.

### 5. Idempotency
Using the `Idempotency-Key` header ensures duplicate requests return the same result:
```http
Idempotency-Key: order-123-payment-attempt-1
```

### 6. Comprehensive Logging
All transactions are logged with:
- Payment events
- Gateway requests/responses
- Webhook events
- Timing metrics

## 🧪 Testing Payments

### Mock Behavior
The gateways use mock implementations that simulate real behavior:

| Amount Ending | Behavior |
|--------------|----------|
| `.99` | Stripe fails |
| `.88` | PayPal fails |
| `.77` | Bank Transfer fails |
| Other | ~90% success rate |

### Test the Checkout
1. Visit `/checkout`
2. Select a payment method
3. Click "Pay Now"
4. View the result modal

### Test via API
```bash
# Successful payment
curl -X POST http://localhost:8000/api/payments/process \
  -H "Content-Type: application/json" \
  -H "Idempotency-Key: test-$(date +%s)" \
  -d '{"amount": 100.00, "currency": "USD", "country_code": "US"}'

# Payment that will fail (amount ends in .99)
curl -X POST http://localhost:8000/api/payments/process \
  -H "Content-Type: application/json" \
  -d '{"amount": 99.99, "currency": "USD"}'
```

### Test Webhook
```bash
curl -X POST http://localhost:8000/api/payments/simulate-webhook \
  -H "Content-Type: application/json" \
  -d '{
    "gateway": "stripe",
    "event_type": "payment_intent.succeeded",
    "transaction_id": "pi_xxx"
  }'
```

## 🔧 Adding a New Gateway

One of the main benefits of the Strategy Pattern is easy extensibility. To add a new gateway:

### 1. Add enum value
```php
// app/Enums/PaymentGateway.php
case SQUARE = 'square';
```

### 2. Create the strategy class
```php
// app/PaymentGateways/Strategies/SquareGateway.php
class SquareGateway extends AbstractPaymentGateway
{
    public function getGatewayName(): PaymentGateway
    {
        return PaymentGateway::SQUARE;
    }

    public function processPayment(PaymentRequestDTO $request): PaymentResponseDTO
    {
        // Square API integration
    }

    public function processRefund(RefundRequestDTO $request): RefundResponseDTO
    {
        // Square refund integration
    }

    public function handleWebhook(array $payload, array $headers): WebhookPayloadDTO
    {
        // Parse Square webhooks
    }

    public function verifyWebhookSignature(array $payload, array $headers): bool
    {
        // Verify Square signature
    }
}
```

### 3. Register in GatewayRouter
```php
// app/Services/Payment/GatewayRouter.php
private function registerGateways(): void
{
    $this->gateways = [
        // ... existing gateways
        PaymentGateway::SQUARE->value => new SquareGateway(),
    ];
}
```

### 4. Add configuration
```php
// config/payment.php
'square' => [
    'enabled' => env('SQUARE_ENABLED', true),
    'access_token' => env('SQUARE_ACCESS_TOKEN'),
],
```

That's it! No other code changes needed.

## 🛡️ Security Features

- **PCI Compliance**: No card data stored in database
- **Idempotent Processing**: Prevents duplicate charges
- **Webhook Signature Verification**: Each gateway verifies webhook authenticity
- **Transaction Logging**: Full audit trail

## 📊 Database Schema

### payments
| Column | Type | Description |
|--------|------|-------------|
| uuid | UUID | Public identifier |
| idempotency_key | String | Request deduplication |
| amount | Decimal | Payment amount |
| currency | String(3) | ISO currency code |
| status | Enum | pending/processing/success/failed |
| final_gateway | String | Gateway that processed payment |
| gateway_transaction_id | String | External transaction ID |

### payment_attempts
| Column | Type | Description |
|--------|------|-------------|
| payment_id | FK | Parent payment |
| gateway | String | Gateway used |
| attempt_number | Integer | Attempt sequence |
| status | Enum | Result of this attempt |
| is_retryable | Boolean | Can retry with another gateway |
| response_time_ms | Integer | API response time |

### refunds
| Column | Type | Description |
|--------|------|-------------|
| payment_id | FK | Original payment |
| gateway | String | Must match original gateway |
| amount | Decimal | Refund amount (partial supported) |
| status | Enum | pending/processing/success/failed |

## 📝 License

MIT License

<?php

namespace App\PaymentGateways\Strategies;

use App\DTOs\PaymentRequestDTO;
use App\DTOs\PaymentResponseDTO;
use App\DTOs\RefundRequestDTO;
use App\DTOs\RefundResponseDTO;
use App\DTOs\WebhookPayloadDTO;
use App\Enums\PaymentAttemptStatus;
use App\Enums\PaymentGateway;
use App\Enums\RefundStatus;
use App\PaymentGateways\AbstractPaymentGateway;
use Illuminate\Support\Str;

/**
 * Stripe Payment Gateway Strategy
 * 
 * This is a mock implementation that simulates Stripe API behavior.
 * In production, this would integrate with the actual Stripe SDK.
 */
class StripeGateway extends AbstractPaymentGateway
{
    private string $apiKey;
    private string $webhookSecret;

    protected function loadConfig(): void
    {
        $this->apiKey = config('payment.gateways.stripe.api_key', 'sk_test_mock');
        $this->webhookSecret = config('payment.gateways.stripe.webhook_secret', 'whsec_mock');
        $this->isAvailable = config('payment.gateways.stripe.enabled', true);
    }

    public function getGatewayName(): PaymentGateway
    {
        return PaymentGateway::STRIPE;
    }

    public function processPayment(PaymentRequestDTO $request): PaymentResponseDTO
    {
        $startTime = microtime(true);
        
        $this->log('Processing payment', $this->sanitizeRequest($request));

        // Simulate API call
        $this->simulateApiLatency();

        // Mock payment processing logic
        // In production, this would call Stripe's API
        $success = $this->shouldSucceed($request);
        
        $responseTimeMs = (int)((microtime(true) - $startTime) * 1000);

        if ($success) {
            $transactionId = 'pi_' . Str::random(24);
            
            return new PaymentResponseDTO(
                success: true,
                status: PaymentAttemptStatus::SUCCESS,
                gateway: $this->getGatewayName(),
                transactionId: $transactionId,
                responseTimeMs: $responseTimeMs,
                rawResponse: [
                    'id' => $transactionId,
                    'object' => 'payment_intent',
                    'amount' => (int)($request->amount * 100),
                    'currency' => strtolower($request->currency),
                    'status' => 'succeeded',
                    'created' => time(),
                ]
            );
        }

        // Simulate different failure scenarios
        $failureScenario = $this->getFailureScenario($request);

        return new PaymentResponseDTO(
            success: false,
            status: $failureScenario['status'],
            gateway: $this->getGatewayName(),
            errorCode: $failureScenario['code'],
            errorMessage: $failureScenario['message'],
            isRetryable: $failureScenario['retryable'],
            responseTimeMs: $responseTimeMs,
            rawResponse: [
                'error' => [
                    'type' => $failureScenario['type'],
                    'code' => $failureScenario['code'],
                    'message' => $failureScenario['message'],
                ]
            ]
        );
    }

    public function processRefund(RefundRequestDTO $request): RefundResponseDTO
    {
        $startTime = microtime(true);
        
        $this->log('Processing refund', [
            'refund_uuid' => $request->refundUuid,
            'original_transaction' => $request->originalTransactionId,
            'amount' => $request->amount,
        ]);

        $this->simulateApiLatency();

        // Mock: 95% success rate for refunds
        $success = rand(1, 100) <= 95;
        $responseTimeMs = (int)((microtime(true) - $startTime) * 1000);

        if ($success) {
            $refundId = 're_' . Str::random(24);
            
            return new RefundResponseDTO(
                success: true,
                status: RefundStatus::SUCCESS,
                gateway: $this->getGatewayName(),
                refundId: $refundId,
                responseTimeMs: $responseTimeMs,
                rawResponse: [
                    'id' => $refundId,
                    'object' => 'refund',
                    'amount' => (int)($request->amount * 100),
                    'currency' => strtolower($request->currency),
                    'status' => 'succeeded',
                    'payment_intent' => $request->originalTransactionId,
                ]
            );
        }

        return new RefundResponseDTO(
            success: false,
            status: RefundStatus::FAILED,
            gateway: $this->getGatewayName(),
            errorCode: 'refund_failed',
            errorMessage: 'Unable to process refund at this time',
            responseTimeMs: $responseTimeMs,
            rawResponse: [
                'error' => [
                    'type' => 'invalid_request_error',
                    'message' => 'Unable to process refund',
                ]
            ]
        );
    }

    public function handleWebhook(array $payload, array $headers): WebhookPayloadDTO
    {
        $eventType = $payload['type'] ?? 'unknown';
        $data = $payload['data']['object'] ?? [];

        // Map Stripe events to our internal events
        $internalEvent = match($eventType) {
            'payment_intent.succeeded' => 'payment.success',
            'payment_intent.payment_failed' => 'payment.failed',
            'charge.refunded' => 'refund.success',
            'charge.refund.updated' => 'refund.updated',
            default => 'unknown',
        };

        return new WebhookPayloadDTO(
            gateway: $this->getGatewayName(),
            eventType: $internalEvent,
            originalEventType: $eventType,
            transactionId: $data['id'] ?? null,
            paymentIntentId: $data['payment_intent'] ?? $data['id'] ?? null,
            amount: isset($data['amount']) ? $data['amount'] / 100 : null,
            currency: $data['currency'] ?? null,
            status: $data['status'] ?? null,
            rawPayload: $payload,
            timestamp: now(),
        );
    }

    public function verifyWebhookSignature(array $payload, array $headers): bool
    {
        // In production, verify using Stripe's webhook signature
        // For mock, check if signature header exists
        $signature = $headers['stripe-signature'] ?? $headers['HTTP_STRIPE_SIGNATURE'] ?? null;
        
        // Mock: Always return true for testing
        // In production: Use Stripe\Webhook::constructEvent()
        return true;
    }

    private function shouldSucceed(PaymentRequestDTO $request): bool
    {
        // Mock success logic based on amount
        // Amounts ending in .99 fail (for testing)
        $amountStr = number_format($request->amount, 2, '.', '');
        if (str_ends_with($amountStr, '.99')) {
            return false;
        }

        // 90% success rate for other payments
        return rand(1, 100) <= 90;
    }

    private function getFailureScenario(PaymentRequestDTO $request): array
    {
        $scenarios = [
            [
                'type' => 'card_error',
                'code' => 'card_declined',
                'message' => 'Your card was declined.',
                'status' => PaymentAttemptStatus::DECLINED,
                'retryable' => false,
            ],
            [
                'type' => 'card_error',
                'code' => 'insufficient_funds',
                'message' => 'Your card has insufficient funds.',
                'status' => PaymentAttemptStatus::DECLINED,
                'retryable' => false,
            ],
            [
                'type' => 'api_error',
                'code' => 'rate_limit',
                'message' => 'Too many requests.',
                'status' => PaymentAttemptStatus::RATE_LIMITED,
                'retryable' => true,
            ],
            [
                'type' => 'api_error',
                'code' => 'timeout',
                'message' => 'Request timed out.',
                'status' => PaymentAttemptStatus::TIMEOUT,
                'retryable' => true,
            ],
        ];

        return $scenarios[array_rand($scenarios)];
    }
}


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
 * PayPal Payment Gateway Strategy
 * 
 * Mock implementation simulating PayPal API behavior.
 * In production, this would integrate with PayPal SDK.
 */
class PayPalGateway extends AbstractPaymentGateway
{
    private string $clientId;
    private string $clientSecret;
    private string $webhookId;

    protected function loadConfig(): void
    {
        $this->clientId = config('payment.gateways.paypal.client_id', 'mock_client_id');
        $this->clientSecret = config('payment.gateways.paypal.client_secret', 'mock_client_secret');
        $this->webhookId = config('payment.gateways.paypal.webhook_id', 'mock_webhook_id');
        $this->isAvailable = config('payment.gateways.paypal.enabled', true);
    }

    public function getGatewayName(): PaymentGateway
    {
        return PaymentGateway::PAYPAL;
    }

    public function processPayment(PaymentRequestDTO $request): PaymentResponseDTO
    {
        $startTime = microtime(true);
        
        $this->log('Processing PayPal payment', $this->sanitizeRequest($request));

        $this->simulateApiLatency();

        $success = $this->shouldSucceed($request);
        $responseTimeMs = (int)((microtime(true) - $startTime) * 1000);

        if ($success) {
            $captureId = 'PAYID-' . strtoupper(Str::random(20));
            
            return new PaymentResponseDTO(
                success: true,
                status: PaymentAttemptStatus::SUCCESS,
                gateway: $this->getGatewayName(),
                transactionId: $captureId,
                responseTimeMs: $responseTimeMs,
                rawResponse: [
                    'id' => $captureId,
                    'status' => 'COMPLETED',
                    'purchase_units' => [
                        [
                            'amount' => [
                                'currency_code' => $request->currency,
                                'value' => number_format($request->amount, 2, '.', ''),
                            ],
                            'payments' => [
                                'captures' => [
                                    [
                                        'id' => $captureId,
                                        'status' => 'COMPLETED',
                                        'amount' => [
                                            'currency_code' => $request->currency,
                                            'value' => number_format($request->amount, 2, '.', ''),
                                        ],
                                    ]
                                ]
                            ]
                        ]
                    ],
                    'create_time' => now()->toIso8601String(),
                ]
            );
        }

        $failureScenario = $this->getFailureScenario();

        return new PaymentResponseDTO(
            success: false,
            status: $failureScenario['status'],
            gateway: $this->getGatewayName(),
            errorCode: $failureScenario['code'],
            errorMessage: $failureScenario['message'],
            isRetryable: $failureScenario['retryable'],
            responseTimeMs: $responseTimeMs,
            rawResponse: [
                'name' => $failureScenario['name'],
                'message' => $failureScenario['message'],
                'debug_id' => Str::random(16),
            ]
        );
    }

    public function processRefund(RefundRequestDTO $request): RefundResponseDTO
    {
        $startTime = microtime(true);
        
        $this->log('Processing PayPal refund', [
            'refund_uuid' => $request->refundUuid,
            'original_transaction' => $request->originalTransactionId,
            'amount' => $request->amount,
        ]);

        $this->simulateApiLatency();

        $success = rand(1, 100) <= 93;
        $responseTimeMs = (int)((microtime(true) - $startTime) * 1000);

        if ($success) {
            $refundId = 'REFUND-' . strtoupper(Str::random(17));
            
            return new RefundResponseDTO(
                success: true,
                status: RefundStatus::SUCCESS,
                gateway: $this->getGatewayName(),
                refundId: $refundId,
                responseTimeMs: $responseTimeMs,
                rawResponse: [
                    'id' => $refundId,
                    'status' => 'COMPLETED',
                    'amount' => [
                        'currency_code' => $request->currency,
                        'value' => number_format($request->amount, 2, '.', ''),
                    ],
                ]
            );
        }

        return new RefundResponseDTO(
            success: false,
            status: RefundStatus::FAILED,
            gateway: $this->getGatewayName(),
            errorCode: 'REFUND_FAILED',
            errorMessage: 'Refund could not be processed',
            responseTimeMs: $responseTimeMs,
            rawResponse: [
                'name' => 'UNPROCESSABLE_ENTITY',
                'message' => 'Refund could not be processed',
            ]
        );
    }

    public function handleWebhook(array $payload, array $headers): WebhookPayloadDTO
    {
        $eventType = $payload['event_type'] ?? 'unknown';
        $resource = $payload['resource'] ?? [];

        $internalEvent = match($eventType) {
            'PAYMENT.CAPTURE.COMPLETED' => 'payment.success',
            'PAYMENT.CAPTURE.DENIED' => 'payment.failed',
            'PAYMENT.CAPTURE.REFUNDED' => 'refund.success',
            default => 'unknown',
        };

        return new WebhookPayloadDTO(
            gateway: $this->getGatewayName(),
            eventType: $internalEvent,
            originalEventType: $eventType,
            transactionId: $resource['id'] ?? null,
            paymentIntentId: $resource['id'] ?? null,
            amount: isset($resource['amount']['value']) ? (float)$resource['amount']['value'] : null,
            currency: $resource['amount']['currency_code'] ?? null,
            status: $resource['status'] ?? null,
            rawPayload: $payload,
            timestamp: now(),
        );
    }

    public function verifyWebhookSignature(array $payload, array $headers): bool
    {
        // In production: Verify using PayPal's webhook signature verification
        // For mock: Always return true
        $signature = $headers['paypal-transmission-sig'] ?? null;
        return true;
    }

    private function shouldSucceed(PaymentRequestDTO $request): bool
    {
        // Mock: Amounts ending in .88 fail
        $amountStr = number_format($request->amount, 2, '.', '');
        if (str_ends_with($amountStr, '.88')) {
            return false;
        }

        // 88% success rate
        return rand(1, 100) <= 88;
    }

    private function getFailureScenario(): array
    {
        $scenarios = [
            [
                'name' => 'INSTRUMENT_DECLINED',
                'code' => 'instrument_declined',
                'message' => 'The payment source was declined by the processor.',
                'status' => PaymentAttemptStatus::DECLINED,
                'retryable' => false,
            ],
            [
                'name' => 'PAYER_ACTION_REQUIRED',
                'code' => 'payer_action_required',
                'message' => 'Payer needs to complete action at PayPal.',
                'status' => PaymentAttemptStatus::FAILED,
                'retryable' => false,
            ],
            [
                'name' => 'INTERNAL_SERVICE_ERROR',
                'code' => 'internal_error',
                'message' => 'An internal service error has occurred.',
                'status' => PaymentAttemptStatus::TIMEOUT,
                'retryable' => true,
            ],
            [
                'name' => 'RATE_LIMIT_REACHED',
                'code' => 'rate_limit',
                'message' => 'Too many requests. Please slow down.',
                'status' => PaymentAttemptStatus::RATE_LIMITED,
                'retryable' => true,
            ],
        ];

        return $scenarios[array_rand($scenarios)];
    }
}


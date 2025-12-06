<?php

namespace App\Services\Payment;

use App\DTOs\PaymentRequestDTO;
use App\DTOs\PaymentResponseDTO;
use App\Enums\PaymentAttemptStatus;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\PaymentAttempt;
use App\Models\TransactionLog;
use App\PaymentGateways\PaymentGatewayContext;
use App\PaymentGateways\PaymentGatewayInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Payment Service
 *
 * Orchestrates the payment flow:
 * 1. Idempotency check
 * 2. Create payment record
 * 3. Route to appropriate gateway(s)
 * 4. Handle retries with backup gateways
 * 5. Log all transactions
 */
class PaymentService
{
    private PaymentGatewayContext $gatewayContext;

    public function __construct(
        private readonly GatewayRouter $router,
        private readonly IdempotencyService $idempotencyService,
    ) {
        $this->gatewayContext = new PaymentGatewayContext();
    }

    /**
     * Process a payment with automatic retry and gateway failover
     */
    public function processPayment(array $data): array
    {
        $idempotencyKey = $data['idempotency_key'] ?? null;

        // Step 1: Check idempotency
        if ($idempotencyKey) {
            $existingResult = $this->idempotencyService->check($idempotencyKey);
            if ($existingResult) {
                if ($existingResult['status'] === 'completed') {
                    return $existingResult['response'];
                }
                // Still processing
                return [
                    'success' => false,
                    'message' => 'Request is already being processed',
                    'retry_after' => $existingResult['retry_after'] ?? 5,
                ];
            }
        }

        // Step 2: Create payment record
        $payment = $this->createPayment($data);

        // Lock for idempotency
        if ($idempotencyKey) {
            $requestDTO = PaymentRequestDTO::fromArray(array_merge($data, ['payment_uuid' => $payment->uuid]));
            $this->idempotencyService->lock($idempotencyKey, $requestDTO->getRequestHash());
        }

        try {
            // Step 3: Get ordered gateways
                $gateways = $this->router->getOrderedGateways(
                preferredGateway: $payment->preferred_gateway,
                countryCode: $payment->country_code,
                amount: (float) $payment->amount,
                paymentMethod: $payment->payment_method ? \App\Enums\PaymentMethod::tryFrom($payment->payment_method) : null
            );

            if (empty($gateways)) {
                $this->markPaymentFailed($payment, 'No available payment gateway for this request');
                $result = $this->buildErrorResponse($payment, 'No available payment gateway');

                if ($idempotencyKey) {
                    $this->idempotencyService->storeResponse($idempotencyKey, $result, 400);
                }

                return $result;
            }

            // Step 4: Attempt payment with failover
            $result = $this->attemptPaymentWithFailover($payment, $gateways);

            // Store result for idempotency
            if ($idempotencyKey) {
                $statusCode = $result['success'] ? 200 : 400;
                $this->idempotencyService->storeResponse($idempotencyKey, $result, $statusCode);
            }

            return $result;

        } catch (\Throwable $e) {
            $this->markPaymentFailed($payment, $e->getMessage());

            if ($idempotencyKey) {
                $this->idempotencyService->unlock($idempotencyKey);
            }

            throw $e;
        }
    }

    /**
     * Attempt payment with automatic failover to backup gateways
     *
     * @param Payment $payment
     * @param PaymentGatewayInterface[] $gateways
     */
    private function attemptPaymentWithFailover(Payment $payment, array $gateways): array
    {
        $maxAttempts = min(count($gateways), $payment->max_retries + 1);
        $lastResponse = null;

        $payment->markAsProcessing();

        $this->logEvent($payment, 'payment.initiated', null, [
            'available_gateways' => array_map(fn($g) => $g->getGatewayName()->value, $gateways),
            'max_attempts' => $maxAttempts,
        ]);

        for ($i = 0; $i < $maxAttempts; $i++) {
            $gateway = $gateways[$i];
            $this->gatewayContext->setStrategy($gateway);

            $this->logEvent($payment, 'gateway.selected', $gateway->getGatewayName()->value, [
                'attempt_number' => $i + 1,
            ]);

            $request = new PaymentRequestDTO(
                paymentUuid: $payment->uuid,
                amount: (float) $payment->amount,
                currency: $payment->currency,
                orderId: $payment->order_id,
                userId: $payment->user_id,
                countryCode: $payment->country_code,
                ipAddress: $payment->ip_address,
            );

            // Process through current gateway
            $response = $this->gatewayContext->processPayment($request);
            $lastResponse = $response;

            // Record this attempt
            $this->recordAttempt($payment, $gateway, $response);
            $payment->incrementAttemptCount();

            $this->logEvent($payment, 'gateway.response', $gateway->getGatewayName()->value, [
                'success' => $response->success,
                'status' => $response->status->value,
                'response_time_ms' => $response->responseTimeMs,
            ]);

            // Success!
            if ($response->success) {
                $payment->markAsSuccess(
                    $gateway->getGatewayName()->value,
                    $response->transactionId,
                    $response->rawResponse
                );

                return $this->buildSuccessResponse($payment, $response);
            }

            // Non-retryable failure (card declined, etc.)
            if (!$response->shouldRetry()) {
                $this->markPaymentFailed($payment, $response->errorMessage ?? 'Payment declined');
                return $this->buildErrorResponse($payment, $response->errorMessage, $response);
            }

            // Retryable failure - try next gateway
            $this->logEvent($payment, 'gateway.retry', $gateway->getGatewayName()->value, [
                'reason' => $response->errorMessage,
                'next_attempt' => $i + 2,
            ]);
        }

        // All attempts exhausted
        $this->markPaymentFailed($payment, 'All payment gateways failed');
        return $this->buildErrorResponse($payment, 'Payment failed after all retry attempts', $lastResponse);
    }

    /**
     * Create initial payment record
     */
    private function createPayment(array $data): Payment
    {
        return DB::transaction(function () use ($data) {
            $payment = Payment::create([
                'uuid' => (string) Str::uuid(),
                'idempotency_key' => $data['idempotency_key'] ?? null,
                'user_id' => $data['user_id'] ?? null,
                'order_id' => $data['order_id'] ?? null,
                'amount' => $data['amount'],
                'currency' => strtoupper($data['currency'] ?? 'USD'),
                'payment_method' => $data['payment_method'] ?? null,
                'preferred_gateway' => $data['preferred_gateway'] ?? null,
                'status' => PaymentStatus::PENDING,
                'country_code' => $data['country_code'] ?? null,
                'ip_address' => $data['ip_address'] ?? null,
                'max_retries' => $data['max_retries'] ?? 2,
            ]);

            $this->logEvent($payment, 'payment.created', null, [
                'amount' => $payment->amount,
                'currency' => $payment->currency,
            ]);

            return $payment;
        });
    }

    /**
     * Record a payment attempt
     */
    private function recordAttempt(Payment $payment, PaymentGatewayInterface $gateway, PaymentResponseDTO $response): PaymentAttempt
    {
        return PaymentAttempt::createForPayment(
            payment: $payment,
            gateway: $gateway->getGatewayName()->value,
            status: $response->status,
            errorCode: $response->errorCode,
            errorMessage: $response->errorMessage,
            isRetryable: $response->isRetryable,
            transactionId: $response->transactionId,
            request: ['amount' => $payment->amount, 'currency' => $payment->currency],
            response: $response->rawResponse,
            responseTimeMs: $response->responseTimeMs
        );
    }

    /**
     * Mark payment as failed
     */
    private function markPaymentFailed(Payment $payment, string $reason): void
    {
        $payment->markAsFailed($reason);
        $this->logEvent($payment, 'payment.failed', $payment->final_gateway, [
            'reason' => $reason,
        ]);
    }

    /**
     * Log transaction event
     */
    private function logEvent(Payment $payment, string $event, ?string $gateway, array $data = []): void
    {
        TransactionLog::logEvent(
            loggable: $payment,
            event: $event,
            gateway: $gateway,
            data: $data,
            ipAddress: $payment->ip_address
        );
    }

    /**
     * Build success response
     */
    private function buildSuccessResponse(Payment $payment, PaymentResponseDTO $response): array
    {
        return [
            'success' => true,
            'message' => 'Payment processed successfully',
            'payment' => [
                'uuid' => $payment->uuid,
                'status' => $payment->status->value,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'gateway' => $payment->final_gateway,
                'transaction_id' => $response->transactionId,
                'processed_at' => $payment->processed_at?->toIso8601String(),
            ],
        ];
    }

    /**
     * Build error response
     */
    private function buildErrorResponse(Payment $payment, ?string $message, ?PaymentResponseDTO $response = null): array
    {
        return [
            'success' => false,
            'message' => $message ?? 'Payment failed',
            'payment' => [
                'uuid' => $payment->uuid,
                'status' => $payment->status->value,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
            ],
            'error' => $response ? [
                'code' => $response->errorCode,
                'message' => $response->errorMessage,
                'is_retryable' => $response->isRetryable,
            ] : null,
        ];
    }

    /**
     * Get payment by UUID
     */
    public function getPayment(string $uuid): ?Payment
    {
        return Payment::where('uuid', $uuid)->first();
    }

    /**
     * Get payment with attempts
     */
    public function getPaymentWithAttempts(string $uuid): ?Payment
    {
        return Payment::with(['attempts', 'refunds'])->where('uuid', $uuid)->first();
    }
}


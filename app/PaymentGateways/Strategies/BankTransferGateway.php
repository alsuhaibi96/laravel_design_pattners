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
 * Bank Transfer Payment Gateway Strategy
 * 
 * Mock implementation for bank transfer/ACH payments.
 * In production, would integrate with banking APIs like Plaid, Dwolla, etc.
 */
class BankTransferGateway extends AbstractPaymentGateway
{
    private string $apiKey;
    private string $accountId;

    protected function loadConfig(): void
    {
        $this->apiKey = config('payment.gateways.bank_transfer.api_key', 'mock_bank_key');
        $this->accountId = config('payment.gateways.bank_transfer.account_id', 'mock_account');
        $this->isAvailable = config('payment.gateways.bank_transfer.enabled', true);
    }

    public function getGatewayName(): PaymentGateway
    {
        return PaymentGateway::BANK_TRANSFER;
    }

    public function processPayment(PaymentRequestDTO $request): PaymentResponseDTO
    {
        $startTime = microtime(true);
        
        $this->log('Processing bank transfer', $this->sanitizeRequest($request));

        // Bank transfers typically take longer
        usleep(rand(200000, 800000)); // 200-800ms

        $success = $this->shouldSucceed($request);
        $responseTimeMs = (int)((microtime(true) - $startTime) * 1000);

        if ($success) {
            $transferId = 'BTR-' . date('Ymd') . '-' . strtoupper(Str::random(8));
            
            return new PaymentResponseDTO(
                success: true,
                status: PaymentAttemptStatus::SUCCESS,
                gateway: $this->getGatewayName(),
                transactionId: $transferId,
                responseTimeMs: $responseTimeMs,
                rawResponse: [
                    'transfer_id' => $transferId,
                    'status' => 'completed',
                    'amount' => $request->amount,
                    'currency' => $request->currency,
                    'type' => 'ach_debit',
                    'created_at' => now()->toIso8601String(),
                    'estimated_arrival' => now()->addWeekdays(2)->toDateString(),
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
                'error_code' => $failureScenario['code'],
                'error_message' => $failureScenario['message'],
            ]
        );
    }

    public function processRefund(RefundRequestDTO $request): RefundResponseDTO
    {
        $startTime = microtime(true);
        
        $this->log('Processing bank transfer refund', [
            'refund_uuid' => $request->refundUuid,
            'original_transaction' => $request->originalTransactionId,
            'amount' => $request->amount,
        ]);

        usleep(rand(200000, 600000));

        $success = rand(1, 100) <= 90;
        $responseTimeMs = (int)((microtime(true) - $startTime) * 1000);

        if ($success) {
            $refundId = 'BTR-REF-' . date('Ymd') . '-' . strtoupper(Str::random(6));
            
            return new RefundResponseDTO(
                success: true,
                status: RefundStatus::SUCCESS,
                gateway: $this->getGatewayName(),
                refundId: $refundId,
                responseTimeMs: $responseTimeMs,
                rawResponse: [
                    'refund_id' => $refundId,
                    'status' => 'completed',
                    'amount' => $request->amount,
                    'currency' => $request->currency,
                    'original_transfer' => $request->originalTransactionId,
                    'estimated_arrival' => now()->addWeekdays(3)->toDateString(),
                ]
            );
        }

        return new RefundResponseDTO(
            success: false,
            status: RefundStatus::FAILED,
            gateway: $this->getGatewayName(),
            errorCode: 'refund_failed',
            errorMessage: 'Bank transfer refund could not be initiated',
            responseTimeMs: $responseTimeMs,
            rawResponse: [
                'error_code' => 'refund_failed',
                'error_message' => 'Refund initiation failed',
            ]
        );
    }

    public function handleWebhook(array $payload, array $headers): WebhookPayloadDTO
    {
        $eventType = $payload['event'] ?? 'unknown';
        $data = $payload['data'] ?? [];

        $internalEvent = match($eventType) {
            'transfer.completed' => 'payment.success',
            'transfer.failed' => 'payment.failed',
            'transfer.returned' => 'payment.failed',
            'refund.completed' => 'refund.success',
            'refund.failed' => 'refund.failed',
            default => 'unknown',
        };

        return new WebhookPayloadDTO(
            gateway: $this->getGatewayName(),
            eventType: $internalEvent,
            originalEventType: $eventType,
            transactionId: $data['transfer_id'] ?? null,
            paymentIntentId: $data['transfer_id'] ?? null,
            amount: $data['amount'] ?? null,
            currency: $data['currency'] ?? null,
            status: $data['status'] ?? null,
            rawPayload: $payload,
            timestamp: now(),
        );
    }

    public function verifyWebhookSignature(array $payload, array $headers): bool
    {
        // In production: Verify HMAC signature
        $signature = $headers['x-bank-signature'] ?? null;
        return true;
    }

    private function shouldSucceed(PaymentRequestDTO $request): bool
    {
        // Mock: Amounts ending in .77 fail
        $amountStr = number_format($request->amount, 2, '.', '');
        if (str_ends_with($amountStr, '.77')) {
            return false;
        }

        // Check minimum amount for bank transfers
        if ($request->amount < 10.00) {
            return false;
        }

        // 92% success rate
        return rand(1, 100) <= 92;
    }

    private function getFailureScenario(): array
    {
        $scenarios = [
            [
                'code' => 'insufficient_funds',
                'message' => 'Account has insufficient funds.',
                'status' => PaymentAttemptStatus::DECLINED,
                'retryable' => false,
            ],
            [
                'code' => 'invalid_account',
                'message' => 'Bank account could not be verified.',
                'status' => PaymentAttemptStatus::FAILED,
                'retryable' => false,
            ],
            [
                'code' => 'bank_timeout',
                'message' => 'Bank system did not respond in time.',
                'status' => PaymentAttemptStatus::TIMEOUT,
                'retryable' => true,
            ],
            [
                'code' => 'service_unavailable',
                'message' => 'Banking service temporarily unavailable.',
                'status' => PaymentAttemptStatus::RATE_LIMITED,
                'retryable' => true,
            ],
        ];

        return $scenarios[array_rand($scenarios)];
    }
}


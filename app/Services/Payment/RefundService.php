<?php

namespace App\Services\Payment;

use App\DTOs\RefundRequestDTO;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\TransactionLog;
use App\PaymentGateways\PaymentGatewayContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Refund Service
 * 
 * Handles refund processing:
 * - Validates refund eligibility
 * - Routes refund through original gateway (required)
 * - Supports partial refunds
 * - Logs all refund transactions
 */
class RefundService
{
    private PaymentGatewayContext $gatewayContext;

    public function __construct(
        private readonly GatewayRouter $router,
    ) {
        $this->gatewayContext = new PaymentGatewayContext();
    }

    /**
     * Process a refund for a payment
     */
    public function processRefund(string $paymentUuid, array $data): array
    {
        // Get the original payment
        $payment = Payment::where('uuid', $paymentUuid)->first();

        if (!$payment) {
            return [
                'success' => false,
                'message' => 'Payment not found',
            ];
        }

        // Validate refund eligibility
        $validation = $this->validateRefund($payment, $data);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'message' => $validation['message'],
            ];
        }

        $amount = (float) ($data['amount'] ?? $payment->getRefundableAmount());

        // Create refund record
        $refund = $this->createRefund($payment, $amount, $data);

        try {
            // Get original gateway - refunds MUST go through original gateway
            $gateway = $this->router->getGateway($payment->final_gateway);

            if (!$gateway) {
                $refund->markAsFailed(['error' => 'Original gateway not available']);
                return [
                    'success' => false,
                    'message' => 'Original payment gateway is not available',
                    'refund' => $this->formatRefund($refund),
                ];
            }

            $this->gatewayContext->setStrategy($gateway);

            $this->logEvent($refund, 'refund.initiated', $payment->final_gateway, [
                'amount' => $amount,
                'original_payment' => $payment->uuid,
            ]);

            $refund->markAsProcessing();

            // Process refund through original gateway
            $request = new RefundRequestDTO(
                refundUuid: $refund->uuid,
                paymentUuid: $payment->uuid,
                originalTransactionId: $payment->gateway_transaction_id,
                amount: $amount,
                currency: $payment->currency,
                reason: $data['reason'] ?? null,
                initiatedBy: $data['initiated_by'] ?? null,
            );

            $response = $this->gatewayContext->processRefund($request);

            $this->logEvent($refund, 'refund.response', $payment->final_gateway, [
                'success' => $response->success,
                'response_time_ms' => $response->responseTimeMs,
            ]);

            if ($response->success) {
                $refund->markAsSuccess($response->refundId, $response->rawResponse);
                $this->updatePaymentRefundStatus($payment);

                return [
                    'success' => true,
                    'message' => 'Refund processed successfully',
                    'refund' => $this->formatRefund($refund),
                ];
            }

            $refund->markAsFailed($response->rawResponse);

            return [
                'success' => false,
                'message' => $response->errorMessage ?? 'Refund failed',
                'refund' => $this->formatRefund($refund),
                'error' => [
                    'code' => $response->errorCode,
                    'message' => $response->errorMessage,
                ],
            ];

        } catch (\Throwable $e) {
            $refund->markAsFailed(['error' => $e->getMessage()]);
            
            $this->logEvent($refund, 'refund.error', $payment->final_gateway, [
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Validate if refund is possible
     */
    private function validateRefund(Payment $payment, array $data): array
    {
        // Check payment status
        if (!$payment->isSuccessful()) {
            return [
                'valid' => false,
                'message' => 'Can only refund successful payments',
            ];
        }

        // Check if gateway transaction exists
        if (empty($payment->gateway_transaction_id)) {
            return [
                'valid' => false,
                'message' => 'Payment has no gateway transaction ID',
            ];
        }

        // Check refundable amount
        $requestedAmount = (float) ($data['amount'] ?? $payment->getRefundableAmount());
        $refundableAmount = $payment->getRefundableAmount();

        if ($requestedAmount <= 0) {
            return [
                'valid' => false,
                'message' => 'Refund amount must be greater than zero',
            ];
        }

        if ($requestedAmount > $refundableAmount) {
            return [
                'valid' => false,
                'message' => "Refund amount exceeds refundable amount ($refundableAmount)",
            ];
        }

        return ['valid' => true];
    }

    /**
     * Create refund record
     */
    private function createRefund(Payment $payment, float $amount, array $data): Refund
    {
        return DB::transaction(function () use ($payment, $amount, $data) {
            $refund = Refund::create([
                'uuid' => (string) Str::uuid(),
                'payment_id' => $payment->id,
                'gateway' => $payment->final_gateway,
                'amount' => $amount,
                'currency' => $payment->currency,
                'status' => RefundStatus::PENDING,
                'reason' => $data['reason'] ?? null,
                'notes' => $data['notes'] ?? null,
                'initiated_by' => $data['initiated_by'] ?? null,
            ]);

            $this->logEvent($refund, 'refund.created', $payment->final_gateway, [
                'amount' => $amount,
                'payment_uuid' => $payment->uuid,
            ]);

            return $refund;
        });
    }

    /**
     * Update payment status after refund
     */
    private function updatePaymentRefundStatus(Payment $payment): void
    {
        $totalRefunded = $payment->getTotalRefundedAmount();

        if ($totalRefunded >= (float) $payment->amount) {
            $payment->update(['status' => PaymentStatus::REFUNDED]);
        } else {
            $payment->update(['status' => PaymentStatus::PARTIALLY_REFUNDED]);
        }
    }

    /**
     * Log refund event
     */
    private function logEvent(Refund $refund, string $event, ?string $gateway, array $data = []): void
    {
        TransactionLog::logEvent(
            loggable: $refund,
            event: $event,
            gateway: $gateway,
            data: $data
        );
    }

    /**
     * Format refund for response
     */
    private function formatRefund(Refund $refund): array
    {
        return [
            'uuid' => $refund->uuid,
            'status' => $refund->status->value,
            'amount' => $refund->amount,
            'currency' => $refund->currency,
            'gateway' => $refund->gateway,
            'gateway_refund_id' => $refund->gateway_refund_id,
            'reason' => $refund->reason,
            'processed_at' => $refund->processed_at?->toIso8601String(),
            'created_at' => $refund->created_at->toIso8601String(),
        ];
    }

    /**
     * Get refund by UUID
     */
    public function getRefund(string $uuid): ?Refund
    {
        return Refund::where('uuid', $uuid)->first();
    }

    /**
     * Get refunds for a payment
     */
    public function getRefundsForPayment(string $paymentUuid): array
    {
        $payment = Payment::where('uuid', $paymentUuid)->first();
        
        if (!$payment) {
            return [];
        }

        return $payment->refunds->map(fn($r) => $this->formatRefund($r))->all();
    }
}


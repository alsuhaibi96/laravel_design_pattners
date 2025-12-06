<?php

namespace App\Services\Payment;

use App\DTOs\WebhookPayloadDTO;
use App\Enums\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\TransactionLog;
use App\PaymentGateways\PaymentGatewayContext;
use Illuminate\Support\Facades\Log;

/**
 * Webhook Service
 * 
 * Handles incoming webhooks from payment gateways:
 * - Signature verification
 * - Event normalization
 * - State updates
 * - Idempotent processing
 */
class WebhookService
{
    private PaymentGatewayContext $gatewayContext;

    public function __construct(
        private readonly GatewayRouter $router,
    ) {
        $this->gatewayContext = new PaymentGatewayContext();
    }

    /**
     * Process incoming webhook
     */
    public function processWebhook(string $gatewayName, array $payload, array $headers): array
    {
        $gateway = PaymentGateway::tryFrom($gatewayName);

        if (!$gateway) {
            return [
                'success' => false,
                'message' => 'Unknown gateway',
            ];
        }

        $gatewayStrategy = $this->router->getGateway($gateway);

        if (!$gatewayStrategy) {
            return [
                'success' => false,
                'message' => 'Gateway not configured',
            ];
        }

        $this->gatewayContext->setStrategy($gatewayStrategy);

        // Verify signature
        if (!$this->gatewayContext->verifyWebhookSignature($payload, $headers)) {
            Log::channel('payment')->warning('Webhook signature verification failed', [
                'gateway' => $gatewayName,
            ]);

            return [
                'success' => false,
                'message' => 'Invalid signature',
            ];
        }

        // Parse webhook payload
        $webhookData = $this->gatewayContext->handleWebhook($payload, $headers);

        // Log the webhook
        $this->logWebhook($gatewayName, $webhookData);

        // Handle based on event type
        return $this->handleEvent($webhookData);
    }

    /**
     * Handle webhook event
     */
    private function handleEvent(WebhookPayloadDTO $webhook): array
    {
        return match (true) {
            $webhook->isPaymentSuccess() => $this->handlePaymentSuccess($webhook),
            $webhook->isPaymentFailed() => $this->handlePaymentFailed($webhook),
            $webhook->isRefundEvent() => $this->handleRefundEvent($webhook),
            default => $this->handleUnknownEvent($webhook),
        };
    }

    /**
     * Handle payment success webhook
     */
    private function handlePaymentSuccess(WebhookPayloadDTO $webhook): array
    {
        $payment = $this->findPaymentByTransactionId($webhook->transactionId);

        if (!$payment) {
            return [
                'success' => true,
                'message' => 'Payment not found, webhook acknowledged',
                'transaction_id' => $webhook->transactionId,
            ];
        }

        // Idempotent: Skip if already successful
        if ($payment->isSuccessful()) {
            return [
                'success' => true,
                'message' => 'Payment already marked as successful',
                'payment_uuid' => $payment->uuid,
            ];
        }

        // Update payment status
        $payment->update([
            'status' => PaymentStatus::SUCCESS,
            'processed_at' => $webhook->timestamp ?? now(),
        ]);

        TransactionLog::logEvent(
            loggable: $payment,
            event: 'webhook.payment_success',
            gateway: $webhook->gateway->value,
            data: $webhook->toArray()
        );

        return [
            'success' => true,
            'message' => 'Payment status updated',
            'payment_uuid' => $payment->uuid,
        ];
    }

    /**
     * Handle payment failed webhook
     */
    private function handlePaymentFailed(WebhookPayloadDTO $webhook): array
    {
        $payment = $this->findPaymentByTransactionId($webhook->transactionId);

        if (!$payment) {
            return [
                'success' => true,
                'message' => 'Payment not found, webhook acknowledged',
            ];
        }

        // Don't override success status
        if ($payment->isSuccessful()) {
            return [
                'success' => true,
                'message' => 'Payment is successful, ignoring failure webhook',
                'payment_uuid' => $payment->uuid,
            ];
        }

        $payment->update([
            'status' => PaymentStatus::FAILED,
            'failure_reason' => 'Failed via webhook: ' . ($webhook->status ?? 'unknown'),
            'processed_at' => $webhook->timestamp ?? now(),
        ]);

        TransactionLog::logEvent(
            loggable: $payment,
            event: 'webhook.payment_failed',
            gateway: $webhook->gateway->value,
            data: $webhook->toArray()
        );

        return [
            'success' => true,
            'message' => 'Payment marked as failed',
            'payment_uuid' => $payment->uuid,
        ];
    }

    /**
     * Handle refund webhook events
     */
    private function handleRefundEvent(WebhookPayloadDTO $webhook): array
    {
        // Find refund by gateway refund ID
        $refund = Refund::where('gateway_refund_id', $webhook->transactionId)->first();

        if (!$refund) {
            return [
                'success' => true,
                'message' => 'Refund not found, webhook acknowledged',
            ];
        }

        $isSuccess = str_contains($webhook->eventType, 'success');

        if ($isSuccess && !$refund->isSuccessful()) {
            $refund->update([
                'status' => RefundStatus::SUCCESS,
                'processed_at' => $webhook->timestamp ?? now(),
            ]);
        }

        TransactionLog::logEvent(
            loggable: $refund,
            event: 'webhook.refund.' . ($isSuccess ? 'success' : 'updated'),
            gateway: $webhook->gateway->value,
            data: $webhook->toArray()
        );

        return [
            'success' => true,
            'message' => 'Refund webhook processed',
            'refund_uuid' => $refund->uuid,
        ];
    }

    /**
     * Handle unknown webhook events
     */
    private function handleUnknownEvent(WebhookPayloadDTO $webhook): array
    {
        Log::channel('payment')->info('Unknown webhook event', [
            'gateway' => $webhook->gateway->value,
            'event_type' => $webhook->eventType,
            'original_event' => $webhook->originalEventType,
        ]);

        return [
            'success' => true,
            'message' => 'Webhook acknowledged',
            'event_type' => $webhook->originalEventType,
        ];
    }

    /**
     * Find payment by gateway transaction ID
     */
    private function findPaymentByTransactionId(?string $transactionId): ?Payment
    {
        if (!$transactionId) {
            return null;
        }

        return Payment::where('gateway_transaction_id', $transactionId)->first();
    }

    /**
     * Log webhook for audit
     */
    private function logWebhook(string $gateway, WebhookPayloadDTO $webhook): void
    {
        Log::channel('payment')->info('Webhook received', [
            'gateway' => $gateway,
            'event_type' => $webhook->eventType,
            'original_event' => $webhook->originalEventType,
            'transaction_id' => $webhook->transactionId,
        ]);
    }
}


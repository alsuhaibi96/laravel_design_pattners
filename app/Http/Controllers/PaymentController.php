<?php

namespace App\Http\Controllers;

use App\Services\Payment\GatewayRouter;
use App\Services\Payment\PaymentService;
use App\Services\Payment\RefundService;
use App\Services\Payment\WebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Payment Controller
 * 
 * Handles all payment-related HTTP endpoints:
 * - Process payment
 * - Handle webhooks
 * - Process refunds
 * - Get payment status
 */
class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentService $paymentService,
        private readonly RefundService $refundService,
        private readonly WebhookService $webhookService,
        private readonly GatewayRouter $gatewayRouter,
    ) {}

    /**
     * Process a new payment
     * 
     * POST /api/payments/process
     */
    public function processPayment(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'sometimes|string|size:3',
            'payment_method' => 'sometimes|string|in:credit_card,debit_card,paypal,bank_transfer',
            'preferred_gateway' => 'sometimes|string|in:stripe,paypal,bank_transfer',
            'order_id' => 'sometimes|string|max:255',
            'country_code' => 'sometimes|string|size:2',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $data['idempotency_key'] = $request->header('Idempotency-Key');
        $data['ip_address'] = $request->ip();
        $data['user_id'] = $request->user()?->id;

        try {
            $result = $this->paymentService->processPayment($data);
            
            $statusCode = $result['success'] ? 200 : 400;
            return response()->json($result, $statusCode);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Payment processing failed',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal error',
            ], 500);
        }
    }

    /**
     * Handle webhook from payment gateway
     * 
     * POST /api/payments/webhook/{gateway}
     */
    public function handleWebhook(Request $request, string $gateway): JsonResponse
    {
        $payload = $request->all();
        $headers = $request->headers->all();

        // Flatten headers array
        $flatHeaders = [];
        foreach ($headers as $key => $values) {
            $flatHeaders[$key] = is_array($values) ? $values[0] : $values;
        }

        try {
            $result = $this->webhookService->processWebhook($gateway, $payload, $flatHeaders);
            
            // Webhooks should return 200 to acknowledge receipt
            return response()->json($result, 200);

        } catch (\Throwable $e) {
            // Log but still return 200 to avoid retries for our errors
            \Log::channel('payment')->error('Webhook processing error', [
                'gateway' => $gateway,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Webhook processing error',
            ], 200);
        }
    }

    /**
     * Process a refund
     * 
     * POST /api/payments/{paymentId}/refund
     */
    public function processRefund(Request $request, string $paymentId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'sometimes|numeric|min:0.01',
            'reason' => 'sometimes|string|max:500',
            'notes' => 'sometimes|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $data['initiated_by'] = $request->user()?->id;

        try {
            $result = $this->refundService->processRefund($paymentId, $data);
            
            $statusCode = $result['success'] ? 200 : 400;
            return response()->json($result, $statusCode);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Refund processing failed',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal error',
            ], 500);
        }
    }

    /**
     * Get payment status
     * 
     * GET /api/payments/{paymentId}
     */
    public function getPayment(string $paymentId): JsonResponse
    {
        $payment = $this->paymentService->getPaymentWithAttempts($paymentId);

        if (!$payment) {
            return response()->json([
                'success' => false,
                'message' => 'Payment not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'payment' => [
                'uuid' => $payment->uuid,
                'status' => $payment->status->value,
                'status_label' => $payment->status->label(),
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'payment_method' => $payment->payment_method,
                'gateway' => $payment->final_gateway,
                'transaction_id' => $payment->gateway_transaction_id,
                'order_id' => $payment->order_id,
                'attempt_count' => $payment->attempt_count,
                'failure_reason' => $payment->failure_reason,
                'processed_at' => $payment->processed_at?->toIso8601String(),
                'created_at' => $payment->created_at->toIso8601String(),
                'attempts' => $payment->attempts->map(fn($a) => [
                    'gateway' => $a->gateway,
                    'attempt_number' => $a->attempt_number,
                    'status' => $a->status->value,
                    'error_code' => $a->error_code,
                    'error_message' => $a->error_message,
                    'response_time_ms' => $a->response_time_ms,
                    'created_at' => $a->created_at->toIso8601String(),
                ]),
                'refunds' => $payment->refunds->map(fn($r) => [
                    'uuid' => $r->uuid,
                    'status' => $r->status->value,
                    'amount' => $r->amount,
                    'reason' => $r->reason,
                    'processed_at' => $r->processed_at?->toIso8601String(),
                ]),
                'refundable_amount' => $payment->getRefundableAmount(),
            ],
        ]);
    }

    /**
     * Get available payment methods for a country/amount
     * 
     * GET /api/payments/methods
     */
    public function getPaymentMethods(Request $request): JsonResponse
    {
        $countryCode = $request->query('country_code');
        $amount = $request->query('amount') ? (float) $request->query('amount') : null;

        $gateways = $this->gatewayRouter->getAvailableGateways($countryCode, $amount);

        $methods = [];
        foreach ($gateways as $gateway) {
            $config = $gateway->getConfig();
            $methods[] = [
                'gateway' => $config['gateway'],
                'name' => $gateway->getGatewayName()->label(),
                'available' => $config['available'],
                'fee' => $amount ? $gateway->calculateFee($amount, 'USD') : null,
            ];
        }

        return response()->json([
            'success' => true,
            'methods' => $methods,
            'country_code' => $countryCode,
        ]);
    }

    /**
     * Simulate webhook for testing
     * 
     * POST /api/payments/simulate-webhook
     */
    public function simulateWebhook(Request $request): JsonResponse
    {
        if (!config('app.debug')) {
            return response()->json([
                'success' => false,
                'message' => 'Only available in debug mode',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'gateway' => 'required|string|in:stripe,paypal,bank_transfer',
            'event_type' => 'required|string',
            'transaction_id' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $gateway = $request->input('gateway');
        $eventType = $request->input('event_type');
        $transactionId = $request->input('transaction_id');

        // Build mock webhook payload based on gateway
        $payload = $this->buildMockWebhookPayload($gateway, $eventType, $transactionId);

        $result = $this->webhookService->processWebhook($gateway, $payload, []);

        return response()->json($result);
    }

    /**
     * Build mock webhook payload for testing
     */
    private function buildMockWebhookPayload(string $gateway, string $eventType, string $transactionId): array
    {
        return match ($gateway) {
            'stripe' => [
                'type' => $eventType,
                'data' => [
                    'object' => [
                        'id' => $transactionId,
                        'status' => str_contains($eventType, 'succeeded') ? 'succeeded' : 'failed',
                    ],
                ],
            ],
            'paypal' => [
                'event_type' => $eventType,
                'resource' => [
                    'id' => $transactionId,
                    'status' => str_contains($eventType, 'COMPLETED') ? 'COMPLETED' : 'FAILED',
                ],
            ],
            'bank_transfer' => [
                'event' => $eventType,
                'data' => [
                    'transfer_id' => $transactionId,
                    'status' => str_contains($eventType, 'completed') ? 'completed' : 'failed',
                ],
            ],
            default => [],
        };
    }
}


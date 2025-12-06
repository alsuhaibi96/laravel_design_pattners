<?php

namespace App\PaymentGateways;

use App\DTOs\PaymentRequestDTO;
use App\DTOs\PaymentResponseDTO;
use App\DTOs\RefundRequestDTO;
use App\DTOs\RefundResponseDTO;
use App\DTOs\WebhookPayloadDTO;
use App\Enums\PaymentGateway;

/**
 * Strategy Interface for Payment Gateways
 * 
 * This interface defines the contract that all payment gateway strategies must implement.
 * Each concrete strategy (Stripe, PayPal, Bank Transfer) will implement this interface,
 * allowing them to be used interchangeably at runtime.
 */
interface PaymentGatewayInterface
{
    /**
     * Get the gateway identifier
     */
    public function getGatewayName(): PaymentGateway;

    /**
     * Process a payment through this gateway
     */
    public function processPayment(PaymentRequestDTO $request): PaymentResponseDTO;

    /**
     * Process a refund through this gateway
     * Refunds must be processed through the original gateway
     */
    public function processRefund(RefundRequestDTO $request): RefundResponseDTO;

    /**
     * Validate and parse incoming webhook payload
     * Each gateway has different webhook formats
     */
    public function handleWebhook(array $payload, array $headers): WebhookPayloadDTO;

    /**
     * Verify webhook signature
     * Each gateway uses different signature methods
     */
    public function verifyWebhookSignature(array $payload, array $headers): bool;

    /**
     * Check if gateway is available/healthy
     */
    public function isAvailable(): bool;

    /**
     * Check if gateway supports the given country
     */
    public function supportsCountry(string $countryCode): bool;

    /**
     * Check if amount is within gateway limits
     */
    public function supportsAmount(float $amount, string $currency): bool;

    /**
     * Calculate fee for given amount
     */
    public function calculateFee(float $amount, string $currency): float;

    /**
     * Get gateway configuration (sanitized, no secrets)
     */
    public function getConfig(): array;
}


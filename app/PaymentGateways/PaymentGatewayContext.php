<?php

namespace App\PaymentGateways;

use App\DTOs\PaymentRequestDTO;
use App\DTOs\PaymentResponseDTO;
use App\DTOs\RefundRequestDTO;
use App\DTOs\RefundResponseDTO;
use App\DTOs\WebhookPayloadDTO;
use App\Enums\PaymentGateway;

/**
 * Payment Gateway Context
 * 
 * This is the Context class in the Strategy pattern.
 * It maintains a reference to a Strategy object and delegates work to it.
 * The context doesn't know the concrete class of a strategy.
 */
class PaymentGatewayContext
{
    private PaymentGatewayInterface $strategy;

    public function __construct(?PaymentGatewayInterface $strategy = null)
    {
        if ($strategy) {
            $this->strategy = $strategy;
        }
    }

    /**
     * Set the payment strategy at runtime
     * This allows switching between different payment gateways
     */
    public function setStrategy(PaymentGatewayInterface $strategy): self
    {
        $this->strategy = $strategy;
        return $this;
    }

    /**
     * Get current strategy
     */
    public function getStrategy(): PaymentGatewayInterface
    {
        return $this->strategy;
    }

    /**
     * Get the current gateway name
     */
    public function getGatewayName(): PaymentGateway
    {
        return $this->strategy->getGatewayName();
    }

    /**
     * Process payment through the current strategy
     */
    public function processPayment(PaymentRequestDTO $request): PaymentResponseDTO
    {
        return $this->strategy->processPayment($request);
    }

    /**
     * Process refund through the current strategy
     */
    public function processRefund(RefundRequestDTO $request): RefundResponseDTO
    {
        return $this->strategy->processRefund($request);
    }

    /**
     * Handle webhook through the current strategy
     */
    public function handleWebhook(array $payload, array $headers): WebhookPayloadDTO
    {
        return $this->strategy->handleWebhook($payload, $headers);
    }

    /**
     * Verify webhook signature through the current strategy
     */
    public function verifyWebhookSignature(array $payload, array $headers): bool
    {
        return $this->strategy->verifyWebhookSignature($payload, $headers);
    }

    /**
     * Check if current gateway is available
     */
    public function isAvailable(): bool
    {
        return $this->strategy->isAvailable();
    }

    /**
     * Check if current gateway supports the country
     */
    public function supportsCountry(string $countryCode): bool
    {
        return $this->strategy->supportsCountry($countryCode);
    }

    /**
     * Check if current gateway supports the amount
     */
    public function supportsAmount(float $amount, string $currency): bool
    {
        return $this->strategy->supportsAmount($amount, $currency);
    }

    /**
     * Calculate fee for the current gateway
     */
    public function calculateFee(float $amount, string $currency): float
    {
        return $this->strategy->calculateFee($amount, $currency);
    }
}


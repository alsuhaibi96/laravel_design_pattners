<?php

namespace App\PaymentGateways;

use App\DTOs\PaymentRequestDTO;
use App\DTOs\PaymentResponseDTO;
use App\DTOs\RefundRequestDTO;
use App\DTOs\RefundResponseDTO;
use App\Enums\PaymentGateway;
use Illuminate\Support\Facades\Log;

/**
 * Abstract base class for payment gateways
 * Provides common functionality for all gateway implementations
 */
abstract class AbstractPaymentGateway implements PaymentGatewayInterface
{
    protected bool $isAvailable = true;
    protected array $config = [];

    public function __construct()
    {
        $this->loadConfig();
    }

    abstract protected function loadConfig(): void;

    public function isAvailable(): bool
    {
        return $this->isAvailable;
    }

    public function supportsCountry(string $countryCode): bool
    {
        return $this->getGatewayName()->supportsCountry($countryCode);
    }

    public function supportsAmount(float $amount, string $currency): bool
    {
        $gateway = $this->getGatewayName();
        return $amount >= $gateway->minAmount() && $amount <= $gateway->maxAmount();
    }

    public function calculateFee(float $amount, string $currency): float
    {
        return $this->getGatewayName()->calculateFee($amount);
    }

    public function getConfig(): array
    {
        // Return sanitized config (remove secrets)
        return [
            'gateway' => $this->getGatewayName()->value,
            'available' => $this->isAvailable,
            'rate_limit' => $this->getGatewayName()->rateLimit(),
            'supported_countries' => $this->getGatewayName()->supportedCountries(),
        ];
    }

    protected function log(string $message, array $context = [], string $level = 'info'): void
    {
        $context['gateway'] = $this->getGatewayName()->value;
        Log::channel('payment')->$level($message, $context);
    }

    protected function sanitizeRequest(PaymentRequestDTO $request): array
    {
        // Remove sensitive data for logging
        return [
            'payment_uuid' => $request->paymentUuid,
            'amount' => $request->amount,
            'currency' => $request->currency,
            'country' => $request->countryCode,
        ];
    }

    protected function simulateApiLatency(): void
    {
        // Simulate realistic API latency for mock implementations
        usleep(rand(100000, 500000)); // 100-500ms
    }
}


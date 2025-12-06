<?php

namespace App\Services\Payment;

use App\Enums\PaymentGateway;
use App\Enums\PaymentMethod;
use App\PaymentGateways\PaymentGatewayInterface;
use App\PaymentGateways\Strategies\BankTransferGateway;
use App\PaymentGateways\Strategies\PayPalGateway;
use App\PaymentGateways\Strategies\StripeGateway;
use Illuminate\Support\Collection;

/**
 * Gateway Router Service
 * 
 * Responsible for selecting and ordering payment gateways based on:
 * - User preference
 * - Geographic location
 * - Transaction amount
 * - Gateway availability
 * - Fee optimization
 */
class GatewayRouter
{
    private array $gateways = [];
    
    public function __construct()
    {
        $this->registerGateways();
    }

    /**
     * Register all available payment gateways
     */
    private function registerGateways(): void
    {
        $this->gateways = [
            PaymentGateway::STRIPE->value => new StripeGateway(),
            PaymentGateway::PAYPAL->value => new PayPalGateway(),
            PaymentGateway::BANK_TRANSFER->value => new BankTransferGateway(),
        ];
    }

    /**
     * Get ordered list of gateways for a payment request
     * 
     * @return PaymentGatewayInterface[]
     */
    public function getOrderedGateways(
        ?string $preferredGateway = null,
        ?string $countryCode = null,
        ?float $amount = null,
        ?PaymentMethod $paymentMethod = null
    ): array {
        $availableGateways = $this->getAvailableGateways($countryCode, $amount, $paymentMethod);

        if (empty($availableGateways)) {
            return [];
        }

        // If preferred gateway is specified and available, put it first
        if ($preferredGateway && isset($availableGateways[$preferredGateway])) {
            $preferred = $availableGateways[$preferredGateway];
            unset($availableGateways[$preferredGateway]);
            return array_merge([$preferred], array_values($availableGateways));
        }

        // Sort by fee (lowest first) for high-value transactions
        if ($amount && $amount > 1000) {
            return $this->sortByFee($availableGateways, $amount);
        }

        // Default ordering based on success rate/reliability
        return $this->sortByReliability($availableGateways);
    }

    /**
     * Get a specific gateway by name
     */
    public function getGateway(string|PaymentGateway $gateway): ?PaymentGatewayInterface
    {
        $gatewayKey = $gateway instanceof PaymentGateway ? $gateway->value : $gateway;
        return $this->gateways[$gatewayKey] ?? null;
    }

    /**
     * Get all available gateways filtered by constraints
     */
    public function getAvailableGateways(
        ?string $countryCode = null,
        ?float $amount = null,
        ?PaymentMethod $paymentMethod = null
    ): array {
        return Collection::make($this->gateways)
            ->filter(function (PaymentGatewayInterface $gateway) use ($countryCode, $amount, $paymentMethod) {
                // Check if gateway is available
                if (!$gateway->isAvailable()) {
                    return false;
                }

                // Check country support
                if ($countryCode && !$gateway->supportsCountry($countryCode)) {
                    return false;
                }

                // Check amount support
                if ($amount && !$gateway->supportsAmount($amount, 'USD')) {
                    return false;
                }

                // Check payment method compatibility
                if ($paymentMethod) {
                    $supportedGateways = $paymentMethod->supportedGateways();
                    if (!in_array($gateway->getGatewayName(), $supportedGateways)) {
                        return false;
                    }
                }

                return true;
            })
            ->all();
    }

    /**
     * Sort gateways by fee (lowest first)
     * For high-value transactions, fee optimization matters
     */
    private function sortByFee(array $gateways, float $amount): array
    {
        uasort($gateways, function (PaymentGatewayInterface $a, PaymentGatewayInterface $b) use ($amount) {
            $feeA = $a->calculateFee($amount, 'USD');
            $feeB = $b->calculateFee($amount, 'USD');
            return $feeA <=> $feeB;
        });

        return array_values($gateways);
    }

    /**
     * Sort gateways by reliability (Stripe first as most reliable)
     */
    private function sortByReliability(array $gateways): array
    {
        $priority = [
            PaymentGateway::STRIPE->value => 1,
            PaymentGateway::PAYPAL->value => 2,
            PaymentGateway::BANK_TRANSFER->value => 3,
        ];

        uasort($gateways, function (PaymentGatewayInterface $a, PaymentGatewayInterface $b) use ($priority) {
            $priorityA = $priority[$a->getGatewayName()->value] ?? 99;
            $priorityB = $priority[$b->getGatewayName()->value] ?? 99;
            return $priorityA <=> $priorityB;
        });

        return array_values($gateways);
    }

    /**
     * Get list of all supported gateways with their config
     */
    public function getAllGatewaysConfig(): array
    {
        return Collection::make($this->gateways)
            ->map(fn (PaymentGatewayInterface $gateway) => $gateway->getConfig())
            ->all();
    }

    /**
     * Check if any gateway is available for given constraints
     */
    public function hasAvailableGateway(
        ?string $countryCode = null,
        ?float $amount = null,
        ?PaymentMethod $paymentMethod = null
    ): bool {
        return !empty($this->getAvailableGateways($countryCode, $amount, $paymentMethod));
    }
}


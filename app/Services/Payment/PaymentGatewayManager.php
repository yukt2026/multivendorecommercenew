<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Models\Setting;
use App\Services\Payment\Drivers\CashfreeGateway;
use App\Services\Payment\Drivers\RazorpayGateway;
use InvalidArgumentException;

class PaymentGatewayManager
{
    /** @var array<string, PaymentGatewayInterface> */
    protected array $drivers = [];

    public function __construct()
    {
        $this->drivers['razorpay'] = new RazorpayGateway();
        $this->drivers['cashfree'] = new CashfreeGateway();
    }

    /**
     * Get a gateway driver by identifier or the active default.
     */
    public function driver(?string $name = null): PaymentGatewayInterface
    {
        $driverKey = strtolower((string) ($name ?: $this->getDefaultDriver()));

        if (!isset($this->drivers[$driverKey])) {
            throw new InvalidArgumentException("Unsupported payment gateway driver: [{$driverKey}].");
        }

        return $this->drivers[$driverKey];
    }

    /**
     * Get configured default gateway driver name.
     */
    public function getDefaultDriver(): string
    {
        return (string) Setting::get('payment_gateway_default', config('onestall.payment_gateway', 'razorpay'));
    }

    /**
     * Register a custom gateway driver.
     */
    public function extend(string $name, PaymentGatewayInterface $gateway): self
    {
        $this->drivers[strtolower($name)] = $gateway;
        return $this;
    }
}

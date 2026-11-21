<?php

namespace App\Support\Payments;

use App\Contracts\Payments\PaymentGateway;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

class PaymentGatewayManager
{
    public function __construct(private readonly Container $container) {}

    public function default(): PaymentGateway
    {
        return $this->driver((string) config('payments.default'));
    }

    public function driver(string $name): PaymentGateway
    {
        $drivers = config('payments.drivers', []);
        $class = $drivers[$name] ?? null;

        if (! is_string($class) || ! in_array($name, config('payments.enabled', []), true)) {
            throw new InvalidArgumentException("Payment gateway [{$name}] is not enabled.");
        }

        $gateway = $this->container->make($class);

        if (! $gateway instanceof PaymentGateway) {
            throw new InvalidArgumentException("Payment gateway [{$name}] must implement PaymentGateway.");
        }

        return $gateway;
    }
}

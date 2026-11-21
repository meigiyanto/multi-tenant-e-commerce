<?php

namespace App\Contracts\Shipping;

interface ShippingProvider
{
    /** @return list<array<string, mixed>> */
    public function searchDestinations(string $query): array;

    /**
     * @phpstan-param list<string> $couriers
     * @phpstan-return list<array<string, mixed>>
     */
    public function calculate(int $originId, int $destinationId, int $weightGrams, array $couriers): array;
}

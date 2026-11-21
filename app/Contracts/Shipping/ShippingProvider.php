<?php

namespace App\Contracts\Shipping;

interface ShippingProvider
{
    /** @return list<array<string, mixed>> */
    public function searchDestinations(string $query): array;

    /**
     * @param int          $originId
     * @param int          $destinationId
     * @param int          $weightGrams
     * @param list<string> $couriers
     *
     * @return list<array<string, mixed>>
     */
    public function calculate(int $originId, int $destinationId, int $weightGrams, array $couriers): array;
}

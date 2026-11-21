<?php

namespace App\Contracts\Shipping;

interface ShippingProvider
{
    public function searchDestinations(string $query): array;

    public function calculate(int $originId, int $destinationId, int $weightGrams, array $couriers): array;
}

<?php

namespace App\Services\Shipping;

use App\Contracts\Shipping\ShippingProvider;
use App\Models\Cart;
use App\Models\Store;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class ShippingQuoteService
{
    public function __construct(private readonly ShippingProvider $provider) {}

    /** @return list<array<string, mixed>> */
    public function destinations(string $query): array
    {
        return $this->provider->searchDestinations($query);
    }

    public function weight(Cart $cart): int
    {
        $cart->loadMissing('items.product');
        $weight = $cart->items->sum(fn ($item): int => (int) ($item->product->weight_grams ?? 1000) * (int) $item->quantity);

        return max(1, (int) $weight);
    }

    /** @return list<array{courier: string, courier_name: string, service: string, description: string, amount: int, etd: string}> */
    public function rates(Store $store, Cart $cart, int $destinationId): array
    {
        $settings = $store->settings ?? [];
        $originId = (int) data_get($settings, 'shipping.origin_destination_id', 0);

        if ($originId < 1) {
            throw ValidationException::withMessages(['shipping' => 'The store owner has not configured the shipping origin address.']);
        }

        $weight = $this->weight($cart);
        $couriers = array_values(array_filter((array) config('shipping.providers.rajaongkir.couriers', []), 'is_string'));
        /** @var list<string> $couriers */
        $cacheKey = 'shipping-rates:'.hash('sha256', implode('|', [$store->id, $originId, $destinationId, $weight, implode(',', $couriers)]));

        /** @var list<array{courier: string, courier_name: string, service: string, description: string, amount: int, etd: string}> $rates */
        $rates = Cache::remember($cacheKey, now()->addSeconds(90), fn (): array => $this->provider->calculate($originId, $destinationId, $weight, $couriers));

        usort($rates, static function (array $first, array $second): int {
            $providerOrder = strcasecmp($first['courier_name'], $second['courier_name']);

            if ($providerOrder !== 0) {
                return $providerOrder;
            }

            $priceOrder = (int) $first['amount'] <=> (int) $second['amount'];

            return $priceOrder !== 0 ? $priceOrder : strcasecmp($first['service'], $second['service']);
        });

        return $rates;
    }

    /** @return array{courier: string, courier_name: string, service: string, description: string, amount: int, etd: string} */
    public function selectedRate(Store $store, Cart $cart, int $destinationId, string $courier, string $service): array
    {
        $rate = collect($this->rates($store, $cart, $destinationId))->first(
            fn (array $item): bool => $item['courier'] === $courier && $item['service'] === $service,
        );

        if ($rate === null) {
            throw ValidationException::withMessages(['shipping_service' => 'The selected shipping service is invalid or the rate has changed. Please recalculate shipping.']);
        }

        return $rate;
    }
}

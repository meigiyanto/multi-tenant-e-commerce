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
            throw ValidationException::withMessages(['shipping' => 'Alamat asal pengiriman toko belum dikonfigurasi oleh pemilik toko.']);
        }

        $weight = $this->weight($cart);
        $couriers = (array) config('shipping.providers.rajaongkir.couriers', []);
        $cacheKey = 'shipping-rates:'.hash('sha256', implode('|', [$store->id, $originId, $destinationId, $weight, implode(',', $couriers)]));

        /** @var list<array{courier: string, courier_name: string, service: string, description: string, amount: int, etd: string}> $rates */
        $rates = Cache::remember($cacheKey, now()->addSeconds(90), fn (): array => $this->provider->calculate($originId, $destinationId, $weight, $couriers));

        usort($rates, static function (array $first, array $second): int {
            $providerOrder = strcasecmp($first['courier_name'] ?? $first['courier'], $second['courier_name'] ?? $second['courier']);

            return $providerOrder !== 0 ? $providerOrder : strcasecmp($first['service'], $second['service']);
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
            throw ValidationException::withMessages(['shipping_service' => 'Layanan pengiriman tidak valid atau tarif sudah berubah. Silakan hitung ulang ongkir.']);
        }

        return $rate;
    }
}

<?php

namespace App\Services\Shipping;

use App\Contracts\Shipping\ShippingProvider;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class RajaOngkirShippingProvider implements ShippingProvider
{
    private function client()
    {
        $key = (string) config('shipping.providers.rajaongkir.api_key');
        if ($key === '') {
            throw new RuntimeException('RajaOngkir API key belum dikonfigurasi.');
        }

        return Http::baseUrl(rtrim((string) config('shipping.providers.rajaongkir.base_url'), '/').'/')
            ->withHeaders(['key' => $key, 'Accept' => 'application/json'])
            ->timeout(20);
    }

    public function searchDestinations(string $query): array
    {
        $response = $this->client()->get('destination/domestic-destination', ['search' => $query]);
        if (! $response->successful()) {
            throw new RuntimeException('Gagal mencari tujuan pengiriman dari RajaOngkir.');
        }

        return $response->json('data', []);
    }

    public function calculate(int $originId, int $destinationId, int $weightGrams, array $couriers): array
    {
        $response = $this->client()->asForm()->post('calculate/domestic-cost', [
            'origin' => $originId,
            'destination' => $destinationId,
            'weight' => max(1, $weightGrams),
            'courier' => implode(':', $couriers),
            'price' => 'lowest',
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('Gagal menghitung ongkos kirim dari RajaOngkir.');
        }

        $rates = [];
        foreach ($response->json('data', []) as $courier) {
            foreach (($courier['costs'] ?? []) as $service) {
                $cost = $service['cost'] ?? [];
                if (array_is_list($cost)) {
                    $cost = $cost[0] ?? [];
                }

                $rates[] = [
                    'courier' => (string) ($courier['code'] ?? ''),
                    'courier_name' => (string) ($courier['name'] ?? strtoupper((string) ($courier['code'] ?? ''))),
                    'service' => (string) ($service['service'] ?? ''),
                    'description' => (string) ($service['description'] ?? ''),
                    'amount' => (int) ($cost['value'] ?? 0),
                    'etd' => (string) ($cost['etd'] ?? ''),
                ];
            }
        }

        return $rates;
    }
}

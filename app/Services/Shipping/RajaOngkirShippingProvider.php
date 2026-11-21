<?php

namespace App\Services\Shipping;

use App\Contracts\Shipping\ShippingProvider;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class RajaOngkirShippingProvider implements ShippingProvider
{
    private function client(): PendingRequest
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
        $response = $this->client()->get('destination/domestic-destination', [
            'search' => $query,
            'limit' => 10,
            'offset' => 0,
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('Gagal mencari tujuan pengiriman dari RajaOngkir.');
        }

        return $response->json('data', []);
    }

    public function calculate(int $originId, int $destinationId, int $weightGrams, array $couriers): array
    {
        $rates = [];

        foreach ($couriers as $courierCode) {
            $response = $this->client()->asForm()->post('calculate/domestic-cost', [
                'origin' => $originId,
                'destination' => $destinationId,
                'weight' => max(1, $weightGrams),
                'courier' => $courierCode,
                'price' => 'lowest',
            ]);

            if (in_array($response->status(), [400, 404, 422], true)) {
                continue;
            }

            if (! $response->successful()) {
                throw new RuntimeException('Gagal menghitung ongkos kirim dari RajaOngkir.');
            }

            foreach ($response->json('data', []) ?? [] as $service) {
                if (isset($service['costs'])) {
                    foreach ($service['costs'] as $nestedService) {
                        $rates[] = $this->normalizeRate($service, $nestedService);
                    }

                    continue;
                }

                $rates[] = [
                    'courier' => (string) ($service['code'] ?? $courierCode),
                    'courier_name' => (string) ($service['name'] ?? strtoupper((string) $courierCode)),
                    'service' => (string) ($service['service'] ?? ''),
                    'description' => (string) ($service['description'] ?? ''),
                    'amount' => (int) ($service['cost'] ?? 0),
                    'etd' => (string) ($service['etd'] ?? ''),
                ];
            }
        }

        return $rates;
    }

    private function normalizeRate(array $courier, array $service): array
    {
        $cost = $service['cost'] ?? [];
        if (is_array($cost) && array_is_list($cost)) {
            $cost = $cost[0] ?? [];
        }

        return [
            'courier' => (string) ($courier['code'] ?? ''),
            'courier_name' => (string) ($courier['name'] ?? strtoupper((string) ($courier['code'] ?? ''))),
            'service' => (string) ($service['service'] ?? ''),
            'description' => (string) ($service['description'] ?? ''),
            'amount' => (int) (is_array($cost) ? ($cost['value'] ?? 0) : $cost),
            'etd' => (string) (is_array($cost) ? ($cost['etd'] ?? '') : ($service['etd'] ?? '')),
        ];
    }
}

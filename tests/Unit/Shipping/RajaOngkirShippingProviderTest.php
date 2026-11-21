<?php

namespace Tests\Unit\Shipping;

use App\Services\Shipping\RajaOngkirShippingProvider;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RajaOngkirShippingProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'shipping.providers.rajaongkir.api_key' => 'demo-api-key',
            'shipping.providers.rajaongkir.base_url' => 'https://rajaongkir.komerce.id/api/v1',
        ]);
    }

    public function test_it_searches_domestic_destinations(): void
    {
        Http::fake([
            'rajaongkir.komerce.id/api/v1/destination/domestic-destination*' => Http::response([
                'data' => [['id' => 123, 'label' => 'Kebayoran Baru, Jakarta Selatan']],
            ]),
        ]);

        $destinations = app(RajaOngkirShippingProvider::class)->searchDestinations('Kebayoran');

        $this->assertSame(123, $destinations[0]['id']);
        Http::assertSent(fn ($request): bool => $request->hasHeader('key', 'demo-api-key')
            && str_contains($request->url(), 'search=Kebayoran'));
    }

    public function test_it_calculates_and_normalizes_shipping_services(): void
    {
        Http::fake([
            'rajaongkir.komerce.id/api/v1/calculate/domestic-cost' => Http::response([
                'data' => [[
                    'code' => 'jne',
                    'name' => 'Jalur Nugraha Ekakurir',
                    'costs' => [[
                        'service' => 'REG',
                        'description' => 'Regular Service',
                        'cost' => ['value' => 18000, 'etd' => '2-3'],
                    ]],
                ]],
            ]),
        ]);

        $rates = app(RajaOngkirShippingProvider::class)->calculate(100, 200, 1500, ['jne', 'jnt']);

        $this->assertSame([
            'courier' => 'jne',
            'courier_name' => 'Jalur Nugraha Ekakurir',
            'service' => 'REG',
            'description' => 'Regular Service',
            'amount' => 18000,
            'etd' => '2-3',
        ], $rates[0]);
        Http::assertSent(fn ($request): bool => $request->hasHeader('key', 'demo-api-key')
            && $request['origin'] == 100
            && $request['destination'] == 200
            && $request['weight'] == 1500);
    }
}

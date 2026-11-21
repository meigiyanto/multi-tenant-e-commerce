<?php

namespace Tests\Feature\Storefront;

use App\Models\Product;
use App\Models\Store;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StorefrontProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_view_an_active_product_detail_on_its_store_domain(): void
    {
        [$store, $product] = $this->activeStoreWithProduct();

        $this->get('http://'.$store->slug.'.localhost/products/'.$product->slug)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('storefront/product')
                ->where('store.name', $store->name)
                ->where('product.name', $product->name)
                ->where('product.slug', $product->slug)
                ->has('product.variants', 1)
                ->has('product.images', 0),
            );
    }

    public function test_draft_products_are_not_exposed_on_the_storefront(): void
    {
        [$store, $product] = $this->activeStoreWithProduct(status: 'draft');

        $this->get('http://'.$store->slug.'.localhost/products/'.$product->slug)
            ->assertNotFound();
    }

    public function test_product_detail_cannot_be_loaded_from_another_store_domain(): void
    {
        [$store, $product] = $this->activeStoreWithProduct();
        $otherTeam = Team::factory()->create(['status' => 'active']);
        $otherStore = $otherTeam->stores()->create([
            'name' => 'Other Store',
            'slug' => 'other-store-'.$otherTeam->id,
            'status' => 'active',
            'currency' => 'IDR',
        ]);
        $otherStore->domains()->create([
            'domain' => $otherStore->slug.'.localhost',
            'type' => 'subdomain',
            'status' => 'active',
            'is_primary' => true,
            'verified_at' => now(),
        ]);

        $this->get('http://'.$otherStore->slug.'.localhost/products/'.$product->slug)
            ->assertNotFound();
    }

    /** @return array{Store, Product} */
    private function activeStoreWithProduct(string $status = 'active'): array
    {
        $team = Team::factory()->create(['status' => 'active']);
        $store = $team->stores()->create([
            'name' => 'Detail Store',
            'slug' => 'detail-store-'.$team->id,
            'status' => 'active',
            'timezone' => 'Asia/Jakarta',
            'currency' => 'IDR',
        ]);
        $store->domains()->create([
            'domain' => $store->slug.'.localhost',
            'type' => 'subdomain',
            'status' => 'active',
            'is_primary' => true,
            'verified_at' => now(),
        ]);
        $product = $store->products()->create([
            'name' => 'Detail Product',
            'slug' => 'detail-product',
            'status' => $status,
            'description' => 'A product detail description.',
            'price' => 150000,
            'track_inventory' => false,
        ]);
        $product->variants()->create([
            'store_id' => $store->id,
            'name' => 'Default',
            'sku' => 'DETAIL-'.$store->id,
            'is_active' => true,
        ]);

        return [$store, $product];
    }
}

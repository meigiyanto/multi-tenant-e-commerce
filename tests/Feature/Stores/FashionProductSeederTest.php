<?php

namespace Tests\Feature\Stores;

use App\Models\Team;
use Database\Seeders\FashionProductSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FashionProductSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_twenty_store_scoped_fashion_products_with_variants_and_stock(): void
    {
        $team = Team::factory()->create();
        $store = $team->stores()->create([
            'name' => 'Fashion Demo Store',
            'slug' => 'fashion-demo-store',
            'status' => 'draft',
            'timezone' => 'Asia/Jakarta',
            'currency' => 'IDR',
        ]);

        $seeder = new FashionProductSeeder;
        $seeder->run();

        $this->assertDatabaseCount('categories', 2);
        $this->assertDatabaseCount('products', 20);
        $this->assertDatabaseHas('categories', [
            'store_id' => $store->id,
            'slug' => 'womens-fashion',
        ]);
        $this->assertDatabaseHas('categories', [
            'store_id' => $store->id,
            'slug' => 'mens-fashion',
        ]);
        $this->assertDatabaseHas('products', [
            'store_id' => $store->id,
            'slug' => 'linen-relaxed-blouse',
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('products', [
            'store_id' => $store->id,
            'slug' => 'oxford-button-down-shirt',
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('product_variants', [
            'store_id' => $store->id,
            'sku' => 'WF-LINEN-RELAXED-BLOUSE-IVORY-S',
        ]);
        $this->assertDatabaseHas('inventory_movements', [
            'store_id' => $store->id,
            'type' => 'initial',
            'reference' => 'fashion-product-seeder',
        ]);

        $this->assertGreaterThan(20, $store->fresh()->products()->withCount('variants')->get()->sum('variants_count'));
    }

    public function test_it_is_idempotent_and_does_not_reset_existing_inventory(): void
    {
        $team = Team::factory()->create();
        $store = $team->stores()->create([
            'name' => 'Fashion Demo Store',
            'slug' => 'fashion-demo-store',
            'status' => 'draft',
            'timezone' => 'Asia/Jakarta',
            'currency' => 'IDR',
        ]);

        $seeder = new FashionProductSeeder;
        $seeder->run();

        $variant = $store->fresh()->products()
            ->where('slug', 'linen-relaxed-blouse')
            ->firstOrFail()
            ->variants()
            ->where('sku', 'WF-LINEN-RELAXED-BLOUSE-IVORY-S')
            ->firstOrFail();

        $variant->inventory()->update(['quantity' => 3]);
        $seeder->run();

        $this->assertDatabaseCount('categories', 2);
        $this->assertDatabaseCount('products', 20);
        $this->assertDatabaseHas('inventories', [
            'store_id' => $store->id,
            'product_variant_id' => $variant->id,
            'quantity' => 3,
        ]);
    }
}

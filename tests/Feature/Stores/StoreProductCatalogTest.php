<?php

namespace Tests\Feature\Stores;

use App\Enums\TeamRole;
use App\Models\Product;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StoreProductCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_members_can_view_only_their_store_catalog(): void
    {
        [$user, $team, $store] = $this->ownedStore();
        $category = $store->categories()->create(['name' => 'Apparel', 'slug' => 'apparel']);
        $product = $store->products()->create([
            'category_id' => $category->id, 'name' => 'T-Shirt', 'slug' => 't-shirt',
            'status' => 'active', 'price' => 120000, 'track_inventory' => true,
        ]);
        $variant = $product->variants()->create(['store_id' => $store->id, 'name' => 'Black / M', 'sku' => 'TS-BLK-M']);
        $variant->inventory()->create(['store_id' => $store->id, 'quantity' => 8]);

        $this->actingAs($user)
            ->get(route('teams.stores.catalog.index', [$team, $store]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('catalog/index')
                ->where('store.id', $store->id)
                ->has('products.data', 1)
                ->where('products.data.0.name', 'T-Shirt')
                ->where('products.data.0.stock', 8)
                ->where('categories.0.name', 'Apparel'),
            );
    }

    public function test_store_owner_can_create_product_variants_photos_and_initial_stock(): void
    {
        Storage::fake('public');
        [$user, $team, $store] = $this->ownedStore();
        $category = $store->categories()->create(['name' => 'Shoes', 'slug' => 'shoes']);

        $this->actingAs($user)->post(route('teams.stores.catalog.products.store', [$team, $store]), [
            'name' => 'Running Shoes',
            'slug' => 'running-shoes',
            'category_id' => $category->id,
            'status' => 'active',
            'description' => 'Lightweight running shoes.',
            'price' => 450000,
            'compare_at_price' => 500000,
            'track_inventory' => 1,
            'variants' => [[
                'name' => 'Blue / 42',
                'sku' => 'RUN-BLU-42',
                'options' => ['Color' => 'Blue', 'Size' => '42'],
                'price' => 470000,
                'initial_stock' => 12,
                'low_stock_threshold' => 3,
            ]],
            'images' => [UploadedFile::fake()->image('shoes.jpg', 800, 800)],
        ])->assertRedirect(route('teams.stores.catalog.index', [$team, $store]));

        $product = Product::query()->where('store_id', $store->id)->where('slug', 'running-shoes')->firstOrFail();
        $variant = $product->variants()->firstOrFail();
        $this->assertSame('RUN-BLU-42', $variant->sku);
        $this->assertSame(['Color' => 'Blue', 'Size' => '42'], $variant->options);
        $this->assertDatabaseHas('inventories', ['store_id' => $store->id, 'product_variant_id' => $variant->id, 'quantity' => 12, 'low_stock_threshold' => 3]);
        $this->assertDatabaseHas('inventory_movements', ['store_id' => $store->id, 'type' => 'initial', 'quantity' => 12, 'balance_after' => 12]);
        $this->assertDatabaseCount('product_images', 1);
        Storage::disk('public')->assertExists($product->images()->firstOrFail()->path);
    }

    public function test_store_owner_can_adjust_stock_and_movement_is_recorded(): void
    {
        [$user, $team, $store] = $this->ownedStore();
        $product = $store->products()->create(['name' => 'Mug', 'slug' => 'mug', 'status' => 'active', 'price' => 50000, 'track_inventory' => true]);
        $variant = $product->variants()->create(['store_id' => $store->id, 'name' => 'Default', 'sku' => 'MUG-01']);
        $variant->inventory()->create(['store_id' => $store->id, 'quantity' => 10]);

        $this->actingAs($user)->post(route('teams.stores.catalog.stock.adjust', [$team, $store, $product, $variant]), [
            'quantity' => -2,
            'notes' => 'Damaged stock',
        ])->assertRedirect();

        $this->assertDatabaseHas('inventories', ['product_variant_id' => $variant->id, 'quantity' => 8]);
        $this->assertDatabaseHas('inventory_movements', ['store_id' => $store->id, 'type' => 'adjustment', 'quantity' => -2, 'balance_after' => 8, 'notes' => 'Damaged stock']);

        $this->actingAs($user)->get(route('teams.stores.catalog.products.edit', [$team, $store, $product]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('movements.0.quantity', -2)->where('movements.0.balance_after', 8));
    }

    public function test_stock_cannot_be_adjusted_below_reserved_quantity(): void
    {
        [$user, $team, $store] = $this->ownedStore();
        $product = $store->products()->create(['name' => 'Bottle', 'slug' => 'bottle', 'status' => 'active', 'price' => 25000, 'track_inventory' => true]);
        $variant = $product->variants()->create(['store_id' => $store->id, 'name' => 'Default', 'sku' => 'BOT-01']);
        $variant->inventory()->create(['store_id' => $store->id, 'quantity' => 5, 'reserved_quantity' => 3]);

        $this->actingAs($user)->from(route('teams.stores.catalog.products.edit', [$team, $store, $product]))
            ->post(route('teams.stores.catalog.stock.adjust', [$team, $store, $product, $variant]), ['quantity' => -3])
            ->assertSessionHasErrors('quantity');

        $this->assertDatabaseHas('inventories', ['product_variant_id' => $variant->id, 'quantity' => 5]);
    }

    public function test_store_members_cannot_access_another_stores_product(): void
    {
        [$user, $team, $store] = $this->ownedStore();
        [, , $otherStore] = $this->ownedStore();
        $product = $otherStore->products()->create(['name' => 'Private Item', 'slug' => 'private-item', 'status' => 'active', 'price' => 10000, 'track_inventory' => true]);

        $this->actingAs($user)
            ->get(route('teams.stores.catalog.products.edit', [$team, $store, $product]))
            ->assertNotFound();
    }

    public function test_team_member_without_store_management_permission_cannot_create_products(): void
    {
        [$user, $team, $store] = $this->ownedStore(TeamRole::Member);
        $this->actingAs($user)->post(route('teams.stores.catalog.products.store', [$team, $store]), [
            'name' => 'Blocked product', 'status' => 'draft', 'price' => 10000, 'track_inventory' => 1,
            'variants' => [['name' => 'Default', 'sku' => 'BLOCK-01', 'initial_stock' => 0]],
        ])->assertForbidden();

        $this->assertDatabaseMissing('products', ['store_id' => $store->id, 'name' => 'Blocked product']);
    }

    public function test_categories_are_store_scoped_and_cannot_be_deleted_while_in_use(): void
    {
        [$user, $team, $store] = $this->ownedStore();
        $category = $store->categories()->create(['name' => 'Accessories', 'slug' => 'accessories']);
        $product = $store->products()->create(['category_id' => $category->id, 'name' => 'Cap', 'slug' => 'cap', 'status' => 'draft', 'price' => 30000, 'track_inventory' => true]);

        $this->actingAs($user)
            ->delete(route('teams.stores.catalog.categories.destroy', [$team, $store, $category]))
            ->assertStatus(422);

        $this->assertDatabaseHas('categories', ['id' => $category->id]);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'category_id' => $category->id]);
    }

    public function test_public_storefront_shows_only_active_products_for_the_resolved_store(): void
    {
        [, , $store] = $this->ownedStore();
        $store->update(['status' => 'active']);
        $store->domains()->create([
            'domain' => 'catalog.localhost',
            'type' => 'subdomain',
            'status' => 'active',
            'is_primary' => true,
            'verified_at' => now(),
        ]);
        $active = $store->products()->create(['name' => 'Published Tee', 'slug' => 'published-tee', 'status' => 'active', 'price' => 150000, 'track_inventory' => true]);
        $variant = $active->variants()->create(['store_id' => $store->id, 'name' => 'Blue / L', 'sku' => 'TEE-BLUE-L', 'options' => ['Color' => 'Blue', 'Size' => 'L']]);
        $variant->inventory()->create(['store_id' => $store->id, 'quantity' => 4]);
        $store->products()->create(['name' => 'Draft item', 'slug' => 'draft-item', 'status' => 'draft', 'price' => 10000, 'track_inventory' => true]);

        $this->get('http://catalog.localhost/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('storefront/index')
                ->has('products', 1)
                ->where('products.0.name', 'Published Tee')
                ->where('products.0.in_stock', true)
                ->where('products.0.variants.0.stock', 4),
            );
    }

    /** @return array{User, Team, \App\Models\Store} */
    private function ownedStore(TeamRole $role = TeamRole::Owner): array
    {
        $user = User::factory()->create();
        $team = Team::factory()->create();
        $team->members()->attach($user, ['role' => $role->value]);
        $store = $team->stores()->create([
            'name' => 'Catalog Store',
            'slug' => 'catalog-store-'.$team->id,
            'status' => 'draft',
            'timezone' => 'Asia/Jakarta',
            'currency' => 'IDR',
        ]);

        return [$user, $team, $store];
    }
}

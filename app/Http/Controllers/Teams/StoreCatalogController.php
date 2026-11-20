<?php

namespace App\Http\Controllers\Teams;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class StoreCatalogController extends Controller
{
    public function index(Team $team, Store $store): Response
    {
        $this->authorizeStore($team, $store, 'view');

        return Inertia::render('catalog/index', [
            'team' => ['name' => $team->name, 'slug' => $team->slug],
            'store' => ['id' => $store->id, 'name' => $store->name, 'currency' => $store->currency],
            'products' => $store->products()->with(['category:id,name', 'images:id,product_id,path,is_primary', 'variants.inventory'])
                ->latest()->paginate(15)->through(fn (Product $product) => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'slug' => $product->slug,
                    'status' => $product->status,
                    'price' => $product->price,
                    'category' => $product->category?->name,
                    'image_url' => $product->images->firstWhere('is_primary', true) ? Storage::disk('public')->url($product->images->firstWhere('is_primary', true)->path) : null,
                    'variants_count' => $product->variants->count(),
                    'stock' => $product->variants->sum(fn (ProductVariant $variant) => $variant->inventory?->quantity ?? 0),
                ]),
            'categories' => $store->categories()->withCount('products')->orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'slug', 'is_active', 'parent_id']),
            'canManage' => Gate::allows('update', $store),
        ]);
    }

    public function create(Team $team, Store $store): Response
    {
        $this->authorizeStore($team, $store, 'update');

        return Inertia::render('catalog/create', [
            'team' => ['name' => $team->name, 'slug' => $team->slug],
            'store' => ['id' => $store->id, 'name' => $store->name, 'currency' => $store->currency],
            'categories' => $store->categories()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request, Team $team, Store $store): RedirectResponse
    {
        $this->authorizeStore($team, $store, 'update');
        $data = $this->validateProduct($request, $store);

        DB::transaction(function () use ($request, $store, $data): void {
            $product = $store->products()->create($this->productAttributes($data, $store));
            $variants = $data['variants'] ?? [];
            if ($variants === []) {
                $variants = [['name' => 'Default', 'sku' => Str::upper(Str::slug($product->name)).'-'.Str::upper(Str::random(8)), 'options' => [], 'initial_stock' => 0]];
            }

            foreach ($variants as $variantData) {
                $variant = $this->saveVariant($store, $product, $variantData);
                $inventory = $variant->inventory()->create([
                    'store_id' => $store->id,
                    'quantity' => (int) ($variantData['initial_stock'] ?? 0),
                    'low_stock_threshold' => (int) ($variantData['low_stock_threshold'] ?? 0),
                ]);
                if ($inventory->quantity > 0) {
                    $inventory->movements()->create([
                        'store_id' => $store->id,
                        'user_id' => $request->user()->id,
                        'type' => 'initial',
                        'quantity' => $inventory->quantity,
                        'balance_after' => $inventory->quantity,
                        'notes' => 'Initial stock',
                    ]);
                }
            }

            $this->storeImages($request, $store, $product);
        });

        return to_route('teams.stores.catalog.index', [$team->slug, $store->id])->with('success', __('Product created.'));
    }

    public function edit(Team $team, Store $store, Product $product): Response
    {
        $this->authorizeProduct($team, $store, $product, 'update');
        $product->load(['variants.inventory', 'images']);

        return Inertia::render('catalog/edit', [
            'team' => ['name' => $team->name, 'slug' => $team->slug],
            'store' => ['id' => $store->id, 'name' => $store->name, 'currency' => $store->currency],
            'categories' => $store->categories()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'status' => $product->status,
                'category_id' => $product->category_id,
                'description' => $product->description,
                'price' => $product->price,
                'compare_at_price' => $product->compare_at_price,
                'track_inventory' => $product->track_inventory,
                'variants' => $product->variants->map(fn (ProductVariant $variant) => [
                    'id' => $variant->id, 'name' => $variant->name, 'sku' => $variant->sku,
                    'options' => $variant->options ?? [], 'price' => $variant->price,
                    'compare_at_price' => $variant->compare_at_price,
                    'initial_stock' => $variant->inventory?->quantity ?? 0,
                    'low_stock_threshold' => $variant->inventory?->low_stock_threshold ?? 0,
                ]),
                'images' => $product->images->map(fn (ProductImage $image) => [
                    'id' => $image->id, 'url' => Storage::disk('public')->url($image->path),
                    'alt_text' => $image->alt_text, 'is_primary' => $image->is_primary,
                ]),
            ],
        ]);
    }

    public function update(Request $request, Team $team, Store $store, Product $product): RedirectResponse
    {
        $this->authorizeProduct($team, $store, $product, 'update');
        $data = $this->validateProduct($request, $store, $product);
        DB::transaction(function () use ($request, $store, $product, $data): void {
            $product->update($this->productAttributes($data, $store, $product));
            foreach ($data['variants'] ?? [] as $variantData) {
                $variant = isset($variantData['id'])
                    ? $product->variants()->where('store_id', $store->id)->findOrFail($variantData['id'])
                    : null;
                $variant = $this->saveVariant($store, $product, $variantData, $variant);
                $inventory = $variant->inventory()->firstOrCreate(['store_id' => $store->id]);
                $target = (int) ($variantData['initial_stock'] ?? $inventory->quantity);
                $delta = $target - $inventory->quantity;
                if ($delta !== 0) {
                    $this->adjustInventory($request, $store, $inventory, $delta, 'adjustment', 'Product edit stock update');
                }
                $inventory->update(['low_stock_threshold' => (int) ($variantData['low_stock_threshold'] ?? 0)]);
            }
            $this->storeImages($request, $store, $product);
        });

        return to_route('teams.stores.catalog.index', [$team->slug, $store->id])->with('success', __('Product updated.'));
    }

    public function destroy(Team $team, Store $store, Product $product): RedirectResponse
    {
        $this->authorizeProduct($team, $store, $product, 'update');
        $product->delete();

        return back()->with('success', __('Product archived.'));
    }

    public function storeCategory(Request $request, Team $team, Store $store): RedirectResponse
    {
        $this->authorizeStore($team, $store, 'update');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'parent_id' => ['nullable', 'integer', Rule::exists('categories', 'id')->where('store_id', $store->id)],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);
        $slug = Str::slug($data['name']);
        $base = $slug;
        $suffix = 2;
        while ($store->categories()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }
        $store->categories()->create($data + ['slug' => $slug]);

        return back()->with('success', __('Category created.'));
    }

    public function updateCategory(Request $request, Team $team, Store $store, Category $category): RedirectResponse
    {
        $this->authorizeCategory($team, $store, $category, 'update');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'parent_id' => ['nullable', 'integer', Rule::exists('categories', 'id')->where('store_id', $store->id)->where('id', '!=', $category->id)],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['required', 'boolean'],
        ]);
        abort_if((int) ($data['parent_id'] ?? 0) === $category->id, 422, __('A category cannot be its own parent.'));\n        $category->update($data + ['slug' => Str::slug($data['name'])]);

        return back()->with('success', __('Category updated.'));
    }

    public function destroyCategory(Team $team, Store $store, Category $category): RedirectResponse
    {
        $this->authorizeCategory($team, $store, $category, 'update');
        abort_if($category->products()->exists() || $category->children()->exists(), 422, __('Move its products and subcategories before deleting this category.'));
        $category->delete();

        return back()->with('success', __('Category deleted.'));
    }

    public function destroyImage(Team $team, Store $store, Product $product, ProductImage $image): RedirectResponse
    {
        $this->authorizeProduct($team, $store, $product, 'update');
        abort_unless($image->store_id === $store->id && $image->product_id === $product->id, 404);
        Storage::disk('public')->delete($image->path);
        $wasPrimary = $image->is_primary;
        $image->delete();
        if ($wasPrimary) {
            $product->images()->oldest()->first()?->update(['is_primary' => true]);
        }

        return back()->with('success', __('Product image removed.'));
    }

    public function adjustStock(Request $request, Team $team, Store $store, Product $product, ProductVariant $variant): RedirectResponse
    {
        $this->authorizeProduct($team, $store, $product, 'update');
        abort_unless($variant->store_id === $store->id && $variant->product_id === $product->id, 404);
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'not_in:0', 'between:-1000000,1000000'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);
        $inventory = $variant->inventory()->firstOrCreate(['store_id' => $store->id]);
        $this->adjustInventory($request, $store, $inventory, (int) $data['quantity'], 'adjustment', $data['notes'] ?? null);

        return back()->with('success', __('Inventory adjusted.'));
    }

    private function validateProduct(Request $request, Store $store, ?Product $product = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'slug' => ['nullable', 'string', 'max:200', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('products', 'slug')->where('store_id', $store->id)->ignore($product?->id)],
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')->where('store_id', $store->id)],
            'status' => ['required', Rule::in(['draft', 'active', 'archived'])],
            'description' => ['nullable', 'string', 'max:10000'],
            'price' => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
            'compare_at_price' => ['nullable', 'numeric', 'gte:price', 'max:999999999999.99'],
            'track_inventory' => ['required', 'boolean'],
            'variants' => ['nullable', 'array', 'min:1', 'max:100'],
            'variants.*.id' => ['nullable', 'integer'],
            'variants.*.name' => ['required', 'string', 'max:120'],
            'variants.*.sku' => ['required', 'string', 'max:100'],
            'variants.*.options' => ['nullable', 'array'],
            'variants.*.price' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'variants.*.compare_at_price' => ['nullable', 'numeric', 'min:0'],
            'variants.*.initial_stock' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'variants.*.low_stock_threshold' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'images' => ['nullable', 'array', 'max:10'],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);
    }

    private function productAttributes(array $data, Store $store, ?Product $product = null): array
    {
        return [
            'category_id' => $data['category_id'] ?? null,
            'name' => $data['name'],
            'slug' => $this->uniqueProductSlug($store, $data['slug'] ?? Str::slug($data['name']), $product),
            'status' => $data['status'],
            'description' => $data['description'] ?? null,
            'price' => $data['price'],
            'compare_at_price' => $data['compare_at_price'] ?? null,
            'track_inventory' => (bool) $data['track_inventory'],
        ];
    }

    private function uniqueProductSlug(Store $store, string $slug, ?Product $product = null): string\n    {\n        $base = $slug !== '' ? $slug : 'product';\n        $candidate = $base;\n        $suffix = 2;\n        while ($store->products()->where('slug', $candidate)->when($product, fn ($query) => $query->where('id', '!=', $product->id))->exists()) {\n            $candidate = $base.'-'.$suffix++;\n        }\n\n        return $candidate;\n    }\n\n    private function saveVariant(Store $store, Product $product, array $data, ?ProductVariant $variant = null): ProductVariant
    {
        $skuExists = ProductVariant::query()->where('store_id', $store->id)->where('sku', $data['sku'])->when($variant, fn ($query) => $query->where('id', '!=', $variant->id))->exists();
        if ($skuExists) {
            throw ValidationException::withMessages(['variants' => __('A SKU is already used by another product in this Store.')]);
        }
        $attributes = [
            'store_id' => $store->id, 'name' => $data['name'], 'sku' => $data['sku'],
            'options' => $data['options'] ?? [], 'price' => $data['price'] ?? null,
            'compare_at_price' => $data['compare_at_price'] ?? null, 'is_active' => true,
        ];
        if ($variant) {
            $variant->update($attributes);
            return $variant;
        }

        return $product->variants()->create($attributes);
    }

    private function storeImages(Request $request, Store $store, Product $product): void
    {
        foreach ($request->file('images', []) as $index => $file) {
            $image = $product->images()->create([
                'store_id' => $store->id,
                'path' => $file->store("stores/{$store->id}/products/{$product->id}", 'public'),
                'alt_text' => $product->name,
                'sort_order' => $product->images()->count(),
                'is_primary' => ! $product->images()->exists() && $index === 0,
            ]);
        }
    }

    private function adjustInventory(Request $request, Store $store, Inventory $inventory, int $delta, string $type, ?string $notes): void
    {
        $inventory = Inventory::query()->whereKey($inventory->id)->lockForUpdate()->firstOrFail();
        $next = $inventory->quantity + $delta;
        if ($next < $inventory->reserved_quantity) {
            throw ValidationException::withMessages(['quantity' => __('Stock cannot be lower than reserved quantity.')]);
        }
        $inventory->update(['quantity' => $next]);
        $inventory->movements()->create([
            'store_id' => $store->id, 'user_id' => $request->user()->id,
            'type' => $type, 'quantity' => $delta, 'balance_after' => $next, 'notes' => $notes,
        ]);
    }

    private function authorizeStore(Team $team, Store $store, string $ability): void
    {
        abort_unless($store->team_id === $team->id, 404);
        Gate::authorize($ability, $store);
    }

    private function authorizeProduct(Team $team, Store $store, Product $product, string $ability): void
    {
        $this->authorizeStore($team, $store, $ability);
        abort_unless($product->store_id === $store->id, 404);
    }

    private function authorizeCategory(Team $team, Store $store, Category $category, string $ability): void
    {
        $this->authorizeStore($team, $store, $ability);
        abort_unless($category->store_id === $store->id, 404);
    }
}

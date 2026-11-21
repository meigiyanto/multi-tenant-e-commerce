<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Support\Stores\StoreContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function show(string $slug, StoreContext $context): Response
    {
        $store = $context->store();

        abort_if($store === null, 404);

        $product = $store->products()
            ->where('slug', $slug)
            ->where('status', 'active')
            ->with([
                'category:id,name',
                'images:id,store_id,product_id,path,alt_text,is_primary,sort_order',
                'variants' => fn ($query) => $query->where('is_active', true)->with('inventory'),
            ])
            ->firstOrFail();

        $relatedProducts = $store->products()
            ->where('status', 'active')
            ->whereKeyNot($product->id)
            ->when($product->category_id, fn ($query) => $query->where('category_id', $product->category_id))
            ->with([
                'images:id,product_id,path,is_primary,sort_order',
                'variants' => fn ($query) => $query->where('is_active', true)->with('inventory'),
            ])
            ->latest()
            ->limit(4)
            ->get()
            ->map(fn (Product $related): array => $this->productCard($related));

        return Inertia::render('storefront/product', [
            'store' => [
                'name' => $store->name,
                'currency' => $store->currency,
                'primary_color' => $store->settings['storefront']['primary_color'] ?? '#2563eb',
                'accent_color' => $store->settings['storefront']['accent_color'] ?? '#0f172a',
            ],
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'description' => $product->description,
                'category' => $product->category?->name,
                'price' => $product->price,
                'compare_at_price' => $product->compare_at_price,
                'images' => $product->images->map(fn ($image): array => [
                    'url' => Storage::disk('public')->url($image->path),
                    'alt_text' => $image->alt_text ?: $product->name,
                    'is_primary' => $image->is_primary,
                ])->values(),
                'variants' => $product->variants->map(fn (ProductVariant $variant): array => [
                    'id' => $variant->id,
                    'name' => $variant->name,
                    'options' => $variant->options ?? [],
                    'price' => $variant->price ?? $product->price,
                    'stock' => $product->track_inventory
                        ? max(0, ($variant->inventory?->quantity ?? 0) - ($variant->inventory?->reserved_quantity ?? 0))
                        : null,
                ])->values(),
            ],
            'related_products' => $relatedProducts,
        ]);
    }

    private function productCard(Product $product): array
    {
        $image = $product->images->firstWhere('is_primary', true) ?? $product->images->first();

        return [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'price' => $product->price,
            'image_url' => $image ? Storage::disk('public')->url($image->path) : null,
            'in_stock' => ! $product->track_inventory || $product->variants->contains(
                fn (ProductVariant $variant): bool => (($variant->inventory?->quantity ?? 0) - ($variant->inventory?->reserved_quantity ?? 0)) > 0,
            ),
        ];
    }
}

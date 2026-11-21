<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Storefront\CartService;
use App\Support\Stores\StoreContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class StorefrontController extends Controller
{
    public function __invoke(Request $request, StoreContext $context, CartService $carts): Response
    {
        $store = $context->store();

        abort_if($store === null, 404);

        $settings = $store->settings['storefront'] ?? [];
        $products = $store->products()
            ->where('status', 'active')
            ->with([
                'category:id,name',
                'images:id,product_id,path,alt_text,is_primary,sort_order',
                'variants' => fn ($query) => $query->where('is_active', true)->with('inventory'),
            ])
            ->latest()
            ->get()
            ->map(fn (Product $product): array => [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'description' => $product->description,
                'category' => $product->category?->name,
                'price' => $product->price,
                'compare_at_price' => $product->compare_at_price,
                'image_url' => ($image = $product->images->firstWhere('is_primary', true) ?? $product->images->first())
                    ? Storage::disk('public')->url($image->path)
                    : null,
                'variants' => $product->variants->map(fn (ProductVariant $variant) => [
                    'id' => $variant->id,
                    'name' => $variant->name,
                    'options' => $variant->options ?? [],
                    'price' => $variant->price ?? $product->price,
                    'stock' => $product->track_inventory ? max(0, ($variant->inventory?->quantity ?? 0) - ($variant->inventory?->reserved_quantity ?? 0)) : null,
                ])->values(),
                'in_stock' => ! $product->track_inventory || $product->variants->contains(fn (ProductVariant $variant) =>
                    (($variant->inventory?->quantity ?? 0) - ($variant->inventory?->reserved_quantity ?? 0)) > 0
                ),
            ]);

        return Inertia::render('storefront/index', [
            'store' => [
                'name' => $store->name,
                'slug' => $store->slug,
                'currency' => $store->currency,
                'description' => $settings['description'] ?? '',
                'logo_url' => filled($settings['logo_path'] ?? null) ? Storage::disk('public')->url($settings['logo_path']) : null,
                'banner_url' => filled($settings['banner_path'] ?? null) ? Storage::disk('public')->url($settings['banner_path']) : null,
                'primary_color' => $settings['primary_color'] ?? '#2563eb',
                'accent_color' => $settings['accent_color'] ?? '#0f172a',
                'navigation' => $settings['navigation'] ?? [],
                'contact' => array_merge(['email' => '', 'phone' => '', 'address' => ''], $settings['contact'] ?? []),
            ],
            'products' => $products,
            'cart_count' => $carts->countForSession($store, $request->session()->getId()),
            'domain' => $context->domain()?->domain,
        ]);
    }
}

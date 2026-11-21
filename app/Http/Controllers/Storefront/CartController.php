<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Services\Storefront\CartService;
use App\Support\Stores\StoreContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class CartController extends Controller
{
    public function __construct(private readonly CartService $carts) {}

    public function show(string $storeSubdomain, Request $request, StoreContext $context): Response
    {
        $store = $context->store();

        abort_if($store === null, 404);

        $cart = $this->carts->current($store, $request->session()->getId());
        $items = $this->carts->items($cart)->get()->map(fn ($item): array => [
            'id' => $item->id,
            'product_id' => $item->product_id,
            'variant_id' => $item->product_variant_id,
            'name' => $item->product->name,
            'slug' => $item->product->slug,
            'image_url' => ($image = $item->product->images->firstWhere('is_primary', true) ?? $item->product->images->first())
                ? Storage::disk('public')->url($image->path)
                : null,
            'variant_name' => $item->variant->name,
            'options' => $item->variant->options ?? [],
            'quantity' => $item->quantity,
            'unit_price' => $item->unit_price,
            'line_total' => number_format((float) $item->unit_price * $item->quantity, 2, '.', ''),
            'stock' => $item->variant->inventory
                ? max(0, $item->variant->inventory->quantity - $item->variant->inventory->reserved_quantity)
                : null,
        ]);

        return Inertia::render('storefront/cart', [
            'store' => ['name' => $store->name, 'currency' => $store->currency],
            'items' => $items,
            'item_count' => $this->carts->count($cart),
            'subtotal' => $this->carts->subtotal($cart),
        ]);
    }

    public function store(string $storeSubdomain, Request $request, StoreContext $context): RedirectResponse
    {
        $store = $context->store();
        abort_if($store === null, 404);

        $data = $request->validate([
            'product_variant_id' => ['required', 'integer', 'min:1'],
            'quantity' => ['sometimes', 'integer', 'min:1', 'max:99'],
        ]);

        $this->carts->add($store, $request->session()->getId(), $data['product_variant_id'], $data['quantity'] ?? 1);

        return back()->with('success', 'Produk berhasil ditambahkan ke keranjang.');
    }

    public function update(string $storeSubdomain, Request $request, int $item, StoreContext $context): RedirectResponse
    {
        $store = $context->store();
        abort_if($store === null, 404);

        $data = $request->validate(['quantity' => ['required', 'integer', 'min:1', 'max:99']]);
        $this->carts->update($store, $request->session()->getId(), $item, $data['quantity']);

        return back()->with('success', 'Jumlah produk diperbarui.');
    }

    public function destroy(Request $request, int $item, StoreContext $context): RedirectResponse
    {
        $store = $context->store();
        abort_if($store === null, 404);

        $this->carts->remove($store, $request->session()->getId(), $item);

        return back()->with('success', 'Produk dihapus dari keranjang.');
    }
}

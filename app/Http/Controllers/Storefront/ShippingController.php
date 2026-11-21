<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Services\Shipping\ShippingQuoteService;
use App\Support\Stores\StoreContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShippingController extends Controller
{
    public function __construct(private readonly ShippingQuoteService $quotes) {}

    public function destinations(Request $request): JsonResponse
    {
        $validated = $request->validate(['search' => ['required', 'string', 'min:3', 'max:100']]);

        return response()->json(['data' => $this->quotes->destinations($validated['search'])]);
    }

    public function quotes(Request $request, StoreContext $context): JsonResponse
    {
        $validated = $request->validate(['destination_id' => ['required', 'integer', 'min:1']]);
        $store = $context->store();
        abort_if($store === null, 404);

        $cart = Cart::query()
            ->where('store_id', $store->id)
            ->where('session_id', $request->session()->getId())
            ->where('status', 'active')
            ->with('items.product')
            ->firstOrFail();

        return response()->json([
            'weight_grams' => $this->quotes->weight($cart),
            'rates' => $this->quotes->rates($store, $cart, (int) $validated['destination_id']),
        ]);
    }
}

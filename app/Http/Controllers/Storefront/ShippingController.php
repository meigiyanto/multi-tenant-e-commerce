<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Services\Shipping\ShippingQuoteService;
use App\Services\Storefront\CartSessionService;
use App\Support\Stores\StoreContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class ShippingController extends Controller
{
    public function __construct(private readonly ShippingQuoteService $quotes, private readonly CartSessionService $cartSessions) {}

    public function destinations(Request $request): JsonResponse
    {
        $validated = $request->validate(['search' => ['required', 'string', 'min:3', 'max:100']]);

        try {
            return response()->json(['data' => $this->quotes->destinations($validated['search'])]);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }

    public function quotes(Request $request, StoreContext $context): SymfonyResponse
    {
        $validated = $request->validate(['destination_id' => ['required', 'integer', 'min:1']]);
        $store = $context->store();
        abort_if($store === null, 404);

        $cartSessionId = $this->cartSessions->id($request);
        $cart = Cart::query()
            ->where('store_id', $store->id)
            ->where('session_id', $cartSessionId)
            ->where('status', 'active')
            ->with('items.product')
            ->firstOrFail();

        try {
            return $this->cartSessions->attach(response()->json([
                'weight_grams' => $this->quotes->weight($cart),
                'rates' => $this->quotes->rates($store, $cart, (int) $validated['destination_id']),
            ]), $request);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }
}

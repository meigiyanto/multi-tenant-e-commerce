<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Payments\PaymentService;
use App\Support\Stores\StoreContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

class PaymentController extends Controller
{
    public function __construct(private readonly PaymentService $payments) {}

    public function start(string $storeSubdomain, string $orderNumber, StoreContext $context): RedirectResponse
    {
        $store = $context->store();
        abort_if($store === null, 404);

        $order = Order::query()
            ->where('store_id', $store->id)
            ->where('order_number', $orderNumber)
            ->firstOrFail();

        $transaction = $this->payments->start($order);

        return redirect()->away($transaction->redirect_url);
    }

    public function notification(Request $request): JsonResponse
    {
        try {
            $this->payments->handleNotification($request->all());
        } catch (RuntimeException $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 400);
        }

        return response()->json(['success' => true]);
    }
}

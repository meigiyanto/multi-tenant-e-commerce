<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Notifications\PlatformNotificationService;
use App\Support\Stores\StoreContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class CustomerRefundRequestController extends Controller
{
    public function store(Request $request, string $storeSubdomain, string $orderNumber, StoreContext $context): RedirectResponse
    {
        $store = $context->store();
        abort_if($store === null, 404);

        $customer = $request->user('customer');
        $order = Order::query()
            ->where('store_id', $store->id)
            ->where('customer_id', $customer->id)
            ->where('order_number', $orderNumber)
            ->firstOrFail();

        $validated = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:3000']]);

        $transaction = $order->paymentTransactions()
            ->where('status', 'paid')
            ->whereNotNull('paid_at')
            ->latest('paid_at')
            ->first();

        if ($order->payment_status !== 'paid' || $transaction === null) {
            throw ValidationException::withMessages([
                'reason' => 'A refund can only be requested for a successfully paid transaction.',
            ]);
        }

        if ($order->refundRequests()->whereIn('status', ['requested', 'under_review', 'processing', 'refunded'])->exists()) {
            throw ValidationException::withMessages(['reason' => 'A refund request already exists for this order.']);
        }

        $refundRequest = $order->refundRequests()->create([
            'store_id' => $store->id,
            'customer_id' => $customer->id,
            'payment_transaction_id' => $transaction->id,
            'reason' => $validated['reason'],
            'status' => 'requested',
        ]);

        app(PlatformNotificationService::class)->notifyAdmins([
            'event' => 'refund_requested', 'title' => 'New refund request',
            'message' => 'Refund requested for order '.$order->order_number.'.',
            'url' => route('platform.admin.refunds.index'), 'subject_id' => $refundRequest->id,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Your refund request has been submitted for review.']);

        return to_route('storefront.account.orders.index', ['storeSubdomain' => $storeSubdomain]);
    }
}

<?php

namespace App\Http\Middleware;

use App\Models\Order;
use App\Services\Stores\StoreDomainService;
use App\Support\Stores\StoreContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveStoreFromOrderNumber
{
    public function __construct(
        private readonly StoreDomainService $domains,
        private readonly StoreContext $context,
    ) {}

    /**
     * Resolve the store for a Midtrans Finish redirect using its signed order token.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $orderNumber = (string) $request->route('orderNumber', '');
        $providedKey = (string) $request->query('key', '');
        $appKey = (string) config('app.key');

        if ($orderNumber === '' || $providedKey === '' || $appKey === '') {
            abort(404);
        }

        $expectedKey = hash_hmac('sha256', $orderNumber, $appKey);
        if (! hash_equals($expectedKey, $providedKey)) {
            abort(404);
        }

        $order = Order::query()
            ->with('store.primaryDomain')
            ->where('order_number', $orderNumber)
            ->first();

        $primaryDomain = $order?->store?->primaryDomain;
        if ($primaryDomain === null) {
            abort(404);
        }

        $domain = $this->domains->resolve($primaryDomain->domain);
        if ($domain === null || $domain->store_id !== $order->store_id) {
            abort(404);
        }

        $this->context->set($domain->store, $domain);
        $request->attributes->set('store', $domain->store);
        $request->attributes->set('store_domain', $domain);
        $request->attributes->set('team', $domain->store->team);

        return $next($request);
    }
}

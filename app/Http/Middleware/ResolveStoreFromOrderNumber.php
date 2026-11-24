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
        $expires = $request->query('expires');
        $appKey = (string) config('app.key');

        if ($orderNumber === '' || $providedKey === '' || $appKey === ''
            || ! is_string($expires)
            || filter_var($expires, FILTER_VALIDATE_INT) === false
            || (int) $expires < now()->timestamp) {
            abort(404);
        }

        $expectedKey = hash_hmac('sha256', $orderNumber.'|'.$expires, $appKey);
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
        if ($domain === null || (int) $domain->store_id !== (int) $order->store_id) {
            abort(404);
        }

        $this->context->set($domain->store, $domain);
        $request->attributes->set('store', $domain->store);
        $request->attributes->set('store_domain', $domain);
        $request->attributes->set('team', $domain->store->team);

        $response = $next($request);
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('Referrer-Policy', 'no-referrer');

        return $response;
    }
}

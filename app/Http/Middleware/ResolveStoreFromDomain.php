<?php

namespace App\Http\Middleware;

use App\Services\Stores\StoreDomainService;
use App\Support\Stores\StoreContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveStoreFromDomain
{
    /**
     * Resolve the storefront from the request hostname.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(
        Request $request,
        Closure $next,
        StoreDomainService $domains,
        StoreContext $context,
    ): Response {
        $domain = $domains->resolve($request->getHost());

        abort_unless($domain?->store, 404);

        $store = $domain->store;

        $context->set($store, $domain);

        $request->attributes->set('store', $store);
        $request->attributes->set('store_domain', $domain);
        $request->attributes->set('team', $store->team);

        return $next($request);
    }
}

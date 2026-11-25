<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureCustomerStoreContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $store = $request->attributes->get('store');
        $customer = Auth::guard('customer')->user();

        if ($customer !== null && $store !== null && (int) $customer->store_id !== (int) $store->id) {
            Auth::guard('customer')->logout();
        }

        return $next($request);
    }
}

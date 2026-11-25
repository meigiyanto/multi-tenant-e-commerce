<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCustomerAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user('customer') !== null, 403);

        if (! $request->user('customer')->hasVerifiedEmail()
            && ! $request->routeIs('storefront.account.verification', 'storefront.account.verification.send')) {
            return redirect()->route('storefront.account.verification', [
                'storeSubdomain' => $request->route('storeSubdomain'),
            ]);
        }

        return $next($request);
    }
}

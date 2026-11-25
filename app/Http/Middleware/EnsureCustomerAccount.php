<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCustomerAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        $customer = $request->user('customer');
        abort_unless($customer !== null, 403);

        if (! $customer->hasVerifiedEmail()
            && ! $request->routeIs(
                'storefront.account.verification',
                'storefront.account.verification.send',
                'storefront.account.verification.verify',
            )) {
            return redirect()->route('storefront.account.verification', [
                'storeSubdomain' => $request->route('storeSubdomain'),
            ]);
        }

        return $next($request);
    }
}

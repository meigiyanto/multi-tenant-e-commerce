<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCustomerAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->isCustomerAccount(), 403);

        if (! $request->user()?->hasVerifiedEmail()
            && ! $request->routeIs('storefront.account.verification', 'storefront.account.verification.send')) {
            return redirect('/account/verify-email');
        }

        return $next($request);
    }
}

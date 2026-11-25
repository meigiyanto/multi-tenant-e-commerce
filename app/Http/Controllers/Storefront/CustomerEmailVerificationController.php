<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Support\Stores\StoreContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerEmailVerificationController extends Controller
{
    public function show(Request $request, StoreContext $context): Response
    {
        $store = $context->store();
        abort_if($store === null, 404);

        return Inertia::render('storefront/account/verify-email', [
            'store' => ['name' => $store->name],
            'status' => $request->session()->get('status'),
            'email' => $request->user()->email,
        ]);
    }

    public function send(Request $request): RedirectResponse
    {
        if (! $request->user()->hasVerifiedEmail()) {
            $request->user()->sendEmailVerificationNotification();
        }

        return back()->with('status', 'verification-link-sent');
    }
}

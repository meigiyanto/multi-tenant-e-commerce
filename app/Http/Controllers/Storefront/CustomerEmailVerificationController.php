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
        $request->session()->put('url.intended', url('/account'));

        return Inertia::render('storefront/account/verify-email', [
            'store' => ['name' => $store->name],
            'status' => $request->session()->get('status'),
            'email' => $request->user('customer')->email,
        ]);
    }

    public function send(Request $request): RedirectResponse
    {
        if (! $request->user('customer')->hasVerifiedEmail()) {
            $request->user('customer')->sendEmailVerificationNotification();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('A new verification link has been sent to your email address.')]);

        return back()->with('status', 'verification-link-sent');
    }
}

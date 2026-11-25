<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Support\Stores\StoreContext;
use Illuminate\Auth\Events\Verified;
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

    public function verify(Request $request, string $storeSubdomain, string $id, string $hash): RedirectResponse
    {
        $customer = $request->user('customer');
        abort_unless(ctype_digit($id) && $customer && (string) $customer->getKey() === $id
            && hash_equals(sha1($customer->getEmailForVerification()), $hash), 403);

        if ($customer->markEmailAsVerified()) {
            event(new Verified($customer));
        }

        return to_route('storefront.account.index', [
            'storeSubdomain' => $storeSubdomain,
        ])->with('verified', true);
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

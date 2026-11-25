<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\CustomerProfileUpdateRequest;
use App\Support\Stores\StoreContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerProfileController extends Controller
{
    public function edit(Request $request, StoreContext $context): Response
    {
        $store = $context->store();
        abort_if($store === null, 404);

        return Inertia::render('storefront/account/profile', [
            'store' => ['name' => $store->name],
            'profile' => $request->user('customer')->only([
                'name', 'email', 'phone', 'shipping_address', 'shipping_city',
                'shipping_province', 'shipping_postal_code',
            ]),
        ]);
    }

    public function update(CustomerProfileUpdateRequest $request, StoreContext $context): RedirectResponse
    {
        $store = $context->store();
        abort_if($store === null, 404);

        $user = $request->user('customer');
        $emailChanged = $user->email !== $request->validated('email');
        $validated = $request->validated();
        unset($validated['current_password']);
        if (blank($validated['password'] ?? null)) {
            unset($validated['password']);
        }
        $user->fill($validated);

        if ($emailChanged) {
            $user->email_verified_at = null;
        }

        $user->save();

        if ($emailChanged) {
            $user->sendEmailVerificationNotification();
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $emailChanged
                ? __('Profile updated. A verification link has been sent to your new email address.')
                : __('Profile updated.'),
        ]);

        return $emailChanged
            ? to_route('storefront.account.verification', ['storeSubdomain' => $request->route('storeSubdomain')])
            : to_route('storefront.account.profile', ['storeSubdomain' => $request->route('storeSubdomain')]);
    }
}

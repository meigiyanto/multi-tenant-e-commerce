<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileUpdateRequest;
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
            'success' => $request->session()->get('success'),
            'error' => $request->session()->get('error'),
            'profile' => $request->user()->only([
                'name', 'email', 'phone', 'shipping_address', 'shipping_city',
                'shipping_province', 'shipping_postal_code',
            ]),
        ]);
    }

    public function update(ProfileUpdateRequest $request, StoreContext $context): RedirectResponse
    {
        $store = $context->store();
        abort_if($store === null, 404);

        $user = $request->user();
        $user->fill($request->validated());

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        return redirect()->route('storefront.account.profile', ['storeSubdomain' => $store->slug])
            ->with('success', 'Profile updated.');
    }
}

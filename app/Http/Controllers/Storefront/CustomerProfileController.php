<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        return Inertia::render('storefront/account/profile', [
            'success' => $request->session()->get('success'),
            'profile' => $request->user()->only([
                'name', 'email', 'phone', 'shipping_address', 'shipping_city',
                'shipping_province', 'shipping_postal_code',
            ]),
        ]);
    }

    public function update(ProfileUpdateRequest $request, StoreContext $context): RedirectResponse
    {
        $user = $request->user();
        $user->fill($request->validated());

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        return redirect()->route('storefront.account.profile', ['storeSubdomain' => $context->store()->slug])->with('success', 'Profile updated.');
    }
}

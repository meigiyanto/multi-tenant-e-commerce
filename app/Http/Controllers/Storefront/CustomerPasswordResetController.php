<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Notifications\Storefront\ResetCustomerPassword;
use App\Support\Stores\StoreContext;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Inertia\Inertia;
use Inertia\Response;

class CustomerPasswordResetController extends Controller
{
    public function requestForm(StoreContext $context): Response
    {
        $store = $context->store();
        abort_if($store === null, 404);

        return Inertia::render('storefront/account/forgot-password', [
            'store' => ['name' => $store->name],
            'status' => request()->session()->get('status'),
        ]);
    }

    public function sendLink(Request $request, StoreContext $context): RedirectResponse
    {
        $store = $context->store();
        abort_if($store === null, 404);
        $data = $request->validate(['email' => ['required', 'email', 'max:255']]);
        $email = Str::lower(trim($data['email']));
        $customer = Customer::query()->where('store_id', $store->id)->where('email', $email)->first();

        if ($customer !== null) {
            $token = Str::random(64);
            \Illuminate\Support\Facades\DB::table('customer_password_reset_tokens')->updateOrInsert(
                ['store_id' => $store->id, 'email' => $email],
                ['token' => Hash::make($token), 'created_at' => now()],
            );
            $customer->notify(new ResetCustomerPassword($token));
        }

        return back()->with('status', 'customer-password-reset-link-sent');
    }

    public function showResetPage(string $token, Request $request, StoreContext $context): Response
    {
        $store = $context->store();
        abort_if($store === null, 404);

        return Inertia::render('storefront/account/reset-password', [
            'store' => ['name' => $store->name],
            'email' => (string) $request->query('email', ''),
            'token' => $token,
        ]);
    }

    public function reset(Request $request, StoreContext $context): RedirectResponse
    {
        $store = $context->store();
        abort_if($store === null, 404);
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);
        $email = Str::lower(trim($data['email']));
        $record = \Illuminate\Support\Facades\DB::table('customer_password_reset_tokens')
            ->where('store_id', $store->id)->where('email', $email)->first();

        if ($record === null || $record->created_at === null
            || now()->diffInSeconds($record->created_at) > (int) config('auth.passwords.customers.expire', 60) * 60
            || ! Hash::check($data['token'], $record->token)) {
            return back()->withErrors(['email' => __('This password reset link is invalid or has expired.')]);
        }

        $customer = Customer::query()->where('store_id', $store->id)->where('email', $email)->first();
        if ($customer === null) {
            return back()->withErrors(['email' => __('This password reset link is invalid or has expired.')]);
        }

        $customer->forceFill(['password' => $data['password']])->save();
        \Illuminate\Support\Facades\DB::table('customer_password_reset_tokens')
            ->where('store_id', $store->id)->where('email', $email)->delete();
        event(new PasswordReset($customer));

        return to_route('storefront.account.login', ['storeSubdomain' => $store->slug])
            ->with('status', 'customer-password-reset');
    }
}

<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Support\Stores\StoreContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Validation\Rule;

class CustomerAuthController extends Controller
{
    public function loginForm(StoreContext $context): Response
    {
        $store = $context->store();
        abort_if($store === null, 404);

        return Inertia::render('storefront/account/login', ['store' => ['name' => $store->name]]);
    }

    public function registerForm(StoreContext $context): Response
    {
        $store = $context->store();
        abort_if($store === null, 404);

        return Inertia::render('storefront/account/register', ['store' => ['name' => $store->name]]);
    }

    public function login(Request $request, StoreContext $context): RedirectResponse
    {
        $store = $context->store();
        abort_if($store === null, 404);
        $credentials = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string'],
            'remember' => ['sometimes', 'boolean'],
        ]);
        $customer = Customer::query()->where('store_id', $store->id)
            ->where('email', mb_strtolower($credentials['email']))->first();

        if (! $customer || ! Hash::check($credentials['password'], $customer->password)) {
            return back()->withErrors(['email' => __('The provided credentials are incorrect.')])->onlyInput('email');
        }

        Auth::guard('customer')->login($customer, (bool) ($credentials['remember'] ?? false));
        $request->session()->regenerate();

        return to_route('storefront.account.index', ['storeSubdomain' => $store->slug]);
    }

    public function register(Request $request, StoreContext $context): RedirectResponse
    {
        $store = $context->store();
        abort_if($store === null, 404);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255',
                Rule::unique('customers', 'email')->where('store_id', $store->id)],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);
        $customer = Customer::query()->create([
            'store_id' => $store->id,
            'name' => $data['name'],
            'email' => mb_strtolower($data['email']),
            'password' => $data['password'],
        ]);

        Auth::guard('customer')->login($customer);
        $request->session()->regenerate();

        return to_route('storefront.account.profile', ['storeSubdomain' => $store->slug])
            ->with('success', __('Your customer account has been created.'));
    }

    public function logout(Request $request, StoreContext $context): RedirectResponse
    {
        Auth::guard('customer')->logout();
        $request->session()->regenerate();
        $request->session()->regenerateToken();

        return redirect()->route('storefront.home', ['storeSubdomain' => $context->store()?->slug]);
    }
}

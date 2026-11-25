<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Customer;
use App\Services\Storefront\CartSessionService;
use App\Support\Stores\StoreContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class CustomerAuthController extends Controller
{
    public function showLoginPage(StoreContext $context): Response
    {
        $store = $context->store();
        abort_if($store === null, 404);

        return Inertia::render('storefront/account/login', [
            'store' => ['name' => $store->name],
            'status' => request()->session()->get('status'),
        ]);
    }

    public function showRegistrationPage(StoreContext $context): Response
    {
        $store = $context->store();
        abort_if($store === null, 404);

        return Inertia::render('storefront/account/register', ['store' => ['name' => $store->name]]);
    }

    public function login(Request $request, StoreContext $context, CartSessionService $cartSessions): RedirectResponse
    {
        $store = $context->store();
        abort_if($store === null, 404);
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);
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
        $this->linkActiveCart($store->id, $cartSessions->id($request), $customer);

        return to_route('storefront.account.index', ['storeSubdomain' => $store->slug]);
    }

    public function register(Request $request, StoreContext $context, CartSessionService $cartSessions): RedirectResponse
    {
        $store = $context->store();
        abort_if($store === null, 404);
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);
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

        $customer->sendEmailVerificationNotification();
        Auth::guard('customer')->login($customer);
        $request->session()->regenerate();
        $this->linkActiveCart($store->id, $cartSessions->id($request), $customer);

        return to_route('storefront.account.profile', ['storeSubdomain' => $store->slug])
            ->with('success', __('Your customer account has been created.'));
    }

    private function linkActiveCart(int $storeId, string $sessionId, Customer $customer): void
    {
        Cart::query()->where('store_id', $storeId)->where('session_id', $sessionId)
            ->where('status', 'active')->update(['customer_id' => $customer->id, 'user_id' => null]);
    }

    public function logout(Request $request, StoreContext $context, CartSessionService $cartSessions): RedirectResponse
    {
        $store = $context->store();
        if ($store !== null) {
            Cart::query()->where('store_id', $store->id)
                ->where('session_id', $cartSessions->id($request))
                ->where('status', 'active')->update(['customer_id' => null]);
        }

        Auth::guard('customer')->logout();
        $request->session()->regenerate();
        $request->session()->regenerateToken();

        return redirect()->route('storefront.home', ['storeSubdomain' => $context->store()?->slug]);
    }
}

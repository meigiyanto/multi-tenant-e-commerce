<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Merchant\MerchantApplicationController;
use App\Http\Controllers\FonnteDeliveryWebhookController;
use App\Http\Controllers\NotificationDeliveryWebhookController;
use App\Http\Controllers\Storefront\CartController;
use App\Http\Controllers\Storefront\CustomerAuthController;
use App\Http\Controllers\Storefront\CustomerAccountController;
use App\Http\Controllers\Storefront\CustomerEmailVerificationController;
use App\Http\Controllers\Storefront\CustomerOrderController;
use App\Http\Controllers\Storefront\CustomerPasswordResetController;
use App\Http\Controllers\Storefront\CustomerProfileController;
use App\Http\Controllers\Storefront\CheckoutController;
use App\Http\Controllers\Storefront\OrderTrackingController;
use App\Http\Controllers\Storefront\PaymentController;
use App\Http\Controllers\Storefront\ProductController;
use App\Http\Controllers\Storefront\ShippingController;
use App\Http\Controllers\Storefront\StorefrontController;
use App\Http\Controllers\Teams\TeamInvitationController;
use App\Http\Middleware\EnsureCustomerAccount;
use App\Http\Middleware\EnsureCustomerStoreContext;
use App\Http\Middleware\EnsureMerchantTeamContext;
use App\Http\Middleware\EnsureTeamMembership;
use App\Http\Middleware\ResolveStoreFromDomain;
use App\Http\Middleware\ResolveStoreFromOrderNumber;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

$storefrontRootDomain = trim((string) config('storefront.root_domain'));

if ($storefrontRootDomain !== '') {
    Route::domain('{storeSubdomain}.'.$storefrontRootDomain)
        ->middleware([ResolveStoreFromDomain::class, EnsureCustomerStoreContext::class])
        ->group(function (): void {
            Route::get('/', StorefrontController::class)->name('storefront.home');
            Route::get('/products/{slug}', [ProductController::class, 'show'])->name('storefront.products.show');
            Route::get('/cart', [CartController::class, 'show'])->name('storefront.cart.show');
            Route::post('/cart/items', [CartController::class, 'store'])->name('storefront.cart.items.store');
            Route::patch('/cart/items/{item}', [CartController::class, 'update'])->whereNumber('item')->name('storefront.cart.items.update');
            Route::delete('/cart/items/{item}', [CartController::class, 'destroy'])->name('storefront.cart.items.destroy');
            Route::middleware('guest:customer')->group(function (): void {
                Route::get('/account/login', [CustomerAuthController::class, 'loginForm'])->name('storefront.account.login');
                Route::post('/account/login', [CustomerAuthController::class, 'login'])->middleware('throttle:5,1')->name('storefront.account.login.submit');
                Route::get('/account/register', [CustomerAuthController::class, 'registerForm'])->name('storefront.account.register');
                Route::post('/account/register', [CustomerAuthController::class, 'register'])->middleware('throttle:5,1')->name('storefront.account.register.submit');
                Route::get('/account/forgot-password', [CustomerPasswordResetController::class, 'requestForm'])->name('storefront.account.password.request');
                Route::post('/account/forgot-password', [CustomerPasswordResetController::class, 'sendLink'])->middleware('throttle:5,1')->name('storefront.account.password.email');
                Route::get('/account/reset-password/{token}', [CustomerPasswordResetController::class, 'resetForm'])->name('storefront.account.password.reset');
                Route::post('/account/reset-password', [CustomerPasswordResetController::class, 'reset'])->middleware('throttle:5,1')->name('storefront.account.password.update');
            });
            Route::post('/account/logout', [CustomerAuthController::class, 'logout'])
                ->middleware('auth:customer')->name('storefront.account.logout');
            Route::middleware(['auth:customer', EnsureCustomerAccount::class])->group(function (): void {
                Route::get('/account/verify-email', [CustomerEmailVerificationController::class, 'show'])->name('storefront.account.verification');
                Route::post('/account/verification-notification', [CustomerEmailVerificationController::class, 'send'])->middleware('throttle:6,1')->name('storefront.account.verification.send');
                Route::get('/account/verify-email/{id}/{hash}', [CustomerEmailVerificationController::class, 'verify'])->middleware('signed')->name('storefront.account.verification.verify');
                Route::get('/account', [CustomerAccountController::class, 'index'])->name('storefront.account.index');
                Route::get('/account/orders', [CustomerOrderController::class, 'index'])->name('storefront.account.orders.index');
                Route::get('/account/profile', [CustomerProfileController::class, 'edit'])->name('storefront.account.profile');
                Route::patch('/account/profile', [CustomerProfileController::class, 'update'])->name('storefront.account.profile.update');
                Route::get('/checkout', [CheckoutController::class, 'show'])->name('storefront.checkout.show');
                Route::post('/checkout', [CheckoutController::class, 'store'])->name('storefront.checkout.store');
            });
            Route::get('/shipping/destinations', [ShippingController::class, 'destinations'])->name('storefront.shipping.destinations');
            Route::get('/shipping/quotes', [ShippingController::class, 'quotes'])->name('storefront.shipping.quotes');
            Route::get('/orders/{orderNumber}', [CheckoutController::class, 'order'])->middleware(['auth:customer', EnsureCustomerAccount::class])->name('storefront.orders.show');
            Route::post('/orders/{orderNumber}/payment', [PaymentController::class, 'start'])->middleware(['auth:customer', EnsureCustomerAccount::class])->name('storefront.orders.payment');
        });
}

Route::get('/orders/{orderNumber}', [CheckoutController::class, 'finish'])
    ->middleware(ResolveStoreFromOrderNumber::class)->name('storefront.orders.finish');
Route::get('/track/{orderNumber}', [OrderTrackingController::class, 'show'])->name('storefront.orders.track');
Route::post('/payments/midtrans/notification', [PaymentController::class, 'notification'])->name('payments.midtrans.notification');
Route::post('/webhooks/fonnte/{secret}', FonnteDeliveryWebhookController::class)->name('webhooks.fonnte');
Route::post('/webhooks/notification-deliveries/{uuid}', NotificationDeliveryWebhookController::class)->name('webhooks.notification-deliveries');
Route::middleware('guest')->get('/platform/login', function (\Illuminate\Http\Request $request) {
    $request->session()->put('platform_login', true);
    return Inertia::render('auth/platform-login', [
        'canResetPassword' => \Laravel\Fortify\Features::enabled(\Laravel\Fortify\Features::resetPasswords()),
        'status' => $request->session()->get('status'),
    ]);
})->name('platform.login');
Route::inertia('/', 'welcome')->name('home');
Route::prefix('{current_team}')->middleware(['auth', 'verified', EnsureTeamMembership::class, EnsureMerchantTeamContext::class])
    ->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');
    });
Route::middleware(['auth'])->group(function () {
    Route::post('invitations/{invitation}/accept', [TeamInvitationController::class, 'accept'])->name('invitations.accept');
    Route::delete('invitations/{invitation}', [TeamInvitationController::class, 'decline'])->name('invitations.decline');
});
Route::middleware(['auth', 'verified'])->prefix('merchant')->name('merchant.')->group(function () {
    Route::get('application', [MerchantApplicationController::class, 'show'])->name('application.show');
    Route::post('application', [MerchantApplicationController::class, 'store'])->middleware('throttle:6,1')->name('application.store');
});

require __DIR__.'/settings.php';
require __DIR__.'/platform.php';

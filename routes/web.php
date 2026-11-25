<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FonnteDeliveryWebhookController;
use App\Http\Controllers\NotificationDeliveryWebhookController;
use App\Http\Controllers\Storefront\CartController;
use App\Http\Controllers\Storefront\CheckoutController;
use App\Http\Controllers\Storefront\OrderTrackingController;
use App\Http\Controllers\Storefront\PaymentController;
use App\Http\Controllers\Storefront\ProductController;
use App\Http\Controllers\Storefront\ShippingController;
use App\Http\Controllers\Storefront\StorefrontController;
use App\Http\Controllers\Teams\TeamInvitationController;
use App\Http\Middleware\EnsureTeamMembership;
use App\Http\Middleware\ResolveStoreFromDomain;
use App\Http\Middleware\ResolveStoreFromOrderNumber;
use Illuminate\Support\Facades\Route;

$storefrontRootDomain = trim((string) config('storefront.root_domain'));

if ($storefrontRootDomain !== '') {
    Route::domain('{storeSubdomain}.'.$storefrontRootDomain)
        ->middleware(ResolveStoreFromDomain::class)
        ->group(function (): void {
            Route::get('/', StorefrontController::class)->name('storefront.home');
            Route::get('/products/{slug}', [ProductController::class, 'show'])->name('storefront.products.show');
            Route::get('/cart', [CartController::class, 'show'])->name('storefront.cart.show');
            Route::post('/cart/items', [CartController::class, 'store'])->name('storefront.cart.items.store');
            Route::patch('/cart/items/{item}', [CartController::class, 'update'])->whereNumber('item')->name('storefront.cart.items.update');
            Route::delete('/cart/items/{item}', [CartController::class, 'destroy'])->whereNumber('item')->name('storefront.cart.items.destroy');
            Route::middleware('auth')->group(function (): void {
                Route::get('/checkout', [CheckoutController::class, 'show'])->name('storefront.checkout.show');
                Route::post('/checkout', [CheckoutController::class, 'store'])->name('storefront.checkout.store');
            });
            Route::get('/shipping/destinations', [ShippingController::class, 'destinations'])->name('storefront.shipping.destinations');
            Route::get('/shipping/quotes', [ShippingController::class, 'quotes'])->name('storefront.shipping.quotes');
            Route::get('/orders/{orderNumber}', [CheckoutController::class, 'order'])->name('storefront.orders.show');
            Route::post('/orders/{orderNumber}/payment', [PaymentController::class, 'start'])->name('storefront.orders.payment');
        });
}

Route::get('/orders/{orderNumber}', [CheckoutController::class, 'finish'])
    ->middleware(ResolveStoreFromOrderNumber::class)
    ->name('storefront.orders.finish');
Route::get('/track/{orderNumber}', [OrderTrackingController::class, 'show'])->name('storefront.orders.track');

Route::post('/payments/midtrans/notification', [PaymentController::class, 'notification'])->name('payments.midtrans.notification');
Route::post('/webhooks/fonnte/{secret}', FonnteDeliveryWebhookController::class)->name('webhooks.fonnte');
Route::post('/webhooks/notification-deliveries/{uuid}', NotificationDeliveryWebhookController::class)->name('webhooks.notification-deliveries');

Route::middleware('guest')->get('/platform/login', function (\\Illuminate\\Http\\Request $request) {\n    $request->session()->put('platform_login', true);\n\n    return Inertia::render('auth/platform-login', [\n        'canResetPassword' => \\Laravel\\Fortify\\Features::enabled(\\Laravel\\Fortify\\Features::resetPasswords()),\n        'status' => $request->session()->get('status'),\n    ]);\n})->name('platform.login');\n\nRoute::inertia('/', 'welcome')->name('home');

Route::prefix('{current_team}')
    ->middleware(['auth', 'verified', EnsureTeamMembership::class])
    ->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');
    });

Route::middleware(['auth'])->group(function () {
    Route::post('invitations/{invitation}/accept', [TeamInvitationController::class, 'accept'])->name('invitations.accept');
    Route::delete('invitations/{invitation}', [TeamInvitationController::class, 'decline'])->name('invitations.decline');
});

require __DIR__.'/settings.php';
require __DIR__.'/platform.php';

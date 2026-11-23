<?php

use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use App\Http\Controllers\Teams\StoreCatalogController;
use App\Http\Controllers\Teams\StoreController;
use App\Http\Controllers\Teams\StoreDashboardController;
use App\Http\Controllers\Teams\StoreOrderController;
use App\Http\Controllers\Teams\TeamController;
use App\Http\Controllers\Teams\TeamInvitationController;
use App\Http\Controllers\Teams\TeamMemberController;
use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/profile');
    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::get('settings/security', [SecurityController::class, 'edit'])->middleware(RequirePassword::class)->name('security.edit');
    Route::put('settings/password', [SecurityController::class, 'update'])->middleware('throttle:6,1')->name('user-password.update');
    Route::inertia('settings/appearance', 'settings/appearance')->name('appearance.edit');
    Route::get('settings/teams', [TeamController::class, 'index'])->name('teams.index');
    Route::post('settings/teams', [TeamController::class, 'store'])->name('teams.store');

    Route::middleware(EnsureTeamMembership::class)->group(function () {
        Route::get('settings/teams/{team}', [TeamController::class, 'edit'])->name('teams.edit');
        Route::get('settings/teams/{team}/stores', [StoreController::class, 'index'])->name('teams.stores.index');
        Route::post('settings/teams/{team}/stores', [StoreController::class, 'store'])->name('teams.stores.store');
        Route::get('settings/teams/{team}/stores/{store}', [StoreController::class, 'show'])->name('teams.stores.show');
        Route::get('settings/teams/{team}/stores/{store}/dashboard', StoreDashboardController::class)->name('teams.stores.dashboard');
        Route::get('settings/teams/{team}/stores/{store}/orders', [StoreOrderController::class, 'index'])->name('teams.stores.orders.index');
        Route::get('settings/teams/{team}/stores/{store}/orders/{order}', [StoreOrderController::class, 'show'])->name('teams.stores.orders.show');
        Route::patch('settings/teams/{team}/stores/{store}/orders/{order}/status', [StoreOrderController::class, 'updateStatus'])->name('teams.stores.orders.status');
        Route::get('settings/teams/{team}/stores/{store}/settings', [StoreController::class, 'edit'])->name('teams.stores.edit');
        Route::patch('settings/teams/{team}/stores/{store}', [StoreController::class, 'update'])->name('teams.stores.update');
        Route::patch('settings/teams/{team}/stores/{store}/status', [StoreController::class, 'updateStatus'])->name('teams.stores.status');
        Route::post('settings/teams/{team}/stores/{store}/domains', [StoreController::class, 'addDomain'])->name('teams.stores.domains.store');
        Route::post('settings/teams/{team}/stores/{store}/domains/{domain}/verification', [StoreController::class, 'startDomainVerification'])->name('teams.stores.domains.verification.start');
        Route::post('settings/teams/{team}/stores/{store}/domains/{domain}/verification/check', [StoreController::class, 'verifyDomain'])->name('teams.stores.domains.verification.check');
        Route::patch('settings/teams/{team}/stores/{store}/domains/{domain}/status', [StoreController::class, 'updateDomainStatus'])->name('teams.stores.domains.status');
        Route::get('settings/teams/{team}/stores/{store}/catalog', [StoreCatalogController::class, 'index'])->name('teams.stores.catalog.index');
        Route::get('settings/teams/{team}/stores/{store}/catalog/products/create', [StoreCatalogController::class, 'create'])->name('teams.stores.catalog.products.create');
        Route::post('settings/teams/{team}/stores/{store}/catalog/products', [StoreCatalogController::class, 'store'])->name('teams.stores.catalog.products.store');
        Route::get('settings/teams/{team}/stores/{store}/catalog/products/{product}/edit', [StoreCatalogController::class, 'edit'])->name('teams.stores.catalog.products.edit');
        Route::patch('settings/teams/{team}/stores/{store}/catalog/products/{product}', [StoreCatalogController::class, 'update'])->name('teams.stores.catalog.products.update');
        Route::delete('settings/teams/{team}/stores/{store}/catalog/products/{product}', [StoreCatalogController::class, 'destroy'])->name('teams.stores.catalog.products.destroy');
        Route::delete('settings/teams/{team}/stores/{store}/catalog/products/{product}/images/{image}', [StoreCatalogController::class, 'destroyImage'])->name('teams.stores.catalog.images.destroy');
        Route::post('settings/teams/{team}/stores/{store}/catalog/products/{product}/variants/{variant}/stock', [StoreCatalogController::class, 'adjustStock'])->name('teams.stores.catalog.stock.adjust');
        Route::delete('settings/teams/{team}/stores/{store}/catalog/products/{product}/variants/{variant}', [StoreCatalogController::class, 'destroyVariant'])->name('teams.stores.catalog.variants.destroy');
        Route::post('settings/teams/{team}/stores/{store}/catalog/categories', [StoreCatalogController::class, 'storeCategory'])->name('teams.stores.catalog.categories.store');
        Route::patch('settings/teams/{team}/stores/{store}/catalog/categories/{category}', [StoreCatalogController::class, 'updateCategory'])->name('teams.stores.catalog.categories.update');
        Route::delete('settings/teams/{team}/stores/{store}/catalog/categories/{category}', [StoreCatalogController::class, 'destroyCategory'])->name('teams.stores.catalog.categories.destroy');
        Route::patch('settings/teams/{team}', [TeamController::class, 'update'])->name('teams.update');
        Route::delete('settings/teams/{team}', [TeamController::class, 'destroy'])->name('teams.destroy');
        Route::post('settings/teams/{team}/switch', [TeamController::class, 'switch'])->name('teams.switch');
        Route::delete('settings/teams/{team}/leave', [TeamController::class, 'leave'])->name('teams.leave');
        Route::patch('settings/teams/{team}/members/{user}', [TeamMemberController::class, 'update'])->name('teams.members.update');
        Route::delete('settings/teams/{team}/members/{user}', [TeamMemberController::class, 'destroy'])->name('teams.members.destroy');
        Route::post('settings/teams/{team}/invitations', [TeamInvitationController::class, 'store'])->name('teams.invitations.store');
        Route::delete('settings/teams/{team}/invitations/{invitation}', [TeamInvitationController::class, 'destroy'])->name('teams.invitations.destroy');
    });
});

Route::get('.well-known/passkey-endpoints', function () {
    return response()->json(['enroll' => route('security.edit'), 'manage' => route('security.edit')]);
})->name('well-known.passkeys');

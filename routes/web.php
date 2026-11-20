<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Storefront\StorefrontController;
use App\Http\Controllers\Teams\TeamInvitationController;
use App\Http\Middleware\EnsureTeamMembership;
use App\Http\Middleware\ResolveStoreFromDomain;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

$storefrontRootDomain = trim((string) config('storefront.root_domain'));

if ($storefrontRootDomain !== '') {
    Route::domain('{storeSubdomain}.'.$storefrontRootDomain)
        ->middleware(ResolveStoreFromDomain::class)
        ->get('/', StorefrontController::class)
        ->name('storefront.home');
}

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

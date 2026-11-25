<?php

use App\\Http\\Controllers\\Platform\\MerchantApplicationController;
use App\\Http\\Controllers\\Platform\\TeamController;
use App\\Http\\Middleware\\EnsurePlatformAdmin;
use Illuminate\\Support\\Facades\\Route;

Route::middleware(['auth', 'verified', EnsurePlatformAdmin::class])
    ->prefix('platform/admin')
    ->name('platform.admin.')
    ->group(function () {
        Route::get('teams', [TeamController::class, 'index'])->name('teams.index');
        Route::patch('teams/{team}/status', [TeamController::class, 'updateStatus'])->name('teams.status');
        Route::get('merchant-applications', [MerchantApplicationController::class, 'index'])->name('merchant-applications.index');
        Route::patch('merchant-applications/{application}', [MerchantApplicationController::class, 'update'])->name('merchant-applications.update');
    });

<?php

use App\Enums\StoreDomainStatus;
use App\Models\StoreDomain;
use App\Models\TeamInvitation;
use App\Services\Payments\PaymentService;
use Illuminate\Support\Facades\Schedule;

Schedule::call(function () {
    TeamInvitation::query()
        ->whereNotNull('expires_at')
        ->where('expires_at', '<', now())
        ->delete();
})->daily()->description('Delete expired team invitations');

Schedule::call(function () {
    StoreDomain::query()
        ->where('status', StoreDomainStatus::Verifying->value)
        ->whereNotNull('verification_expires_at')
        ->where('verification_expires_at', '<=', now())
        ->update([
            'status' => StoreDomainStatus::Expired->value,
            'verification_token' => null,
            'updated_at' => now(),
        ]);
})->everyMinute()->description('Expire overdue custom domain verification challenges');

Schedule::call(fn () => app(PaymentService::class)->reconcilePendingTransactions('midtrans'))
    ->everyFiveMinutes()
    ->name('payments-midtrans-reconciliation')
    ->withoutOverlapping()
    ->description('Reconcile pending Midtrans payments against the status API');

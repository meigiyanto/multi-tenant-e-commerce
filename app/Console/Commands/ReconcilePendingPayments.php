<?php

namespace App\Console\Commands;

use App\Services\Payments\PaymentService;
use Illuminate\Console\Command;

class ReconcilePendingPayments extends Command
{
    protected $signature = 'payments:reconcile-pending {--gateway=midtrans}';

    protected $description = 'Reconcile pending payment transactions with the payment gateway status API';

    public function handle(PaymentService $payments): int
    {
        $gateway = (string) $this->option('gateway');

        $this->info("Reconciling pending payments for [{$gateway}]...");

        $count = $payments->reconcilePendingTransactions($gateway);

        $this->info("Reconciliation completed. Terminal transactions updated: {$count}.");

        return self::SUCCESS;
    }
}

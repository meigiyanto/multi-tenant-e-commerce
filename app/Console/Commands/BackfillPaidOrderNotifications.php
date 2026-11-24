<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\Notifications\OrderNotificationService;
use Illuminate\Console\Command;

class BackfillPaidOrderNotifications extends Command
{
    protected $signature = 'notifications:backfill-paid-orders {--dispatch : Create delivery records and queue actual notifications} {--order= : Limit processing to one order ID}';

    protected $description = 'Backfill payment-confirmation notifications for orders paid before notification tracking was enabled';

    public function handle(OrderNotificationService $notifications): int
    {
        $dispatch = (bool) $this->option('dispatch');
        
        $query = Order::query()
            ->where('payment_status', 'paid')
            ->whereHas('paymentTransactions', fn ($query) => $query->where('status', 'paid'))
            ->with('notificationDeliveries')
            ->orderBy('id');

        if ($orderId = $this->option('order')) {
            $query->whereKey((int) $orderId);
        }

        $ordersChecked = 0;
        $eligibleMail = 0;
        $eligibleWhatsApp = 0;
        $alreadyRecorded = 0;
        $processed = 0;

        $query->chunkById(100, function ($orders) use (
            $dispatch,
            $notifications,
            &$ordersChecked,
            &$eligibleMail,
            &$eligibleWhatsApp,
            &$alreadyRecorded,
            &$processed,
        ): void {
            foreach ($orders as $order) {
                $ordersChecked++;
                
                $existing = $order->notificationDeliveries
                    ->where('event', 'payment_confirmed')
                    ->keyBy('channel');
                
                $channels = [];

                if (filter_var($order->customer_email, FILTER_VALIDATE_EMAIL)) {
                    if ($existing->has('mail')) {
                        $alreadyRecorded++;
                    } else {
                        $channels[] = 'mail';
                        $eligibleMail++;
                    }
                }

                $phone = preg_replace('/\D+/', '', (string) $order->customer_phone);
                
                if ($phone !== '') {
                    if ($existing->has('whatsapp')) {
                        $alreadyRecorded++;
                    } else {
                        $channels[] = 'whatsapp';
                        $eligibleWhatsApp++;
                    }
                }

                if ($channels === []) {
                    continue;
                }

                if ($dispatch) {
                    $notifications->queuePaymentConfirmed($order->id);
                    $processed++;
                    continue;
                }

                $this->line($order->order_number.': '.implode(', ', $channels));
            }
        });

        $this->newLine();
        $this->table(['Metric', 'Count'], [
            ['Paid orders checked', $ordersChecked],
            ['Eligible email deliveries', $eligibleMail],
            ['Eligible WhatsApp deliveries', $eligibleWhatsApp],
            ['Channels already recorded', $alreadyRecorded],
            ['Orders processed', $processed],
        ]);

        if ($dispatch === false) {
            $this->warn('DRY RUN: no delivery records were created and no messages were queued.');
            $this->line('Run with --dispatch to create missing records and queue email/WhatsApp messages.');

            return self::SUCCESS;
        }

        $this->info('Backfill completed. Notifications were queued for eligible channels.');

        return self::SUCCESS;
    }
}
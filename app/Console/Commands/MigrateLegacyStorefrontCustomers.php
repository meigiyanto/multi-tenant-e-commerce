<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class MigrateLegacyStorefrontCustomers extends Command
{
    protected $signature = 'customers:migrate-legacy {--apply : Apply the migration instead of showing a dry-run report}';

    protected $description = 'Move eligible legacy storefront customer ownership from users to store-scoped customers without changing platform users or teams.';

    public function handle(): int
    {
        foreach (['customers', 'orders', 'carts', 'team_members', 'platform_admins'] as $table) {
            if (! Schema::hasTable($table)) {
                $this->error("Required table [{$table}] does not exist.");

                return self::FAILURE;
            }
        }

        foreach ([['orders', 'customer_id'], ['carts', 'customer_id']] as [$table, $column]) {
            if (! Schema::hasColumn($table, $column)) {
                $this->error("Column [{$table}.{$column}] does not exist. Run the customer schema migrations first.");

                return self::FAILURE;
            }
        }

        $users = DB::table('users')
            ->whereNull('current_team_id')
            ->whereNotExists(fn ($query) => $query->selectRaw('1')
                ->from('platform_admins')->whereColumn('platform_admins.user_id', 'users.id'))
            ->whereNotExists(fn ($query) => $query->selectRaw('1')
                ->from('team_members')->whereColumn('team_members.user_id', 'users.id'))
            ->where(function ($query): void {
                $query->whereExists(fn ($orders) => $orders->selectRaw('1')
                    ->from('orders')->whereColumn('orders.user_id', 'users.id')->whereNull('orders.customer_id'))
                    ->orWhereExists(fn ($carts) => $carts->selectRaw('1')
                        ->from('carts')->whereColumn('carts.user_id', 'users.id')->whereNull('carts.customer_id'));
            })
            ->orderBy('users.id')
            ->get(['users.id', 'users.name', 'users.email', 'users.password', 'users.phone',
                'users.shipping_address', 'users.shipping_city', 'users.shipping_province',
                'users.shipping_postal_code', 'users.email_verified_at']);

        $legacyUsers = DB::table('users')
            ->where(function ($query): void {
                $query->whereExists(fn ($orders) => $orders->selectRaw('1')
                    ->from('orders')->whereColumn('orders.user_id', 'users.id')->whereNull('orders.customer_id'))
                    ->orWhereExists(fn ($carts) => $carts->selectRaw('1')
                        ->from('carts')->whereColumn('carts.user_id', 'users.id')->whereNull('carts.customer_id'));
            })
            ->get(['users.id', 'users.email', 'users.current_team_id']);
        $eligibleIds = $users->pluck('id')->all();
        $blockedUsers = $legacyUsers->reject(fn ($user): bool => in_array($user->id, $eligibleIds, true));

        foreach ($blockedUsers as $user) {
            $reasons = [];
            if ($user->current_team_id !== null) {
                $reasons[] = 'current_team_id is set';
            }
            if (DB::table('platform_admins')->where('user_id', $user->id)->exists()) {
                $reasons[] = 'platform administrator';
            }
            if (DB::table('team_members')->where('user_id', $user->id)->exists()) {
                $reasons[] = 'team membership exists';
            }
            $this->warn("Manual review required for user {$user->id} ({$user->email}): ".implode(', ', $reasons).'.');
        }

        $eligible = 0;
        $conflicts = 0;
        $linkedOrders = 0;
        $linkedCarts = 0;
        $apply = (bool) $this->option('apply');

        foreach ($users as $user) {
            $storeIds = DB::table('orders')->where('user_id', $user->id)->whereNull('customer_id')
                ->pluck('store_id')
                ->merge(DB::table('carts')->where('user_id', $user->id)->whereNull('customer_id')->pluck('store_id'))
                ->unique()->values();

            foreach ($storeIds as $storeId) {
                $email = Str::lower(trim((string) $user->email));
                if ($email === '') {
                    $conflicts++;
                    $this->warn("Skipped user {$user->id}: empty email.");

                    continue;
                }

                $existing = DB::table('customers')->where('store_id', $storeId)->where('email', $email)->first();
                if ($existing !== null) {
                    $conflicts++;
                    $this->warn("Skipped user {$user->id}, store {$storeId}: customer email already exists; manual ownership review required.");

                    continue;
                }

                $eligible++;
                if (! $apply) {
                    $this->line("Would migrate user {$user->id} ({$email}) to store {$storeId}.");
                    continue;
                }

                DB::transaction(function () use ($user, $storeId, $email, &$linkedOrders, &$linkedCarts): void {
                    $customerId = DB::table('customers')->insertGetId([
                        'store_id' => $storeId,
                        'name' => $user->name,
                        'email' => $email,
                        'email_verified_at' => $user->email_verified_at,
                        'password' => $user->password,
                        'phone' => $user->phone,
                        'shipping_address' => $user->shipping_address,
                        'shipping_city' => $user->shipping_city,
                        'shipping_province' => $user->shipping_province,
                        'shipping_postal_code' => $user->shipping_postal_code,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $linkedOrders += DB::table('orders')
                        ->where('store_id', $storeId)->where('user_id', $user->id)
                        ->whereNull('customer_id')
                        ->update(['customer_id' => $customerId, 'user_id' => null]);

                    $linkedCarts += DB::table('carts')
                        ->where('store_id', $storeId)->where('user_id', $user->id)
                        ->whereNull('customer_id')
                        ->update(['customer_id' => $customerId, 'user_id' => null]);
                });
            }
        }

        $this->newLine();
        $this->info($apply ? 'Legacy customer migration applied.' : 'Dry-run only; no database rows were changed.');
        $this->table(['Metric', 'Count'], [
            ['Eligible customer/store identities', $eligible],
            ['Conflicts requiring manual review', $conflicts],
            ['Blocked legacy users requiring team/platform review', $blockedUsers->count()],
            ['Orders linked', $apply ? $linkedOrders : 'not applied'],
            ['Carts linked', $apply ? $linkedCarts : 'not applied'],
        ]);

        if (! $apply) {
            $this->comment('Review the report, back up the database, then rerun with --apply.');
        }

        $this->comment('Users, current_team_id values, platform-admin records, and team memberships are never modified or deleted.');

        return self::SUCCESS;
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->string('shipping_tracking_number', 100)->nullable()->after('shipping_service')->index();
            $table->text('shipping_tracking_url')->nullable()->after('shipping_tracking_number');
            $table->timestamp('shipped_at')->nullable()->after('shipping_tracking_url');
            $table->timestamp('delivered_at')->nullable()->after('shipped_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex(['shipping_tracking_number']);
            $table->dropColumn([
                'shipping_tracking_number',
                'shipping_tracking_url',
                'shipped_at',
                'delivered_at',
            ]);
        });
    }
};

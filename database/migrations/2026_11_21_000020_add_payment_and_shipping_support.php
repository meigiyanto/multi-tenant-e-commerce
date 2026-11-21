<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedInteger('weight_grams')->default(1000)->after('track_inventory');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->boolean('track_inventory')->default(false)->after('quantity');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('shipping_destination_id')->nullable()->after('shipping_postal_code');
            $table->string('shipping_destination_label')->nullable()->after('shipping_destination_id');
            $table->string('shipping_provider', 40)->nullable()->after('shipping_destination_label');
            $table->string('shipping_courier', 40)->nullable()->after('shipping_provider');
            $table->string('shipping_service', 80)->nullable()->after('shipping_courier');
            $table->unsignedInteger('shipping_weight_grams')->default(0)->after('shipping_service');
        });

        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('order_id');
            $table->string('gateway', 40);
            $table->string('gateway_order_id', 80)->unique();
            $table->string('gateway_transaction_id')->nullable();
            $table->string('token')->nullable();
            $table->text('redirect_url')->nullable();
            $table->decimal('amount', 15, 2);
            $table->char('currency', 3);
            $table->string('status', 30)->default('pending');
            $table->json('metadata')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->foreign(['store_id', 'order_id'])->references(['store_id', 'id'])->on('orders')->cascadeOnDelete();
            $table->index(['store_id', 'order_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('track_inventory');
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'shipping_destination_id', 'shipping_destination_label', 'shipping_provider',
                'shipping_courier', 'shipping_service', 'shipping_weight_grams',
            ]);
        });
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('weight_grams');
        });
    }
};

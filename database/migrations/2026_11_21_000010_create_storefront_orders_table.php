<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('order_number', 40)->unique();
            $table->string('status', 30)->default('pending');
            $table->string('payment_status', 30)->default('pending');
            $table->string('customer_name', 160);
            $table->string('customer_email', 180);
            $table->string('customer_phone', 40);
            $table->text('shipping_address');
            $table->string('shipping_city', 120);
            $table->string('shipping_province', 120);
            $table->string('shipping_postal_code', 20);
            $table->text('customer_note')->nullable();
            $table->char('currency', 3);
            $table->decimal('subtotal', 15, 2);
            $table->decimal('shipping_amount', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('total', 15, 2);
            $table->timestamp('placed_at');
            $table->timestamps();
            $table->unique(['store_id', 'id']);
            $table->index(['store_id', 'status', 'created_at']);
            $table->index(['store_id', 'customer_email', 'created_at']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('product_variant_id');
            $table->string('product_name', 180);
            $table->string('variant_name', 120);
            $table->string('sku', 100);
            $table->json('options')->nullable();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 15, 2);
            $table->decimal('line_total', 15, 2);
            $table->timestamps();
            $table->foreign(['store_id', 'order_id'])->references(['store_id', 'id'])->on('orders')->cascadeOnDelete();
            $table->foreign(['store_id', 'product_id'])->references(['store_id', 'id'])->on('products')->restrictOnDelete();
            $table->foreign(['store_id', 'product_variant_id'])->references(['store_id', 'id'])->on('product_variants')->restrictOnDelete();
            $table->index(['store_id', 'order_id']);
        });

        Schema::create('order_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('order_id');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->text('note')->nullable();
            $table->timestamps();
            $table->foreign(['store_id', 'order_id'])->references(['store_id', 'id'])->on('orders')->cascadeOnDelete();
            $table->index(['store_id', 'order_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_status_histories');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('inventory_id');
            $table->unsignedBigInteger('product_variant_id');
            $table->unsignedInteger('quantity');
            $table->string('status', 20)->default('reserved');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamps();
            $table->unique(['order_id', 'product_variant_id']);
            $table->index(['store_id', 'status', 'created_at']);
            $table->foreign(['store_id', 'order_id'])->references(['store_id', 'id'])->on('orders')->cascadeOnDelete();
            $table->foreign(['store_id', 'inventory_id'])->references(['store_id', 'id'])->on('inventories')->cascadeOnDelete();
            $table->foreign(['store_id', 'product_variant_id'])->references(['store_id', 'id'])->on('product_variants')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_reservations');
    }
};

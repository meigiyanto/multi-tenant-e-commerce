<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('name', 120);
            $table->string('slug', 140);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['store_id', 'id']);
            $table->unique(['store_id', 'slug']);
            $table->foreign(['store_id', 'parent_id'])->references(['store_id', 'id'])->on('categories')->cascadeOnDelete();
            $table->index(['store_id', 'is_active', 'sort_order']);
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->string('name', 180);
            $table->string('slug', 200);
            $table->string('status', 20)->default('draft');
            $table->text('description')->nullable();
            $table->decimal('price', 15, 2);
            $table->decimal('compare_at_price', 15, 2)->nullable();
            $table->boolean('track_inventory')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['store_id', 'id']);
            $table->unique(['store_id', 'slug']);
            $table->foreign(['store_id', 'category_id'])->references(['store_id', 'id'])->on('categories')->nullOnDelete();
            $table->index(['store_id', 'status', 'created_at']);
        });

        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('product_id');
            $table->string('name', 120)->default('Default');
            $table->string('sku', 100);
            $table->json('options')->nullable();
            $table->decimal('price', 15, 2)->nullable();
            $table->decimal('compare_at_price', 15, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['store_id', 'id']);
            $table->unique(['store_id', 'sku']);
            $table->foreign(['store_id', 'product_id'])->references(['store_id', 'id'])->on('products')->cascadeOnDelete();
            $table->index(['store_id', 'product_id']);
        });

        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('product_id');
            $table->string('path');
            $table->string('alt_text')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
            $table->foreign(['store_id', 'product_id'])->references(['store_id', 'id'])->on('products')->cascadeOnDelete();
            $table->index(['store_id', 'product_id', 'sort_order']);
        });

        Schema::create('inventories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('product_variant_id');
            $table->unsignedInteger('quantity')->default(0);
            $table->unsignedInteger('reserved_quantity')->default(0);
            $table->unsignedInteger('low_stock_threshold')->default(0);
            $table->timestamps();
            $table->unique(['store_id', 'product_variant_id']);
            $table->foreign(['store_id', 'product_variant_id'])->references(['store_id', 'id'])->on('product_variants')->cascadeOnDelete();
            $table->index(['store_id', 'quantity']);
        });

        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20);
            $table->integer('quantity');
            $table->unsignedInteger('balance_after');
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['store_id', 'inventory_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('inventories');
        Schema::dropIfExists('product_images');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('products');
        Schema::dropIfExists('categories');
    }
};

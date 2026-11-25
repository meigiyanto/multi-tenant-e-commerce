<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('email');
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('phone', 40)->nullable();
            $table->text('shipping_address')->nullable();
            $table->string('shipping_city', 120)->nullable();
            $table->string('shipping_province', 120)->nullable();
            $table->string('shipping_postal_code', 30)->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->unique(['store_id', 'email']);
            $table->unique(['store_id', 'id']);
            $table->index(['store_id', 'created_at']);
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->unsignedBigInteger('customer_id')->nullable()->after('user_id');
            $table->foreign(['store_id', 'customer_id'])->references(['store_id', 'id'])
                ->on('customers')->restrictOnDelete();
            $table->index(['store_id', 'customer_id']);
        });

        Schema::table('carts', function (Blueprint $table): void {
            $table->unsignedBigInteger('customer_id')->nullable()->after('user_id');
            $table->foreign(['store_id', 'customer_id'])->references(['store_id', 'id'])
                ->on('customers')->restrictOnDelete();
            $table->index(['store_id', 'customer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('carts', function (Blueprint $table): void {
            $table->dropForeign(['store_id', 'customer_id']);
            $table->dropIndex(['store_id', 'customer_id', 'status']);
            $table->dropColumn('customer_id');
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropForeign(['customer_id']);
            $table->dropIndex(['store_id', 'customer_id']);
            $table->dropColumn('customer_id');
        });

        Schema::dropIfExists('customers');
    }
};

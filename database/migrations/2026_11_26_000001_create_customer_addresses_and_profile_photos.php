<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->string('avatar_path')->nullable()->after('phone');
        });

        Schema::create('customer_addresses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('customer_id');
            $table->string('label', 80);
            $table->enum('type', ['home', 'office']);
            $table->string('recipient_name');
            $table->string('phone', 40);
            $table->text('address');
            $table->string('city', 120);
            $table->string('province', 120);
            $table->string('postal_code', 30)->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->foreign(['store_id', 'customer_id'])->references(['store_id', 'id'])
                ->on('customers')->cascadeOnDelete();
            $table->index(['store_id', 'customer_id', 'is_default']);
        });

        DB::table('customers')->whereNotNull('shipping_address')->orderBy('id')->chunkById(100, function ($customers): void {
            foreach ($customers as $customer) {
                DB::table('customer_addresses')->insert([
                    'store_id' => $customer->store_id,
                    'customer_id' => $customer->id,
                    'label' => 'Alamat utama',
                    'type' => 'home',
                    'recipient_name' => $customer->name,
                    'phone' => $customer->phone ?? '',
                    'address' => $customer->shipping_address,
                    'city' => $customer->shipping_city ?? '',
                    'province' => $customer->shipping_province ?? '',
                    'postal_code' => $customer->shipping_postal_code,
                    'is_default' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_addresses');
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropColumn('avatar_path');
        });
    }
};

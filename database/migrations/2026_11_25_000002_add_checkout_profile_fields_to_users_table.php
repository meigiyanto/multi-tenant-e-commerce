<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('phone', 40)->nullable()->after('email');
            $table->text('shipping_address')->nullable()->after('phone');
            $table->string('shipping_city', 120)->nullable()->after('shipping_address');
            $table->string('shipping_province', 120)->nullable()->after('shipping_city');
            $table->string('shipping_postal_code', 20)->nullable()->after('shipping_province');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['phone', 'shipping_address', 'shipping_city', 'shipping_province', 'shipping_postal_code']);
        });
    }
};

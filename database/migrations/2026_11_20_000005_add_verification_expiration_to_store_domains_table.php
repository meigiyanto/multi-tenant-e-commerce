<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_domains', function (Blueprint $table) {
            $table->timestamp('verification_expires_at')->nullable()->after('verification_token')->index();
        });
    }

    public function down(): void
    {
        Schema::table('store_domains', function (Blueprint $table) {
            $table->dropIndex(['verification_expires_at']);
            $table->dropColumn('verification_expires_at');
        });
    }
};

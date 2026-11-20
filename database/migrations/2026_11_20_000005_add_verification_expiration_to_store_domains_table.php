<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_domains', function (Blueprint $table) {
            $table->timestamp('verification_expires_at')->nullable()->after('verification_token')->index();
        });

        DB::table('store_domains')
            ->where('status', 'verifying')
            ->whereNotNull('verification_token')
            ->update(['verification_expires_at' => now()->addHours(72)]);
    }

    public function down(): void
    {
        Schema::table('store_domains', function (Blueprint $table) {
            $table->dropIndex(['verification_expires_at']);
            $table->dropColumn('verification_expires_at');
        });
    }
};

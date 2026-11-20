<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained('teams')->restrictOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('status', 20)->default('draft');
            $table->string('timezone', 64)->default('UTC');
            $table->char('currency', 3)->default('IDR');
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['team_id', 'slug']);
            $table->index(['team_id', 'status']);
        });

        Schema::create('store_domains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('domain', 253)->unique();
            $table->string('type', 20)->default('subdomain');
            $table->boolean('is_primary')->default(false);
            $table->string('status', 20)->default('pending');
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'status']);
            $table->index(['store_id', 'is_primary']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_domains');
        Schema::dropIfExists('stores');
    }
};

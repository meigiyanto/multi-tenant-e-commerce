<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_deliveries', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('order_id');
            $table->string('event', 60);
            $table->string('channel', 30);
            $table->string('recipient', 255);
            $table->string('status', 30)->default('queued');
            $table->string('provider', 60)->nullable();
            $table->string('provider_message_id')->nullable()->index();
            $table->text('last_error')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();
            $table->foreign(['store_id', 'order_id'])->references(['store_id', 'id'])->on('orders')->cascadeOnDelete();
            $table->unique(['order_id', 'event', 'channel']);
            $table->index(['store_id', 'order_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_deliveries');
    }
};

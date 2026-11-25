<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refund_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('customer_id');
            $table->foreignId('payment_transaction_id')->constrained('payment_transactions')->restrictOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 30)->default('requested');
            $table->text('reason');
            $table->text('admin_notes')->nullable();
            $table->string('refund_reference', 120)->nullable();
            $table->text('result_notes')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->foreign(['store_id', 'order_id'])->references(['store_id', 'id'])->on('orders')->cascadeOnDelete();
            $table->foreign(['store_id', 'customer_id'])->references(['store_id', 'id'])->on('customers')->cascadeOnDelete();
            $table->index(['store_id', 'status', 'created_at']);
            $table->index(['store_id', 'order_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refund_requests');
    }
};

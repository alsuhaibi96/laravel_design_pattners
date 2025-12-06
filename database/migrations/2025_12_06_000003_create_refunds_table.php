<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            
            // Refund must go through original gateway
            $table->string('gateway');
            
            // Amount (can be partial refund)
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('USD');
            
            // Status
            $table->string('status')->default('pending'); // pending, processing, success, failed
            
            // Reason
            $table->string('reason')->nullable();
            $table->text('notes')->nullable();
            
            // Gateway response
            $table->string('gateway_refund_id')->nullable();
            $table->json('gateway_response')->nullable();
            
            // Who initiated
            $table->foreignId('initiated_by')->nullable()->constrained('users')->nullOnDelete();
            
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            
            $table->index(['payment_id', 'status']);
            $table->index('gateway');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('refunds');
    }
};


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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('idempotency_key')->unique()->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            
            // Order/Cart reference
            $table->string('order_id')->nullable();
            
            // Amount details
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('USD');
            
            // Payment method preference
            $table->string('payment_method')->nullable(); // credit_card, paypal, bank_transfer
            $table->string('preferred_gateway')->nullable();
            
            // Final gateway used
            $table->string('final_gateway')->nullable();
            
            // Status tracking
            $table->string('status')->default('pending'); // pending, processing, success, failed, refunded
            $table->string('failure_reason')->nullable();
            
            // Geographic info for routing
            $table->string('country_code', 2)->nullable();
            $table->string('ip_address')->nullable();
            
            // Gateway response data (no sensitive card data - PCI compliant)
            $table->string('gateway_transaction_id')->nullable();
            $table->json('gateway_metadata')->nullable();
            
            // Retry tracking
            $table->unsignedTinyInteger('attempt_count')->default(0);
            $table->unsignedTinyInteger('max_retries')->default(2);
            
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            
            // Indexes for common queries
            $table->index(['status', 'created_at']);
            $table->index(['user_id', 'status']);
            $table->index('order_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};


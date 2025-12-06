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
        Schema::create('payment_attempts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            
            // Gateway used for this attempt
            $table->string('gateway');
            $table->unsignedTinyInteger('attempt_number');
            
            // Status of this specific attempt
            $table->string('status'); // initiated, success, failed, timeout, rate_limited
            
            // Error details
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();
            $table->boolean('is_retryable')->default(false);
            
            // Gateway response
            $table->string('gateway_transaction_id')->nullable();
            $table->json('gateway_request')->nullable(); // Sanitized request (no sensitive data)
            $table->json('gateway_response')->nullable();
            
            // Timing
            $table->unsignedInteger('response_time_ms')->nullable();
            
            $table->timestamps();
            
            // Index for querying attempts by payment
            $table->index(['payment_id', 'attempt_number']);
            $table->index(['gateway', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_attempts');
    }
};


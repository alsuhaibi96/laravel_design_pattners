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
        Schema::create('transaction_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            
            // Polymorphic relation to payment/refund
            $table->string('loggable_type');
            $table->unsignedBigInteger('loggable_id');
            
            // Event type
            $table->string('event'); // initiated, gateway_called, response_received, status_changed, webhook_received
            
            // Gateway info
            $table->string('gateway')->nullable();
            
            // Event data
            $table->json('data')->nullable();
            $table->json('metadata')->nullable();
            
            // Context
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            
            $table->timestamps();
            
            $table->index(['loggable_type', 'loggable_id']);
            $table->index(['event', 'created_at']);
            $table->index('gateway');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transaction_logs');
    }
};


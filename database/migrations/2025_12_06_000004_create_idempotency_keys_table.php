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
        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            
            // Store the result for replay
            $table->json('request_hash')->nullable(); // Hash of original request params
            $table->json('response')->nullable();
            $table->unsignedSmallInteger('status_code')->nullable();
            
            // Track state
            $table->boolean('is_processing')->default(false);
            $table->timestamp('locked_at')->nullable();
            
            // Expiration (typically 24h for payment idempotency)
            $table->timestamp('expires_at');
            
            $table->timestamps();
            
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};


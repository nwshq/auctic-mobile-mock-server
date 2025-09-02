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
        Schema::create('test_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('session_id')->unique();
            $table->string('scenario', 100);
            $table->json('metadata')->nullable();
            $table->json('state')->nullable();
            $table->integer('request_count')->default(0);
            $table->timestamp('expires_at');
            $table->timestamps();
            
            $table->index('session_id');
            $table->index('expires_at');
            $table->index('scenario');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('test_sessions');
    }
};

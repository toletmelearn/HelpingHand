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
        Schema::create('biometric_devices', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('device_type'); // ZKTeco, ESSL, Mantra, Generic
            $table->string('connection_type'); // TCP/IP, Serial, USB, REST
            $table->string('ip_address')->nullable();
            $table->integer('port')->nullable();
            $table->string('api_url')->nullable();
            $table->string('username')->nullable();
            $table->string('password')->nullable();
            $table->text('api_key')->nullable();
            $table->text('device_config')->nullable(); // JSON configuration
            $table->integer('sync_frequency')->default(60); // minutes
            $table->boolean('real_time_sync')->default(false);
            $table->boolean('is_active')->default(true);
            $table->string('status')->default('offline'); // online, offline, error
            $table->timestamp('last_sync_at')->nullable();
            $table->text('last_error')->nullable();
            $table->integer('retry_count')->default(0);
            $table->timestamp('next_sync_at')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index(['is_active', 'status']);
            $table->index('device_type');
            $table->index('next_sync_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('biometric_devices');
    }
};

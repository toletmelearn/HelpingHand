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
        Schema::create('biometric_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('biometric_device_id');
            $table->string('sync_type'); // scheduled, realtime, manual
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->string('status'); // success, failed, in_progress
            $table->integer('records_processed')->default(0);
            $table->integer('records_created')->default(0);
            $table->integer('records_updated')->default(0);
            $table->integer('duplicates_skipped')->default(0);
            $table->integer('errors_count')->default(0);
            $table->text('error_details')->nullable();
            $table->json('sync_metadata')->nullable();
            $table->text('log_message')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index(['biometric_device_id', 'started_at']);
            $table->index('status');
            $table->index('sync_type');
            
            // Foreign key
            $table->foreign('biometric_device_id')->references('id')->on('biometric_devices')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('biometric_sync_logs');
    }
};

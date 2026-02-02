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
        Schema::table('biometric_settings', function (Blueprint $table) {
            $table->boolean('enable_late_arrival_notifications')->default(true);
            $table->boolean('enable_early_departure_notifications')->default(true);
            $table->boolean('enable_half_day_notifications')->default(true);
            $table->boolean('enable_performance_notifications')->default(true);
            $table->json('notification_preferences')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('biometric_settings', function (Blueprint $table) {
            $table->dropColumn([
                'enable_late_arrival_notifications',
                'enable_early_departure_notifications', 
                'enable_half_day_notifications',
                'enable_performance_notifications',
                'notification_preferences'
            ]);
        });
    }
};
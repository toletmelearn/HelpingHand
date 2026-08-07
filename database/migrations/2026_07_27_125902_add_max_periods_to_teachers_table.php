<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teachers', function (Blueprint $table) {
            $table->unsignedTinyInteger('max_periods_per_day')->default(7);
            $table->unsignedTinyInteger('max_periods_per_week')->default(36);
        });
    }

    public function down(): void
    {
        Schema::table('teachers', function (Blueprint $table) {
            $table->dropColumn(['max_periods_per_day', 'max_periods_per_week']);
        });
    }
};

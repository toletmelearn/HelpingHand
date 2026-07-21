<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The bare `classes` table (migration 2026_01_31_072706) was created
     * with no model and no live controller reference -- its only reference
     * (AdminResultMonitorController::index) is itself unrouted/unreachable.
     * See A4 of the Academic module rebuild.
     */
    public function up(): void
    {
        Schema::dropIfExists('classes');
    }

    public function down(): void
    {
        Schema::create('classes', function (\Illuminate\Database\Schema\Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('class_order')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }
};

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
        // 1. Create guard_duty_assignments table
        Schema::create('guard_duty_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('gate_name'); // e.g. Gate 1, Gate 2, Main Gate
            $table->date('duty_date');
            $table->string('shift')->default('General'); // Morning, Evening, Night
            $table->foreignId('assigned_by')->constrained('users')->onDelete('cascade');
            $table->string('status')->default('active'); // active, completed
            $table->timestamps();
        });

        // 2. Add columns to gate_passes table
        Schema::table('gate_passes', function (Blueprint $table) {
            $table->string('exit_gate')->nullable()->after('status');
            $table->string('override_reason')->nullable()->after('exit_gate');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('guard_duty_assignments');

        Schema::table('gate_passes', function (Blueprint $table) {
            $table->dropColumn(['exit_gate', 'override_reason']);
        });
    }
};

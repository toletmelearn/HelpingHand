<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gate_entries', function (Blueprint $table) {
            $table->id();
            $table->string('visitor_name');
            $table->string('purpose');
            $table->timestamp('check_in');
            $table->timestamp('check_out')->nullable();
            $table->string('vehicle_no')->nullable();
            $table->foreignId('host_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gate_entries');
    }
};

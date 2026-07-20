<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('plate_no')->unique();
            $table->string('model');
            $table->integer('capacity');
            $table->string('status')->default('active'); // active, maintenance, inactive
            $table->timestamps();
        });

        Schema::create('routes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('start_point');
            $table->string('end_point');
            $table->decimal('monthly_fare', 10, 2);
            $table->timestamps();
        });

        Schema::create('drivers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('license_no')->unique();
            $table->string('phone_no');
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::create('route_stops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('route_id')->constrained('routes')->onDelete('cascade');
            $table->string('stop_name');
            $table->time('arrival_time')->nullable();
            $table->timestamps();
        });

        Schema::create('student_transport', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
            $table->foreignId('route_id')->constrained('routes')->onDelete('cascade');
            $table->foreignId('stop_id')->constrained('route_stops')->onDelete('cascade');
            $table->foreignId('vehicle_id')->constrained('vehicles')->onDelete('cascade');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_transport');
        Schema::dropIfExists('route_stops');
        Schema::dropIfExists('drivers');
        Schema::dropIfExists('routes');
        Schema::dropIfExists('vehicles');
    }
};

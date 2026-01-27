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
        Schema::create('teacher_biometric_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('teacher_id');
            $table->date('date');
            $table->time('first_in_time')->nullable();
            $table->time('last_out_time')->nullable();
            $table->json('multiple_punches')->nullable();
            $table->decimal('calculated_duration', 8, 2)->nullable();
            $table->string('arrival_status')->default('on_time'); // on_time, late
            $table->string('departure_status')->default('on_time'); // on_time, early_exit, half_day
            $table->integer('grace_minutes_used')->default(0);
            $table->integer('late_minutes')->default(0);
            $table->integer('early_departure_minutes')->default(0);
            $table->text('remarks')->nullable();
            $table->string('import_source')->nullable(); // manual, csv_upload, api
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index(['teacher_id', 'date']);
            $table->index('date');
            $table->index('arrival_status');
            $table->index('departure_status');
            
            // Foreign key constraint
            $table->foreign('teacher_id')->references('id')->on('teachers')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teacher_biometric_records');
    }
};

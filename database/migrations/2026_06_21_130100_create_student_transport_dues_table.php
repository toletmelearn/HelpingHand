<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('student_transport_dues')) {
            Schema::create('student_transport_dues', function (Blueprint $table) {
                $table->id();
                $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
                $table->foreignId('route_id')->constrained('routes')->onDelete('cascade');
                $table->foreignId('stop_id')->constrained('route_stops')->onDelete('cascade');
                $table->string('month');
                $table->string('academic_year');
                $table->decimal('amount', 10, 2)->default(0.00);
                $table->string('status')->default('unpaid'); // unpaid, paid
                $table->timestamps();

                $table->index(['student_id', 'month', 'academic_year'], 'student_transport_dues_index');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('student_transport_dues');
    }
};

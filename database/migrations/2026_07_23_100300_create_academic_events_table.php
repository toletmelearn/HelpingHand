<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('academic_session_id')->nullable();
            $table->string('title');
            $table->enum('type', ['holiday', 'exam', 'event', 'ptm', 'other'])->default('event');
            $table->date('start_date');
            $table->date('end_date');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('academic_session_id')->references('id')->on('academic_sessions')->onDelete('set null');
            $table->index(['academic_session_id', 'start_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_events');
    }
};

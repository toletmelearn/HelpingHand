<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Priority 1.3: no building concept existed anywhere -- a room was a
     * bare string on TimetableSlot/DatesheetEntry/ExamSeatingArrangement,
     * so nothing could tell that "Room 101" and "Lab-3A" are in different
     * physical buildings a teacher needs travel time to move between.
     * transfer_time_in_minutes is this building's own default requirement
     * for travelling to/from ANY other building; building_transfer_times
     * (next migration) can override that for a specific pair.
     */
    public function up(): void
    {
        Schema::create('buildings', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->unsignedInteger('transfer_time_in_minutes')->default(10);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('buildings');
    }
};

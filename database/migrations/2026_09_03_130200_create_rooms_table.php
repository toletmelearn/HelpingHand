<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Priority 1.3: registers which building a given room_number (the
     * free-text string already used by TimetableSlot/DatesheetEntry/
     * ExamSeatingArrangement.room_number -- there's no FK from those
     * tables, matched by string) physically belongs to. A room_number
     * with no row here is simply not building-mapped yet -- every check
     * against it treats that as "can't determine, don't block" rather
     * than a false positive, exactly like an unset room already does for
     * TimetableConflictResolver::roomOverlapConflicts().
     */
    public function up(): void
    {
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->string('room_number')->unique();
            $table->foreignId('building_id')->nullable()->constrained('buildings')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};

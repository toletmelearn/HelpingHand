<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * T4b item 1: one row per GenerateTimetableJob run. `academic_year`
     * (free-text) + `school_class_ids` (JSON) together record the scope a
     * generation targeted -- the same scope GenerateTimetableJob uses to
     * delete previous drafts before writing new ones ("drafts for a
     * session/class replace previous drafts only"), and the same scope
     * PUBLISH/DISCARD operate on. `report` stores GeneratorService's full
     * placements/unplaced/stats array as-generated, so the UI can show the
     * unplaced-lesson sentences without recomputing anything.
     */
    public function up(): void
    {
        Schema::create('timetable_generations', function (Blueprint $table) {
            $table->id();
            $table->string('academic_year')->nullable();
            $table->json('school_class_ids');
            $table->string('status')->default('queued'); // queued -> running -> completed | failed
            $table->unsignedInteger('placed_count')->default(0);
            $table->unsignedInteger('unplaced_count')->default(0);
            $table->json('report')->nullable();
            $table->text('error')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timetable_generations');
    }
};

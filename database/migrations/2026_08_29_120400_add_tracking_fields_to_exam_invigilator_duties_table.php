<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds audit/tracking fields to exam_invigilator_duties: who made the
     * assignment, when, and free-text notes (e.g. a conflict the assigner
     * chose to override). Purely additive/nullable -- kept separate from
     * the table-recreate migration above so the "fix fresh installs" fix
     * and this feature addition can be reasoned about independently.
     * ExamArrangementController::saveInvigilators() is updated in the same
     * change to populate assigned_by/assigned_at going forward; existing
     * rows simply have them NULL.
     */
    public function up(): void
    {
        Schema::table('exam_invigilator_duties', function (Blueprint $table) {
            if (!Schema::hasColumn('exam_invigilator_duties', 'assigned_by')) {
                $table->string('assigned_by')->nullable()->after('role');
            }
            if (!Schema::hasColumn('exam_invigilator_duties', 'assigned_at')) {
                $table->timestamp('assigned_at')->nullable()->after('assigned_by');
            }
            if (!Schema::hasColumn('exam_invigilator_duties', 'notes')) {
                $table->text('notes')->nullable()->after('assigned_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('exam_invigilator_duties', function (Blueprint $table) {
            $table->dropColumn(['assigned_by', 'assigned_at', 'notes']);
        });
    }
};

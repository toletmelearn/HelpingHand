<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * T4a: the solver's hard constraints require "combined-group lessons
     * place simultaneously for all member classes" -- but T2b's
     * combined_class_groups deliberately has no periods_per_week or
     * teacher_id (the teacher is chosen per-placement in the manual
     * storeCombined() flow, and the group itself is just a standing
     * "these classes take this subject together" fact). The solver needs
     * a standing weekly requirement to build lesson units from, the same
     * way it reads periods_per_week off teacher_class_subject_assignments
     * for ordinary lessons.
     *
     * Both columns are nullable and purely additive: a combined group
     * with these left unset is simply not picked up by the generator
     * (still fully usable for T2b's manual placement flow, unaffected).
     */
    public function up(): void
    {
        Schema::table('combined_class_groups', function (Blueprint $table) {
            $table->foreignId('teacher_id')->nullable()->after('subject_id')->constrained('teachers')->nullOnDelete();
            $table->unsignedTinyInteger('periods_per_week')->nullable()->after('teacher_id');
        });
    }

    public function down(): void
    {
        Schema::table('combined_class_groups', function (Blueprint $table) {
            $table->dropConstrainedForeignId('teacher_id');
            $table->dropColumn('periods_per_week');
        });
    }
};

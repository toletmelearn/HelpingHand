<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * GeneratorService needs academic_session_id (a real FK) to scope
     * combined_class_groups, separate from the free-text academic_year
     * everything else in this module uses -- added as its own small
     * migration rather than folded into the table-create above, since
     * this table was already committed once this session.
     */
    public function up(): void
    {
        Schema::table('timetable_generations', function (Blueprint $table) {
            $table->foreignId('academic_session_id')->nullable()->after('academic_year')->constrained('academic_sessions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('timetable_generations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('academic_session_id');
        });
    }
};

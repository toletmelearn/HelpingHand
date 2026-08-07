<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * T4a: the plan's soft-preference scoring calls for "prefer morning
     * for flagged subjects" and "avoid last period for flagged subjects"
     * -- no existing column expresses that flag (subjects.subject_type
     * has exactly one real value, 'scholastic', so it can't distinguish
     * anything). Adding a single boolean that governs both preferences
     * together, since the plan describes them as applying to the same
     * "flagged" set rather than two independent flags.
     */
    public function up(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->boolean('prefer_morning')->default(false)->after('subject_type');
        });
    }

    public function down(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->dropColumn('prefer_morning');
        });
    }
};

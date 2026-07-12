<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Student::schoolClass() picks class_id vs school_class_id per-row
     * depending on which is null -- correct under lazy loading, but
     * Eloquent eager loading (Student::with('schoolClass'), used on every
     * admin student list) resolves the foreign key column once for the
     * whole batch, so any row where only one of the two columns was set
     * silently shows no class at all. A static::saving() hook now keeps
     * both columns in sync going forward; this backfills rows written
     * before that hook existed (bulk-imported students in particular).
     */
    public function up(): void
    {
        if (!Schema::hasTable('students')) {
            return;
        }

        // Unlike school_class_id, class_id has no foreign key constraint --
        // some legacy/loosely-normalized rows carry a class_id that isn't a
        // real school_classes row. Only copy it across where it actually
        // resolves, so this backfill can't itself fail on a bad legacy value.
        $validClassIds = Schema::hasTable('school_classes')
            ? DB::table('school_classes')->pluck('id')
            : collect();

        DB::table('students')
            ->whereNotNull('class_id')
            ->whereNull('school_class_id')
            ->whereIn('class_id', $validClassIds)
            ->update(['school_class_id' => DB::raw('class_id')]);

        DB::table('students')
            ->whereNotNull('school_class_id')
            ->whereNull('class_id')
            ->update(['class_id' => DB::raw('school_class_id')]);
    }

    public function down(): void
    {
        // Not reversible -- this only fills in previously-null values,
        // it never overwrites data that was already there.
    }
};

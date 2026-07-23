<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Corrects 2026_07_23_100000_merge_class_management_into_school_classes,
     * which soft-deleted school_classes 22 ("Class 11") and 23 ("Class 12")
     * as "confirmed dead" duplicates of the stream-specific rows (14-19).
     *
     * That premise was backwards: live data shows 22/23 hold 98 currently
     * enrolled students (65 + 33) with real stream-differentiated sections
     * (e.g. "Science (PCB)", "Science (PCM)", "Commerce", "Humanities"),
     * while school_classes 14-19 have zero students and zero section
     * references. Soft-deleting 22/23 silently broke $student->schoolClass
     * for those 98 students (admit cards, exam seating, UDISE export, fee
     * displays all fell back to the stale, unsynced `class` string).
     */
    private const WRONGLY_ORPHANED_IDS = [22, 23];

    public function up(): void
    {
        DB::table('school_classes')
            ->whereIn('id', self::WRONGLY_ORPHANED_IDS)
            ->whereNotNull('deleted_at')
            ->update(['deleted_at' => null]);
    }

    public function down(): void
    {
        DB::table('school_classes')
            ->whereIn('id', self::WRONGLY_ORPHANED_IDS)
            ->whereNull('deleted_at')
            ->update(['deleted_at' => now()]);
    }
};

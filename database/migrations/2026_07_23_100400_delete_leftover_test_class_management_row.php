<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * class_management id 20 ("TestClass") is the one row the A2 merge
     * migration (2026_07_23_100000) deliberately excluded from the
     * class_management -> school_classes mapping. Phase A closure
     * (2026-07-21) re-investigated it and found:
     *   - zero students reference it via class_id, school_class_id, or the
     *     class string ('TestClass'), including after making
     *     school_class_id authoritative;
     *   - no school_classes row exists under the same name to link it to;
     *   - no legacy_class_map entry exists for it;
     *   - its only reference anywhere is one class_teacher pivot row
     *     (teacher_id=136, "Test T", email testt@example.com, no user_id,
     *     no other associations anywhere in the database) -- itself
     *     unambiguous test data.
     * Decision: DELETE (not migrate -- there is nothing real to preserve).
     * The class_teacher pivot row cascade-deletes automatically
     * (class_teacher.class_id has ON DELETE CASCADE to class_management).
     */
    public function up(): void
    {
        DB::table('class_management')->where('id', 20)->where('name', 'TestClass')->delete();
    }

    public function down(): void
    {
        DB::table('class_management')->insertOrIgnore([
            'id' => 20,
            'name' => 'TestClass',
            'order' => 0,
            'section' => null,
            'stream' => null,
            'capacity' => 40,
            'description' => null,
            'is_active' => 1,
            'created_at' => '2026-07-17 08:17:07',
            'updated_at' => '2026-07-17 08:17:07',
        ]);
    }
};

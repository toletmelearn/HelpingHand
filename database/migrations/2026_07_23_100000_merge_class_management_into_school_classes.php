<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Explicit, reviewed class_management_id => school_classes_id pairing
     * from the A2 reconciliation report (name+stream matched, descriptions
     * confirmed identical). class_management id 20 ("TestClass") is
     * deliberately excluded -- leftover test data (no description,
     * order=0, the only row ever assigned via the class_teacher pivot).
     */
    private const MATCHES = [
        1 => 1, 2 => 2, 3 => 3, 4 => 4, 5 => 5, 6 => 6, 7 => 7, 8 => 8,
        9 => 9, 10 => 10, 11 => 11, 12 => 12, 13 => 13,
        14 => 14, 15 => 15, 16 => 16, 17 => 17, 18 => 18, 19 => 19,
    ];

    /**
     * school_classes rows with no class_management counterpart -- bare
     * duplicates of the stream-specific Class 11/12 rows, confirmed dead
     * in the A2 reconciliation report.
     */
    private const ORPHAN_SCHOOL_CLASS_IDS = [22, 23];

    public function up(): void
    {
        if (!Schema::hasColumn('school_classes', 'capacity')) {
            Schema::table('school_classes', function (Blueprint $table) {
                $table->unsignedInteger('capacity')->nullable()->after('description');
            });
        }

        if (!Schema::hasColumn('school_classes', 'stream')) {
            Schema::table('school_classes', function (Blueprint $table) {
                $table->string('stream')->nullable()->after('capacity');
            });
        }

        if (!Schema::hasTable('legacy_class_map')) {
            Schema::create('legacy_class_map', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('class_management_id');
                $table->unsignedBigInteger('school_class_id');
                $table->timestamps();

                $table->unique('class_management_id');
                $table->foreign('class_management_id')->references('id')->on('class_management')->onDelete('cascade');
                $table->foreign('school_class_id')->references('id')->on('school_classes')->onDelete('cascade');
            });
        }

        foreach (self::MATCHES as $classManagementId => $schoolClassId) {
            $legacy = DB::table('class_management')->find($classManagementId);
            $target = DB::table('school_classes')->find($schoolClassId);

            if (!$legacy || !$target) {
                continue;
            }

            DB::table('school_classes')->where('id', $schoolClassId)->update([
                'capacity' => $legacy->capacity,
                'stream' => $legacy->stream !== '' && $legacy->stream !== null ? $legacy->stream : null,
            ]);

            DB::table('legacy_class_map')->updateOrInsert(
                ['class_management_id' => $classManagementId],
                ['school_class_id' => $schoolClassId, 'updated_at' => now(), 'created_at' => now()]
            );
        }

        $orphans = DB::table('school_classes')
            ->whereIn('id', self::ORPHAN_SCHOOL_CLASS_IDS)
            ->whereNull('deleted_at')
            ->pluck('id');

        if ($orphans->isNotEmpty()) {
            DB::table('school_classes')->whereIn('id', $orphans)->update(['deleted_at' => now()]);
        }
    }

    public function down(): void
    {
        DB::table('school_classes')
            ->whereIn('id', self::ORPHAN_SCHOOL_CLASS_IDS)
            ->update(['deleted_at' => null]);

        Schema::dropIfExists('legacy_class_map');

        if (Schema::hasColumn('school_classes', 'stream')) {
            Schema::table('school_classes', function (Blueprint $table) {
                $table->dropColumn('stream');
            });
        }

        if (Schema::hasColumn('school_classes', 'capacity')) {
            Schema::table('school_classes', function (Blueprint $table) {
                $table->dropColumn('capacity');
            });
        }
    }
};

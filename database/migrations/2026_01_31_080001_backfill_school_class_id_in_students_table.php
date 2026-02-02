<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Get all distinct class names from students
        $studentClasses = DB::table('students')
            ->select('class')
            ->whereNotNull('class')
            ->distinct()
            ->pluck('class');

        echo "Backfilling school_class_id for " . $studentClasses->count() . " classes...\n";

        foreach ($studentClasses as $className) {
            // Find the corresponding school class
            $schoolClass = DB::table('school_classes')
                ->where('name', $className)
                ->first();
            
            if ($schoolClass) {
                // Update students with this class name to use school_class_id
                $updated = DB::table('students')
                    ->where('class', $className)
                    ->update(['school_class_id' => $schoolClass->id]);
                
                echo "  - {$className}: {$updated} students updated\n";
            } else {
                echo "  - {$className}: No matching school class found\n";
            }
        }

        // Verify results
        $totalStudents = DB::table('students')->count();
        $studentsWithSchoolClassId = DB::table('students')->whereNotNull('school_class_id')->count();
        
        echo "\n=== BACKFILL RESULTS ===\n";
        echo "Total students: {$totalStudents}\n";
        echo "Students with school_class_id: {$studentsWithSchoolClassId}\n";
        echo "Completion: " . round(($studentsWithSchoolClassId / $totalStudents) * 100, 2) . "%\n";
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reset school_class_id to null
        DB::table('students')->update(['school_class_id' => null]);
        echo "school_class_id reset to null for all students\n";
    }
};
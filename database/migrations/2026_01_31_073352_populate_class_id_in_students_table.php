<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
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

        foreach ($studentClasses as $className) {
            // Find or create the class
            $schoolClass = \App\Models\SchoolClass::where('name', $className)->first();
            
            if ($schoolClass) {
                // Update students with this class name to use the class_id
                DB::table('students')
                    ->where('class', $className)
                    ->update(['class_id' => $schoolClass->id]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reset class_id to null
        DB::table('students')->update(['class_id' => null]);
    }
};

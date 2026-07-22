<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Standardizes on the "aadhaar_number" spelling (matching
     * admission_enquiries and families) for the students table. teachers
     * and guardians keep their own separate "aadhar_number" columns --
     * out of scope for this rename.
     *
     * Already nullable as of 2026_07_11_000000_make_students_aadhar_number_nullable
     * (a large share of existing records have no Aadhaar on file yet) --
     * this migration only renames, it does not change nullability.
     */
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->renameColumn('aadhar_number', 'aadhaar_number');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->renameColumn('aadhaar_number', 'aadhar_number');
        });
    }
};

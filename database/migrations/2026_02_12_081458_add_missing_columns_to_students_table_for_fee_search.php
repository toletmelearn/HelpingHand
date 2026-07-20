<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            // Add admission_no column if it doesn't exist
            if (!Schema::hasColumn('students', 'admission_no')) {
                $table->string('admission_no')->nullable()->after('aadhar_number');
            }
            
            // Add mobile column if it doesn't exist
            if (!Schema::hasColumn('students', 'mobile')) {
                $table->string('mobile')->nullable()->after('phone');
            }
            
            // Add guardian_name column if it doesn't exist
            if (!Schema::hasColumn('students', 'guardian_name')) {
                $table->string('guardian_name')->nullable()->after('mother_name');
            }
            
            // Add indexes for better search performance
            if (!Schema::hasIndex('students', 'students_admission_no_index')) {
                $table->index('admission_no', 'students_admission_no_index');
            }
            
            if (!Schema::hasIndex('students', 'students_mobile_index')) {
                $table->index('mobile', 'students_mobile_index');
            }
            
            if (!Schema::hasIndex('students', 'students_class_id_index')) {
                $table->index('class_id', 'students_class_id_index');
            }
            
            if (!Schema::hasIndex('students', 'students_name_index')) {
                $table->index('name', 'students_name_index');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            // Drop indexes if they exist
            if (Schema::hasIndex('students', 'students_admission_no_index')) {
                $table->dropIndex('students_admission_no_index');
            }
            
            if (Schema::hasIndex('students', 'students_mobile_index')) {
                $table->dropIndex('students_mobile_index');
            }
            
            if (Schema::hasIndex('students', 'students_class_id_index')) {
                $table->dropIndex('students_class_id_index');
            }
            
            if (Schema::hasIndex('students', 'students_name_index')) {
                $table->dropIndex('students_name_index');
            }
            
            // Drop columns if they exist
            if (Schema::hasColumn('students', 'admission_no')) {
                $table->dropColumn('admission_no');
            }
            
            if (Schema::hasColumn('students', 'mobile')) {
                $table->dropColumn('mobile');
            }
            
            if (Schema::hasColumn('students', 'guardian_name')) {
                $table->dropColumn('guardian_name');
            }
        });
    }
};

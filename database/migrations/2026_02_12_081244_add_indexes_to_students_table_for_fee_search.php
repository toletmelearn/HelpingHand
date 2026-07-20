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
            echo "Inside closure, hasColumn(admission_no): ";
            var_dump(Schema::hasColumn('students', 'admission_no'));
            // Add indexes for the commonly searched fields
            if (Schema::hasColumn('students', 'admission_no') && !Schema::hasIndex('students', 'students_admission_no_index')) {
                $table->index('admission_no', 'students_admission_no_index');
            }
            
            if (Schema::hasColumn('students', 'mobile') && !Schema::hasIndex('students', 'students_mobile_index')) {
                $table->index('mobile', 'students_mobile_index');
            }
            
            if (Schema::hasColumn('students', 'class_id') && !Schema::hasIndex('students', 'students_class_id_index')) {
                $table->index('class_id', 'students_class_id_index');
            }
            
            if (Schema::hasColumn('students', 'name') && !Schema::hasIndex('students', 'students_name_index')) {
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
            // Remove the indexes added
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
        });
    }
};

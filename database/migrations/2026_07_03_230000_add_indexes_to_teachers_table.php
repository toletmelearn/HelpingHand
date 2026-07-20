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
        Schema::table('teachers', function (Blueprint $table) {
            if (Schema::hasColumn('teachers', 'employee_id') && !Schema::hasIndex('teachers', 'teachers_employee_id_index')) {
                $table->index('employee_id', 'teachers_employee_id_index');
            }
            if (Schema::hasColumn('teachers', 'status') && !Schema::hasIndex('teachers', 'teachers_status_index')) {
                $table->index('status', 'teachers_status_index');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teachers', function (Blueprint $table) {
            if (Schema::hasIndex('teachers', 'teachers_employee_id_index')) {
                $table->dropIndex('teachers_employee_id_index');
            }
            if (Schema::hasIndex('teachers', 'teachers_status_index')) {
                $table->dropIndex('teachers_status_index');
            }
        });
    }
};

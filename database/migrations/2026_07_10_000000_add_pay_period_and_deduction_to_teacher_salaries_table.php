<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teacher_salaries', function (Blueprint $table) {
            if (!Schema::hasColumn('teacher_salaries', 'pay_month')) {
                $table->unsignedTinyInteger('pay_month')->nullable()->after('teacher_id');
            }
            if (!Schema::hasColumn('teacher_salaries', 'pay_year')) {
                $table->unsignedSmallInteger('pay_year')->nullable()->after('pay_month');
            }
            if (!Schema::hasColumn('teacher_salaries', 'attendance_deduction_days')) {
                $table->decimal('attendance_deduction_days', 5, 2)->nullable()->after('other_deductions');
            }
            if (!Schema::hasColumn('teacher_salaries', 'attendance_deduction_amount')) {
                $table->decimal('attendance_deduction_amount', 10, 2)->nullable()->after('attendance_deduction_days');
            }
        });
    }

    public function down(): void
    {
        Schema::table('teacher_salaries', function (Blueprint $table) {
            $table->dropColumn(['pay_month', 'pay_year', 'attendance_deduction_days', 'attendance_deduction_amount']);
        });
    }
};

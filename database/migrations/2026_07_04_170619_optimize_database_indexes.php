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
            $table->index(['parent_id', 'school_class_id', 'section_id'], 'idx_students_parent_school_class_sec');
            $table->index(['parent_id', 'class_id', 'section_id'], 'idx_students_parent_class_sec');
        });

        Schema::table('student_fee_ledgers', function (Blueprint $table) {
            $table->index(['student_id', 'academic_year', 'unpaid_amount'], 'idx_fee_ledgers_stud_yr_unpaid');
        });

        Schema::table('results', function (Blueprint $table) {
            $table->index(['student_id', 'exam_id', 'subject_id', 'academic_year'], 'idx_results_stud_ex_sub_yr');
        });

        Schema::table('attendances', function (Blueprint $table) {
            $table->index(['student_id', 'date', 'teacher_id', 'period'], 'idx_attendances_stud_dt_teach_per');
        });

        Schema::table('fee_collections', function (Blueprint $table) {
            $table->index(['student_id', 'receipt_no', 'payment_date'], 'idx_fee_coll_stud_receipt_dt');
        });

        Schema::table('book_issues', function (Blueprint $table) {
            $table->index(['student_id', 'book_id', 'return_date'], 'idx_book_issues_stud_bk_ret_dt');
        });

        Schema::table('student_hostel_allocations', function (Blueprint $table) {
            $table->index(['student_id', 'status'], 'idx_hostel_alloc_stud_stat');
            $table->index(['room_id', 'status'], 'idx_hostel_alloc_rm_stat');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropIndex('idx_students_parent_school_class_sec');
            $table->dropIndex('idx_students_parent_class_sec');
        });

        Schema::table('student_fee_ledgers', function (Blueprint $table) {
            $table->dropIndex('idx_fee_ledgers_stud_yr_unpaid');
        });

        Schema::table('results', function (Blueprint $table) {
            $table->dropIndex('idx_results_stud_ex_sub_yr');
        });

        Schema::table('attendances', function (Blueprint $table) {
            $table->dropIndex('idx_attendances_stud_dt_teach_per');
        });

        Schema::table('fee_collections', function (Blueprint $table) {
            $table->dropIndex('idx_fee_coll_stud_receipt_dt');
        });

        Schema::table('book_issues', function (Blueprint $table) {
            $table->dropIndex('idx_book_issues_stud_bk_ret_dt');
        });

        Schema::table('student_hostel_allocations', function (Blueprint $table) {
            $table->dropIndex('idx_hostel_alloc_stud_stat');
            $table->dropIndex('idx_hostel_alloc_rm_stat');
        });
    }
};

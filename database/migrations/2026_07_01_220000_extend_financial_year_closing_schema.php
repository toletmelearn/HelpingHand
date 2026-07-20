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
        if (Schema::hasTable('student_fee_ledgers')) {
            Schema::table('student_fee_ledgers', function (Blueprint $table) {
                if (!Schema::hasColumn('student_fee_ledgers', 'is_archived')) {
                    $table->boolean('is_archived')->default(false)->after('unpaid_amount');
                }
            });
        }

        if (Schema::hasTable('financial_year_closings')) {
            Schema::table('financial_year_closings', function (Blueprint $table) {
                if (!Schema::hasColumn('financial_year_closings', 'total_scholarship_carried')) {
                    $table->decimal('total_scholarship_carried', 12, 2)->default(0.00)->after('total_advance_carried');
                }
                if (!Schema::hasColumn('financial_year_closings', 'total_refund_carried')) {
                    $table->decimal('total_refund_carried', 12, 2)->default(0.00)->after('total_scholarship_carried');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('student_fee_ledgers')) {
            Schema::table('student_fee_ledgers', function (Blueprint $table) {
                if (Schema::hasColumn('student_fee_ledgers', 'is_archived')) {
                    $table->dropColumn('is_archived');
                }
            });
        }

        if (Schema::hasTable('financial_year_closings')) {
            Schema::table('financial_year_closings', function (Blueprint $table) {
                if (Schema::hasColumn('financial_year_closings', 'total_scholarship_carried')) {
                    $table->dropColumn('total_scholarship_carried');
                }
                if (Schema::hasColumn('financial_year_closings', 'total_refund_carried')) {
                    $table->dropColumn('total_refund_carried');
                }
            });
        }
    }
};

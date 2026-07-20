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
        Schema::table('financial_year_closings', function (Blueprint $table) {
            if (!Schema::hasColumn('financial_year_closings', 'status')) {
                $table->string('status')->default('pending');
            }
            if (!Schema::hasColumn('financial_year_closings', 'last_processed_student_id')) {
                $table->unsignedBigInteger('last_processed_student_id')->nullable();
            }
            if (!Schema::hasColumn('financial_year_closings', 'total_students_processed')) {
                $table->integer('total_students_processed')->default(0);
            }
            if (!Schema::hasColumn('financial_year_closings', 'processed_batch')) {
                $table->integer('processed_batch')->default(0);
            }
            if (!Schema::hasColumn('financial_year_closings', 'total_batches')) {
                $table->integer('total_batches')->default(0);
            }
            if (!Schema::hasColumn('financial_year_closings', 'progress_percent')) {
                $table->decimal('progress_percent', 5, 2)->default(0.00);
            }
            if (!Schema::hasColumn('financial_year_closings', 'started_at')) {
                $table->timestamp('started_at')->nullable();
            }
            if (!Schema::hasColumn('financial_year_closings', 'completed_at')) {
                $table->timestamp('completed_at')->nullable();
            }
            if (!Schema::hasColumn('financial_year_closings', 'closed_by')) {
                $table->foreignId('closed_by')->nullable()->constrained('users');
            }
            if (!Schema::hasColumn('financial_year_closings', 'checksum')) {
                $table->string('checksum')->nullable();
            }
            if (!Schema::hasColumn('financial_year_closings', 'closing_version')) {
                $table->integer('closing_version')->default(1);
            }
            if (!Schema::hasColumn('financial_year_closings', 'error_message')) {
                $table->text('error_message')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('financial_year_closings', function (Blueprint $table) {
            $table->dropForeign(['closed_by']);
            $table->dropColumn([
                'status',
                'last_processed_student_id',
                'total_students_processed',
                'processed_batch',
                'total_batches',
                'progress_percent',
                'started_at',
                'completed_at',
                'closed_by',
                'checksum',
                'closing_version',
                'error_message'
            ]);
        });
    }
};

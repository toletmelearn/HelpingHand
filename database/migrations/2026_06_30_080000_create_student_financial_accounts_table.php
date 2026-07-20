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
        Schema::create('student_financial_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->unique()->constrained('students')->onDelete('cascade');
            $table->string('account_no')->unique();
            $table->enum('status', ['active', 'locked', 'closed'])->default('active');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ip_address')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // Retrofit existing students (including soft-deleted ones)
        try {
            $students = DB::table('students')->select('id')->get();
            foreach ($students as $student) {
                DB::table('student_financial_accounts')->insertOrIgnore([
                    'student_id' => $student->id,
                    'account_no' => 'FIN-' . str_pad($student->id, 6, '0', STR_PAD_LEFT),
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }
        } catch (\Exception $e) {
            // Ignore if student table does not exist in testing
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_financial_accounts');
    }
};

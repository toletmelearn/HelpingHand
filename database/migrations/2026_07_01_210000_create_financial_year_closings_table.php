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
        if (!Schema::hasTable('financial_year_closings')) {
            Schema::create('financial_year_closings', function (Blueprint $table) {
                $table->id();
                $table->string('from_session_code');
                $table->string('to_session_code');
                $table->string('status')->default('staged'); // staged, confirmed
                $table->integer('total_students_processed')->default(0);
                $table->decimal('total_balance_carried', 12, 2)->default(0.00);
                $table->decimal('total_advance_carried', 12, 2)->default(0.00);
                $table->foreignId('processed_by')->constrained('users')->cascadeOnDelete();
                $table->timestamp('confirmed_at')->nullable();
                $table->text('remarks')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('financial_year_closings');
    }
};

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
        if (!Schema::hasTable('transport_adjustments')) {
            Schema::create('transport_adjustments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('student_id');
                $table->unsignedBigInteger('transport_fee_id');
                $table->decimal('amount', 10, 2);
                $table->string('remarks')->nullable();
                $table->unsignedBigInteger('created_by');
                $table->timestamps();

                $table->foreign('student_id')->references('id')->on('students')->onDelete('cascade');
                $table->foreign('transport_fee_id')->references('id')->on('transport_fees')->onDelete('cascade');
                $table->foreign('created_by')->references('id')->on('users');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transport_adjustments');
    }
};

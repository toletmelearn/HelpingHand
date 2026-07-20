<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cashier_closings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cashier_id');
            $table->date('closing_date');
            $table->decimal('opening_balance', 10, 2)->default(0.00);
            
            // Expected balances (from system collections)
            $table->decimal('expected_cash', 10, 2)->default(0.00);
            $table->decimal('expected_upi', 10, 2)->default(0.00);
            $table->decimal('expected_bank', 10, 2)->default(0.00);
            $table->decimal('expected_cheque', 10, 2)->default(0.00);
            $table->decimal('expected_online', 10, 2)->default(0.00);
            
            // Actual counted balances (entered by cashier)
            $table->decimal('actual_cash', 10, 2)->default(0.00);
            $table->decimal('actual_upi', 10, 2)->default(0.00);
            $table->decimal('actual_bank', 10, 2)->default(0.00);
            $table->decimal('actual_cheque', 10, 2)->default(0.00);
            $table->decimal('actual_online', 10, 2)->default(0.00);
            
            $table->text('discrepancy_reason')->nullable();
            $table->string('status')->default('submitted'); // submitted, verified
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->foreign('cashier_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cashier_closings');
    }
};

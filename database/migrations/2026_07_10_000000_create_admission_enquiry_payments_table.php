<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admission_enquiry_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admission_enquiry_id')->constrained('admission_enquiries')->onDelete('cascade');
            $table->foreignId('fee_type_id')->nullable()->constrained('fee_types')->onDelete('set null');
            $table->decimal('amount', 10, 2);
            $table->string('payment_mode'); // cash, cheque, upi, bank_transfer
            $table->string('receipt_no')->unique();
            $table->date('paid_at');
            $table->foreignId('collected_by')->nullable()->constrained('users')->onDelete('set null');
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admission_enquiry_payments');
    }
};

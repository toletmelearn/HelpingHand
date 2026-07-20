<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations for professional fee management system.
     */
    public function up(): void
    {
        // 1. Create fee heads table (individual fee components)
        Schema::create('fee_heads', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g., 'Tuition Fee', 'Transport Fee'
            $table->string('code')->unique(); // e.g., 'TF', 'TRF'
            $table->text('description')->nullable();
            $table->enum('type', ['fixed', 'variable'])->default('fixed');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index('is_active');
        });

        // 2. Create enhanced fee structure table with proper relationships
        Schema::create('fee_structure_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fee_structure_id')->constrained('fee_structures')->onDelete('cascade');
            $table->foreignId('fee_head_id')->constrained('fee_heads')->onDelete('cascade');
            $table->decimal('amount', 10, 2);
            $table->enum('frequency', ['monthly', 'quarterly', 'yearly', 'one-time'])->default('monthly');
            $table->json('applicable_months')->nullable(); // ['april', 'may', 'june'...]
            $table->boolean('is_late_fee_applicable')->default(false);
            $table->decimal('late_fee_amount', 10, 2)->nullable();
            $table->integer('late_fee_days_after')->nullable(); // days after due date
            $table->boolean('is_mandatory')->default(true);
            $table->timestamps();
            
            $table->unique(['fee_structure_id', 'fee_head_id'], 'fee_structure_details_unique');
            $table->index(['fee_structure_id', 'frequency']);
        });

        // 3. Create student fee assignments table
        Schema::create('student_fee_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->onDelete('cascade');
            $table->foreignId('fee_structure_id')->constrained('fee_structures')->onDelete('cascade');
            $table->string('academic_year');
            $table->date('assigned_date');
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->unique(['student_id', 'fee_structure_id', 'academic_year'], 'student_fee_assignments_unique');
            $table->index(['student_id', 'is_active']);
            $table->index('academic_year');
        });

        // 4. Create fee collections table
        Schema::create('fee_collections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->onDelete('cascade');
            $table->foreignId('fee_structure_detail_id')->constrained('fee_structure_details')->onDelete('cascade');
            $table->string('academic_year');
            $table->string('month')->nullable(); // e.g., 'april', 'may'
            $table->decimal('amount', 10, 2);
            $table->decimal('paid_amount', 10, 2)->default(0);
            $table->decimal('due_amount', 10, 2);
            $table->decimal('late_fee', 10, 2)->default(0);
            $table->decimal('discount', 10, 2)->default(0);
            $table->decimal('fine', 10, 2)->default(0);
            $table->enum('status', ['pending', 'partial', 'paid', 'overdue', 'cancelled'])->default('pending');
            $table->date('due_date')->nullable();
            $table->date('payment_date')->nullable();
            $table->enum('payment_mode', ['cash', 'online', 'upi', 'cheque', 'bank_transfer'])->nullable();
            $table->string('transaction_id')->nullable();
            $table->string('cheque_number')->nullable();
            $table->foreignId('collected_by')->nullable()->constrained('users')->onDelete('set null');
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->index(['student_id', 'academic_year']);
            $table->index(['student_id', 'month']);
            $table->index('status');
            $table->index('payment_date');
        });

        // 5. Create fee receipts table
        Schema::create('fee_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_number')->unique();
            $table->foreignId('fee_collection_id')->constrained('fee_collections')->onDelete('cascade');
            $table->foreignId('student_id')->constrained()->onDelete('cascade');
            $table->decimal('total_amount', 10, 2);
            $table->decimal('paid_amount', 10, 2);
            $table->decimal('balance_amount', 10, 2);
            $table->json('fee_details'); // Store detailed breakdown
            $table->foreignId('generated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('generated_at')->useCurrent();
            $table->boolean('is_printed')->default(false);
            $table->timestamps();
            
            $table->index('receipt_number');
            $table->index(['student_id', 'generated_at']);
        });

        // 6. Create discounts table
        Schema::create('fee_discounts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('type', ['percentage', 'fixed'])->default('percentage');
            $table->decimal('value', 5, 2); // percentage or fixed amount
            $table->boolean('is_active')->default(true);
            $table->json('applicable_classes')->nullable(); // ['class_1', 'class_2'] or null for all
            $table->json('applicable_fee_heads')->nullable(); // specific fee heads or null for all
            $table->date('valid_from');
            $table->date('valid_until')->nullable();
            $table->timestamps();
        });

        // 7. Create student fee discounts table
        Schema::create('student_fee_discounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->onDelete('cascade');
            $table->foreignId('fee_discount_id')->constrained('fee_discounts')->onDelete('cascade');
            $table->foreignId('applied_by')->nullable()->constrained('users')->onDelete('set null');
            $table->date('applied_date');
            $table->date('valid_until')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->unique(['student_id', 'fee_discount_id'], 'student_fee_discounts_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_fee_discounts');
        Schema::dropIfExists('fee_discounts');
        Schema::dropIfExists('fee_receipts');
        Schema::dropIfExists('fee_collections');
        Schema::dropIfExists('student_fee_assignments');
        Schema::dropIfExists('fee_structure_details');
        Schema::dropIfExists('fee_heads');
    }
};
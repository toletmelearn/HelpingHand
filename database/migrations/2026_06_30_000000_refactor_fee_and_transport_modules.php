<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Refactor/create transport_fees table
        if (Schema::hasTable('student_transport_dues')) {
            Schema::rename('student_transport_dues', 'transport_fees');
        }

        if (!Schema::hasTable('transport_fees')) {
            Schema::create('transport_fees', function (Blueprint $table) {
                $table->id();
                $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
                $table->foreignId('route_id')->nullable()->constrained('routes')->onDelete('set null');
                $table->foreignId('stop_id')->nullable()->constrained('route_stops')->onDelete('set null');
                $table->string('month');
                $table->string('academic_year');
                $table->decimal('amount', 10, 2)->default(0.00);
                $table->string('status')->default('unpaid'); // unpaid, paid
                $table->timestamps();
            });
        }

        Schema::table('transport_fees', function (Blueprint $table) {
            if (!Schema::hasColumn('transport_fees', 'vehicle_id')) {
                $table->foreignId('vehicle_id')->nullable()->after('stop_id')->constrained('vehicles')->onDelete('set null');
            }
            if (!Schema::hasColumn('transport_fees', 'payment_date')) {
                $table->date('payment_date')->nullable()->after('status');
            }
            if (!Schema::hasColumn('transport_fees', 'payment_mode')) {
                $table->string('payment_mode')->nullable()->after('payment_date');
            }
            if (!Schema::hasColumn('transport_fees', 'transaction_id')) {
                $table->string('transaction_id')->nullable()->after('payment_mode');
            }
            if (!Schema::hasColumn('transport_fees', 'receipt_no')) {
                $table->string('receipt_no')->nullable()->after('transaction_id');
            }
            if (!Schema::hasColumn('transport_fees', 'collected_by')) {
                $table->foreignId('collected_by')->nullable()->after('receipt_no')->constrained('users')->onDelete('set null');
            }
            if (!Schema::hasColumn('transport_fees', 'remarks')) {
                $table->text('remarks')->nullable()->after('collected_by');
            }
        });

        // 2. Create discount_approvals table
        if (!Schema::hasTable('discount_approvals')) {
            Schema::create('discount_approvals', function (Blueprint $table) {
                $table->id();
                $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
                $table->foreignId('discount_rule_id')->nullable()->constrained('discount_rules')->onDelete('set null');
                $table->foreignId('fee_type_id')->nullable()->constrained('fee_types')->onDelete('set null');
                $table->decimal('amount', 10, 2);
                $table->string('month');
                $table->string('academic_year');
                $table->string('status')->default('pending_verification'); // pending_verification, active, rejected
                $table->string('verification_slip')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
                $table->foreignId('verified_by')->nullable()->constrained('users')->onDelete('set null');
                $table->timestamps();
            });
        }

        // 3. Create fee_reversal_requests table
        if (!Schema::hasTable('fee_reversal_requests')) {
            Schema::create('fee_reversal_requests', function (Blueprint $table) {
                $table->id();
                $table->foreignId('fee_collection_id')->constrained('fee_collections')->onDelete('cascade');
                $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
                $table->foreignId('requested_by')->constrained('users')->onDelete('cascade');
                $table->text('reason');
                $table->string('status')->default('pending'); // pending, approved, rejected
                $table->foreignId('processed_by')->nullable()->constrained('users')->onDelete('set null');
                $table->timestamp('reversal_date')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_reversal_requests');
        Schema::dropIfExists('discount_approvals');

        if (Schema::hasTable('transport_fees')) {
            Schema::table('transport_fees', function (Blueprint $table) {
                $table->dropForeign(['vehicle_id']);
                $table->dropColumn('vehicle_id');
                $table->dropColumn('payment_date');
                $table->dropColumn('payment_mode');
                $table->dropColumn('transaction_id');
                $table->dropColumn('receipt_no');
                $table->dropForeign(['collected_by']);
                $table->dropColumn('collected_by');
                $table->dropColumn('remarks');
            });
            Schema::rename('transport_fees', 'student_transport_dues');
        }
    }
};

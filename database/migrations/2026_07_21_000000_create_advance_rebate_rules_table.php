<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * cutoff_month_day is stored as a recurring 'MM-DD' (not a literal
     * date) so one rule works every academic session without needing to
     * be recreated each year -- the cutoff date is resolved against
     * whatever academic year is actually being evaluated.
     */
    public function up(): void
    {
        Schema::create('advance_rebate_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type', 10); // percent, fixed
            $table->decimal('value', 10, 2);
            $table->json('applicable_fee_type_ids')->nullable(); // default: [Tuition FeeType id]
            $table->string('cutoff_month_day', 5); // 'MM-DD'
            $table->string('min_coverage', 20)->default('full_session');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('advance_rebate_rules');
    }
};

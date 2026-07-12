<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Drops the tables left over from the "Professional Fee Management"
     * system (System C) -- its controller had every route commented out,
     * its service wrote fields (assigned_at, valid_from, total_amount on
     * student_fee_assignments; term/discount_percentage on fee_structures)
     * that don't exist on the live schema, and it has been fully removed
     * from the codebase. fee_collections and student_fee_assignments were
     * also created by the original System C migration but were later
     * dropped and recreated with the real System A shape by
     * 2026_02_12_100003_create_student_fee_assignments_table.php and
     * 2026_02_12_100004_create_fee_collections_table.php -- those two
     * tables are still in active use and are NOT touched here.
     */
    public function up(): void
    {
        Schema::dropIfExists('student_fee_discounts');
        Schema::dropIfExists('fee_discounts');
        Schema::dropIfExists('fee_receipts');
        Schema::dropIfExists('fee_structure_details');
        Schema::dropIfExists('fee_heads');
    }

    public function down(): void
    {
        // Intentionally not recreated -- System C is retired. If ever
        // needed, restore from 2026_02_12_000000_create_professional_fee_management_system.php.
    }
};

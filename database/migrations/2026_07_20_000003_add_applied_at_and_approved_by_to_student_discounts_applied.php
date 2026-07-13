<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "All applied concessions store rule_id, applied_at, approved_by" --
     * discount_rule_id already serves as rule_id. approved_by stays null
     * for auto-applied discounts (the normal collection-time snapshot);
     * it's populated only for the manual-override/verified-discount path.
     */
    public function up(): void
    {
        Schema::table('student_discounts_applied', function (Blueprint $table) {
            $table->timestamp('applied_at')->nullable()->after('academic_year');
            $table->foreignId('approved_by')->nullable()->after('applied_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('student_discounts_applied', function (Blueprint $table) {
            $table->dropForeign(['approved_by']);
            $table->dropColumn(['applied_at', 'approved_by']);
        });
    }
};

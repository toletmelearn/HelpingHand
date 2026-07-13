<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A real family grouping entity -- today the only structural link
     * between siblings is students.parent_id (built by silent phone-based
     * dedup in Student::booted()), while the discount engine's 'sibling'
     * rule and LedgerService's family ledger both independently re-derive
     * "siblings" via father_name+mobile string matching, bypassing
     * parent_id entirely. This table becomes the single real grouping,
     * populated only through explicit admin confirmation
     * (family_link_suggestions), never silently.
     */
    public function up(): void
    {
        Schema::create('families', function (Blueprint $table) {
            $table->id();
            $table->string('guardian_name');
            $table->string('mobile', 20);
            $table->string('aadhaar_number', 20)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('mobile');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('families');
    }
};

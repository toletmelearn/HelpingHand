<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Priority 1.3: an optional per-pair override of the transfer time
     * between two SPECIFIC buildings (e.g. Main<->Annex is a 2-minute walk
     * but Main<->Science Block is 12 minutes across campus) -- when no row
     * exists for a pair, Building::transferTimeTo() falls back to the
     * stricter of the two buildings' own transfer_time_in_minutes default.
     * building_a_id is always the smaller id (TransferTimeValidator/
     * Building normalize the pair before writing or querying), so a pair
     * is stored and looked up exactly once regardless of travel direction.
     */
    public function up(): void
    {
        Schema::create('building_transfer_times', function (Blueprint $table) {
            $table->id();
            $table->foreignId('building_a_id')->constrained('buildings')->cascadeOnDelete();
            $table->foreignId('building_b_id')->constrained('buildings')->cascadeOnDelete();
            $table->unsignedInteger('transfer_time_in_minutes');
            $table->timestamps();

            $table->unique(['building_a_id', 'building_b_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('building_transfer_times');
    }
};

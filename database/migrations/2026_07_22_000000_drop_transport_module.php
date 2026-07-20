<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use App\Models\FeeType;

return new class extends Migration
{
    /**
     * The transport module (fleet management + per-student transport fee
     * billing) has been removed from the codebase in its entirety. Drops
     * are ordered child-to-parent to satisfy FK constraints: transport_adjustments
     * references transport_fees; transport_fees and student_transport
     * reference routes/route_stops/vehicles; route_stops references routes.
     */
    public function up(): void
    {
        Schema::dropIfExists('transport_adjustments');
        Schema::dropIfExists('transport_fees');
        Schema::dropIfExists('student_transport_dues');
        Schema::dropIfExists('student_transport');
        Schema::dropIfExists('route_stops');
        Schema::dropIfExists('routes');
        Schema::dropIfExists('drivers');
        Schema::dropIfExists('vehicles');

        FeeType::where('name', 'Transport Fee')->delete();
    }

    public function down(): void
    {
        // Intentionally not recreated -- the transport module is retired.
        // If ever needed, restore from 2026_06_16_130000_create_transport_tables.php
        // and the subsequent transport migrations.
    }
};

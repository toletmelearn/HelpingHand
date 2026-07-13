<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\FeeType;

return new class extends Migration
{
    /**
     * Transport billing already runs entirely through its own pipeline
     * (transport_fees / GenerateMonthlyTransportDues, posted via
     * LedgerService's 'student_transport_due' reference type) and never
     * goes through FeeStructureItem -- BulkFeeAssignmentService and
     * StructureAdjustmentService::changeFeeStructure() already hard-skip
     * anything named "Transport Fee". Deactivating the FeeType just
     * removes it from the fee-structure builder's picker (FeeType::active())
     * so no new FeeStructureItem can be created against it going forward;
     * existing ledger lookups key on name, not status, so nothing else
     * changes.
     */
    public function up(): void
    {
        FeeType::where('name', 'Transport Fee')->update(['status' => 'inactive']);
    }

    public function down(): void
    {
        FeeType::where('name', 'Transport Fee')->update(['status' => 'active']);
    }
};

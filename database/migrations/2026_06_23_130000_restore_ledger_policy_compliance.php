<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('student_fee_ledgers')) {
            $this->restoreCompliance();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op or backfill can be re-run, but since we are hardening compliance, down is no-op.
    }

    /**
     * Restore policy compliance by removing guess fallbacks.
     */
    private function restoreCompliance(): void
    {
        try {
            $ledgers = DB::table('student_fee_ledgers')->get();
            $transportFeeType = DB::table('fee_types')->where('name', 'Transport Fee')->first();

            foreach ($ledgers as $ledger) {
                $academicYear = null;
                $classId = null;
                $feeTypeId = null;

                // Resolve tags ONLY based on direct historical relations
                if ($ledger->reference_type === 'fee_structure_item') {
                    $item = DB::table('fee_structure_items')->where('id', $ledger->reference_id)->first();
                    if ($item) {
                        $feeTypeId = $item->fee_type_id;
                        $structure = DB::table('fee_structures')->where('id', $item->fee_structure_id)->first();
                        if ($structure) {
                            $academicYear = $structure->academic_year;
                            $classVal = DB::table('school_classes')->where('name', $structure->class_name)->first();
                            if ($classVal) {
                                $classId = $classVal->id;
                            }
                        }
                    }
                } elseif ($ledger->reference_type === 'student_transport_due') {
                    $due = DB::table('student_transport_dues')->where('id', $ledger->reference_id)->first();
                    if ($due) {
                        $feeTypeId = $transportFeeType ? $transportFeeType->id : null;
                        $academicYear = $due->academic_year;
                    }
                } elseif ($ledger->reference_type === 'fee_collection') {
                    $collection = DB::table('fee_collections')->where('id', $ledger->reference_id)->first();
                    if ($collection) {
                        $structure = DB::table('fee_structures')->where('id', $collection->fee_structure_id)->first();
                        if ($structure) {
                            $academicYear = $structure->academic_year;
                            $classVal = DB::table('school_classes')->where('name', $structure->class_name)->first();
                            if ($classVal) {
                                $classId = $classVal->id;
                            }
                        }
                    }
                } elseif ($ledger->reference_type === 'fee_refund') {
                    $refund = DB::table('fee_refunds')->where('id', $ledger->reference_id)->first();
                    if ($refund && $refund->fee_collection_id) {
                        $collection = DB::table('fee_collections')->where('id', $refund->fee_collection_id)->first();
                        if ($collection) {
                            $structure = DB::table('fee_structures')->where('id', $collection->fee_structure_id)->first();
                            if ($structure) {
                                $academicYear = $structure->academic_year;
                                $classVal = DB::table('school_classes')->where('name', $structure->class_name)->first();
                                if ($classVal) {
                                    $classId = $classVal->id;
                                }
                            }
                        }
                    }
                }

                // If resolved values differ from what's currently in database, or if they were guesses,
                // we overwrite with the resolved values (which will be NULL if unresolved).
                DB::table('student_fee_ledgers')
                    ->where('id', $ledger->id)
                    ->update([
                        'academic_year' => $academicYear,
                        'class_id' => $classId,
                        'fee_type_id' => $feeTypeId,
                    ]);
            }
        } catch (\Exception $e) {
            Log::error('Compliance restoration migration failed: ' . $e->getMessage());
        }
    }
};

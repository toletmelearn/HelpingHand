<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
        // 1. Upgrade fee_structure_items
        if (Schema::hasTable('fee_structure_items')) {
            Schema::table('fee_structure_items', function (Blueprint $table) {
                if (!Schema::hasColumn('fee_structure_items', 'billing_frequency')) {
                    $table->string('billing_frequency')->nullable(); // monthly, quarterly, half_yearly, annual, one_time
                }
                if (!Schema::hasColumn('fee_structure_items', 'charge_months')) {
                    $table->json('charge_months')->nullable();
                }
            });
        }

        // 2. Create fee_refunds table
        if (!Schema::hasTable('fee_refunds')) {
            Schema::create('fee_refunds', function (Blueprint $table) {
                $table->id();
                $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
                $table->foreignId('fee_collection_id')->nullable()->constrained('fee_collections')->nullOnDelete();
                $table->decimal('amount', 10, 2);
                $table->enum('type', ['refund', 'reversal']);
                $table->text('reason')->nullable();
                $table->string('payment_mode');
                $table->foreignId('processed_by')->constrained('users');
                $table->timestamp('processed_at')->useCurrent();
                $table->timestamps();
            });
        }

        // 3. Upgrade student_fee_ledgers
        if (Schema::hasTable('student_fee_ledgers')) {
            Schema::table('student_fee_ledgers', function (Blueprint $table) {
                if (!Schema::hasColumn('student_fee_ledgers', 'academic_year')) {
                    $table->string('academic_year')->nullable();
                }
                if (!Schema::hasColumn('student_fee_ledgers', 'class_id')) {
                    $table->foreignId('class_id')->nullable()->constrained('school_classes')->nullOnDelete();
                }
                if (!Schema::hasColumn('student_fee_ledgers', 'fee_type_id')) {
                    $table->foreignId('fee_type_id')->nullable()->constrained('fee_types')->nullOnDelete();
                }
                if (!Schema::hasColumn('student_fee_ledgers', 'unpaid_amount')) {
                    $table->decimal('unpaid_amount', 10, 2)->default(0.00);
                }
            });
        }

        // 4. Backfill existing structures & ledger tagging
        $this->backfillData();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fee_refunds');

        if (Schema::hasTable('fee_structure_items')) {
            Schema::table('fee_structure_items', function (Blueprint $table) {
                $table->dropColumn(['billing_frequency', 'charge_months']);
            });
        }

        if (Schema::hasTable('student_fee_ledgers')) {
            Schema::table('student_fee_ledgers', function (Blueprint $table) {
                $table->dropForeign(['student_fee_ledgers_class_id_foreign']);
                $table->dropForeign(['student_fee_ledgers_fee_type_id_foreign']);
                $table->dropColumn(['academic_year', 'class_id', 'fee_type_id', 'unpaid_amount']);
            });
        }
    }

    /**
     * Backfill existing data for backward compatibility.
     */
    private function backfillData(): void
    {
        try {
            // A. Backfill fee_structure_items
            $items = DB::table('fee_structure_items')->get();
            foreach ($items as $item) {
                $structure = DB::table('fee_structures')->where('id', $item->fee_structure_id)->first();
                if (!$structure) continue;

                $freq = $structure->frequency ?? 'monthly';
                $months = [];

                if ($freq === 'monthly') {
                    $billingFreq = 'monthly';
                    $months = ['April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December', 'January', 'February', 'March'];
                } elseif ($freq === 'quarterly') {
                    $billingFreq = 'quarterly';
                    $months = ['Q1', 'Q2', 'Q3', 'Q4'];
                } elseif ($freq === 'yearly') {
                    $billingFreq = 'annual';
                    $months = ['Annual'];
                } else {
                    $billingFreq = 'one_time';
                    $months = ['Annual'];
                }

                DB::table('fee_structure_items')
                    ->where('id', $item->id)
                    ->update([
                        'billing_frequency' => $billingFreq,
                        'charge_months' => json_encode($months)
                    ]);
            }

            // B. Backfill student_fee_ledgers tags
            $ledgers = DB::table('student_fee_ledgers')->get();
            $transportFeeType = DB::table('fee_types')->where('name', 'Transport Fee')->first();

            foreach ($ledgers as $ledger) {
                $academicYear = null;
                $classId = null;
                $feeTypeId = null;

                // Resolve tags based on reference
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
                }


                // Update ledger row with tags
                DB::table('student_fee_ledgers')
                    ->where('id', $ledger->id)
                    ->update([
                        'academic_year' => $academicYear,
                        'class_id' => $classId,
                        'fee_type_id' => $feeTypeId,
                        'unpaid_amount' => ($ledger->debit > 0) ? $ledger->debit : 0.00
                    ]);
            }

            // C. Perform FIFO Matching on existing ledger entries to align unpaid_amount
            $studentIds = DB::table('student_fee_ledgers')->distinct()->pluck('student_id');
            foreach ($studentIds as $studentId) {
                // Fetch all entries for this student in chronological order
                $entries = DB::table('student_fee_ledgers')
                    ->where('student_id', $studentId)
                    ->orderBy('date', 'asc')
                    ->orderBy('id', 'asc')
                    ->get();

                $debits = [];
                $credits = [];

                foreach ($entries as $entry) {
                    if ($entry->debit > 0) {
                        $debits[] = $entry;
                    }
                    if ($entry->credit > 0) {
                        $credits[] = (float)$entry->credit;
                    }
                }

                // Perform FIFO simulation
                $creditIndex = 0;
                $creditCount = count($credits);
                $currentCredit = $creditCount > 0 ? $credits[0] : 0;

                foreach ($debits as $debit) {
                    $remaining = (float)$debit->debit;

                    while ($remaining > 0 && $creditIndex < $creditCount) {
                        if ($currentCredit >= $remaining) {
                            $currentCredit -= $remaining;
                            $remaining = 0;
                        } else {
                            $remaining -= $currentCredit;
                            $currentCredit = 0;
                        }

                        if ($currentCredit == 0) {
                            $creditIndex++;
                            if ($creditIndex < $creditCount) {
                                $currentCredit = $credits[$creditIndex];
                            }
                        }
                    }

                    // Save the calculated unpaid amount
                    DB::table('student_fee_ledgers')
                        ->where('id', $debit->id)
                        ->update(['unpaid_amount' => round($remaining, 2)]);
                }
            }
        } catch (\Exception $e) {
            Log::error('Backfill failed: ' . $e->getMessage());
        }
    }
};

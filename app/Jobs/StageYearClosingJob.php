<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\FinancialYearClosing;
use App\Services\YearClosing\ClosingEngine;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class StageYearClosingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600; // Allow up to 10 minutes

    public function __construct(
        public int $closingId,
        public string $fromSession,
        public string $toSession,
        public ?string $remarks,
        public int $userId
    ) {}

    public function handle(ClosingEngine $engine, AuditLogService $audit): void
    {
        Log::info("StageYearClosingJob starting for closing ID: {$this->closingId}");

        $closing = FinancialYearClosing::find($this->closingId);
        if (!$closing) {
            Log::error("FinancialYearClosing record not found: {$this->closingId}");
            return;
        }

        // Cache lock specific to academic year
        $lockKey = "year-closing-lock-{$this->fromSession}";
        $lock = Cache::lock($lockKey, 600); // 10 minute lock

        if (!$lock->get()) {
            $msg = "Another year closing process is already active for session {$this->fromSession}.";
            $closing->update([
                'status' => 'failed',
                'error_message' => $msg,
            ]);
            Log::warning($msg);
            return;
        }

        try {
            $closing->update([
                'status' => 'processing',
                'started_at' => now(),
            ]);

            // Log starting audit trail
            $audit->logSystemAction('FinancialYearClosing', $this->closingId, 'year_closing_started', 'status', 'staged', 'processing', $this->userId);

            $totalStudents = DB::table('students')->count();
            if ($totalStudents === 0) {
                $closing->update([
                    'status' => 'staged',
                    'completed_at' => now(),
                    'progress_percent' => 100.00,
                ]);
                $lock->release();
                return;
            }

            $chunkSize = 250;
            $totalBatches = (int) ceil($totalStudents / $chunkSize);
            $closing->update(['total_batches' => $totalBatches]);

            $processedCount = 0;
            $batchIndex = 0;

            $totalBalance = 0.00;
            $totalAdvance = 0.00;
            $totalScholarship = 0.00;
            $totalRefund = 0.00;

            DB::table('students')->orderBy('id')->chunk($chunkSize, function ($students) use (
                $closing, $engine, &$processedCount, &$batchIndex, $totalStudents,
                &$totalBalance, &$totalAdvance, &$totalScholarship, &$totalRefund
            ) {
                $batchIndex++;

                DB::transaction(function () use (
                    $students, $closing, $engine, &$processedCount,
                    &$totalBalance, &$totalAdvance, &$totalScholarship, &$totalRefund
                ) {
                    foreach ($students as $student) {
                        $balances = $engine->closeStudent($student->id, $closing->id, $this->fromSession, $this->toSession);
                        
                        $totalBalance += $balances['arrears'];
                        $totalRefund += $balances['refunds'];
                        $totalScholarship += $balances['scholarships'];
                        $totalAdvance += $balances['advances'];
                        
                        $processedCount++;
                    }
                });

                // Update database once per 250 students (progress batch)
                $progressPercent = round(($processedCount / $totalStudents) * 100, 2);
                $closing->update([
                    'total_students_processed' => $processedCount,
                    'processed_batch' => $batchIndex,
                    'progress_percent' => min(100.00, $progressPercent),
                    'total_balance_carried' => $totalBalance,
                    'total_advance_carried' => $totalAdvance,
                    'total_scholarship_carried' => $totalScholarship,
                    'total_refund_carried' => $totalRefund,
                    'last_processed_student_id' => $students->last()->id,
                ]);

                Log::info("StageYearClosingJob processed batch {$batchIndex}/{$closing->total_batches} ({$progressPercent}%)");
            });

            $closing->update([
                'status' => 'staged',
                'completed_at' => now(),
                'progress_percent' => 100.00,
            ]);

            // Log completion audit trail
            $audit->logSystemAction('FinancialYearClosing', $this->closingId, 'year_closing_completed', 'status', 'processing', 'staged', $this->userId);

        } catch (\Exception $e) {
            Log::error("Error during Year Closing: " . $e->getMessage());
            $closing->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
            if (app()->environment('testing')) {
                throw $e;
            }
        } finally {
            $lock->release();
        }
    }
}

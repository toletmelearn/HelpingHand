<?php

namespace App\Console\Commands;

use App\Services\StudentStatus\TerminalStatusReconciliationDetector;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class ReconcileTerminalStatusesCommand extends Command
{
    protected $signature = 'helpinghand:reconcile-terminal-statuses {--dry-run : Show detected drift without changing data}';

    protected $description = 'Detect terminal student status and class compatibility drift without changing data.';

    public function handle(TerminalStatusReconciliationDetector $detector): int
    {
        if (! $this->option('dry-run')) {
            $this->error('This command is dry-run only in Phase 3U. Use --dry-run to inspect issues.');

            return self::FAILURE;
        }

        $result = $detector->detect();
        $summary = $result['summary'];

        $this->info('HelpingHand terminal status reconciliation dry-run');
        $this->line('No data will be changed.');
        $this->newLine();

        $this->table(['Detection', 'Count'], [
            ['Latest terminal statuses with class/section drift', $summary['terminal_status_drift']],
            ['Latest passed_out statuses without Passed Out promotion log', $summary['passed_out_without_log']],
            ['Passed Out promotion logs without latest passed_out status', $summary['passed_out_logs_without_latest_status']],
            ['Suspicious Passed Out promotion logs', $summary['suspicious_passed_out_logs']],
            ['class_id / school_class_id conflicts', $summary['class_fk_conflicts']],
            ['class_id null/non-null mismatches', $summary['class_fk_null_mismatches']],
        ]);

        $this->renderStudentRows(
            'Latest terminal statuses with class/section drift',
            $result['terminal_status_drift'],
            'Review terminal status cleanup; possible Phase 3M-style cleanup needed.'
        );

        $this->renderStudentRows(
            'Latest passed_out statuses without Passed Out promotion log',
            $result['passed_out_without_log'],
            'Review missing Passed Out promotion log; do not auto-create without admin confirmation.'
        );

        $this->renderStudentRows(
            'Passed Out promotion logs without latest passed_out status',
            $result['passed_out_logs_without_latest_status'],
            'Review status/log alignment; do not auto-create status without admin confirmation.'
        );

        $this->renderLogRows(
            'Suspicious Passed Out promotion logs',
            $result['suspicious_passed_out_logs'],
            'Review promotion log source class before any repair.'
        );

        $this->renderStudentRows(
            'class_id / school_class_id conflicts',
            $result['class_fk_conflicts'],
            'Review class_id/school_class_id conflict; decide canonical class before repair.'
        );

        $this->renderStudentRows(
            'class_id null/non-null mismatches',
            $result['class_fk_null_mismatches'],
            'Review class compatibility fields before repair.'
        );

        $this->newLine();
        $this->warn('Dry-run complete. No data was changed and no apply mode exists in Phase 3U.');

        return self::SUCCESS;
    }

    private function renderStudentRows(string $title, Collection $rows, string $recommendedAction): void
    {
        $this->newLine();
        $this->line($title . ': ' . $rows->count());

        if ($rows->isEmpty()) {
            return;
        }

        $this->table(
            [
                'Student ID',
                'Name',
                'Latest Status',
                'class_id',
                'school_class_id',
                'class',
                'section_id',
                'section',
                'Matching Log',
                'Recommended Action',
            ],
            $rows->map(fn ($row) => [
                $row->student_id,
                $row->name ?? 'N/A',
                $row->latest_status ?? 'N/A',
                $row->class_id ?? 'NULL',
                $row->school_class_id ?? 'NULL',
                $row->class ?? 'NULL',
                $row->section_id ?? 'NULL',
                $row->section ?? 'NULL',
                $row->matching_log_status ?? 'N/A',
                $recommendedAction,
            ])->all()
        );
    }

    private function renderLogRows(string $title, Collection $rows, string $recommendedAction): void
    {
        $this->newLine();
        $this->line($title . ': ' . $rows->count());

        if ($rows->isEmpty()) {
            return;
        }

        $this->table(
            ['Log ID', 'Student ID', 'Name', 'from_class', 'to_class', 'Recommended Action'],
            $rows->map(fn ($row) => [
                $row->log_id,
                $row->student_id,
                $row->name ?? 'N/A',
                $row->from_class ?? 'NULL',
                $row->to_class ?? 'NULL',
                $recommendedAction,
            ])->all()
        );
    }
}

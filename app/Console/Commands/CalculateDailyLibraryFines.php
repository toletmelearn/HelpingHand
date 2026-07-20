<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\BookIssue;
use App\Models\LibrarySetting;
use Carbon\Carbon;

class CalculateDailyLibraryFines extends Command
{
    protected $signature = 'library:calculate-fines';
    protected $description = 'Finds overdue book issues, updates delay days, and calculates late fine amounts daily.';

    public function handle()
    {
        $today = Carbon::today();
        $overdueIssues = BookIssue::where('status', 'issued')
            ->where('due_date', '<', $today)
            ->get();

        if ($overdueIssues->isEmpty()) {
            $this->info('No overdue book issues found.');
            return 0;
        }

        $settings = LibrarySetting::getSetting();
        $finePerDay = $settings->fine_per_day;
        $updatedCount = 0;

        foreach ($overdueIssues as $issue) {
            $delayDays = $today->diffInDays($issue->due_date, true);
            $fineAmount = $delayDays * $finePerDay;

            $issue->update([
                'delay_days' => $delayDays,
                'fine_amount' => $fineAmount,
            ]);

            $updatedCount++;
        }

        $this->info("Updated {$updatedCount} overdue book issues with new fines.");
        return 0;
    }
}

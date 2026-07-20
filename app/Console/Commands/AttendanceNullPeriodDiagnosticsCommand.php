<?php

namespace App\Console\Commands;

use App\Support\Attendance\AttendancePeriodPresenter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AttendanceNullPeriodDiagnosticsCommand extends Command
{
    protected $signature = 'helpinghand:attendance-null-period-diagnostics {--json : Output JSON} {--limit=50 : Limit sample rows}';

    protected $description = 'Read-only diagnostics for attendance null/empty period values and duplicate risks.';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $asJson = $this->option('json');

        // Summary counts
        $total = DB::table('attendances')->count();
        $nullCount = DB::table('attendances')->whereNull('period')->count();
        $emptyCount = DB::table('attendances')->where('period', '')->count();
        $trimEmptyCount = DB::table('attendances')
            ->whereRaw("trim(coalesce(period, '')) = ''")
            ->count();

        // Distinct periods
        $distinct = DB::table('attendances')
            ->select('period', DB::raw('count(*) as cnt'))
            ->groupBy('period')
            ->orderByDesc('cnt')
            ->limit(200)
            ->get();

        $classificationSummary = $distinct
            ->groupBy(fn($row) => AttendancePeriodPresenter::classify($row->period))
            ->map(fn($rows) => $rows->sum('cnt'));

        // Duplicate exact groups by student_id, date, period
        $duplicateExact = DB::table('attendances')
            ->select('student_id','date','period', DB::raw('count(*) as cnt'))
            ->groupBy('student_id','date','period')
            ->havingRaw('count(*) > 1')
            ->orderByDesc('cnt')
            ->limit(200)
            ->get();

        // Duplicate groups where period IS NULL
        $duplicateNull = DB::table('attendances')
            ->select('student_id','date', DB::raw('count(*) as cnt'))
            ->whereNull('period')
            ->groupBy('student_id','date')
            ->havingRaw('count(*) > 1')
            ->orderByDesc('cnt')
            ->limit(200)
            ->get();

        // Duplicate groups where period = ''
        $duplicateEmpty = DB::table('attendances')
            ->select('student_id','date', DB::raw('count(*) as cnt'))
            ->where('period', '')
            ->groupBy('student_id','date')
            ->havingRaw('count(*) > 1')
            ->orderByDesc('cnt')
            ->limit(200)
            ->get();

        // Suspicious sentinel-like values
        $suspiciousCandidates = ['full_day','full-day','full day','fullday','all_day','all-day'];
        $suspicious = DB::table('attendances')
            ->whereIn('period', $suspiciousCandidates)
            ->limit($limit)
            ->get();

        // Samples
        $samples = [
            'null_period' => DB::table('attendances')->whereNull('period')->limit($limit)->get(),
            'empty_period' => DB::table('attendances')->where('period', '')->limit($limit)->get(),
            'duplicate_null_groups_sample' => DB::table('attendances')
                ->whereNull('period')
                ->select('student_id','date', DB::raw('count(*) as cnt'))
                ->groupBy('student_id','date')
                ->havingRaw('count(*) > 1')
                ->limit($limit)
                ->get(),
            'suspicious' => $suspicious,
        ];

        $result = [
            'summary' => [
                'total_rows' => $total,
                'period_null_count' => $nullCount,
                'period_empty_string_count' => $emptyCount,
                'period_trim_empty_count' => $trimEmptyCount,
            ],
            'distinct_periods' => $distinct,
            'duplicate_exact_groups' => $duplicateExact,
            'duplicate_null_period_groups' => $duplicateNull,
            'duplicate_empty_period_groups' => $duplicateEmpty,
            'suspicious_sentinel_rows' => $suspicious,
            'period_classification_summary' => $classificationSummary,
            'samples' => $samples,
        ];

        if ($asJson) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT));
            return self::SUCCESS;
        }

        $this->info('Attendance Null/Empty Period Diagnostics (read-only)');
        $this->line('Read-only diagnostics. No attendance data was modified.');
        $this->newLine();

        $this->table(['Metric', 'Value'], [
            ['Total rows', $total],
            ['Period IS NULL', $nullCount],
            ['Period = ""', $emptyCount],
            ['Trimmed period empty', $trimEmptyCount],
        ]);

        $this->newLine();
        $this->info('Distinct period values (top)');
        $this->table(['Period', 'Count'], $distinct->map(fn($r) => [(string) $r->period ?? 'NULL', $r->cnt])->all());

        $this->newLine();
        $this->info('Period classification summary');
        $this->table(['Classification', 'Count'], $classificationSummary->map(fn($count, $classification) => [$classification, $count])->values()->all());

        $this->newLine();
        $this->info('Duplicate groups by student_id, date, period');
        $this->table(['student_id','date','period','count'], $duplicateExact->map(fn($r) => [$r->student_id,$r->date, (string) $r->period ?? 'NULL', $r->cnt])->all());

        $this->newLine();
        $this->info('Duplicate groups where period IS NULL');
        $this->table(['student_id','date','count'], $duplicateNull->map(fn($r) => [$r->student_id,$r->date,$r->cnt])->all());

        $this->newLine();
        $this->info('Duplicate groups where period = ""');
        $this->table(['student_id','date','count'], $duplicateEmpty->map(fn($r) => [$r->student_id,$r->date,$r->cnt])->all());

        $this->newLine();
        $this->info('Suspicious sentinel-like period rows (sample)');
        $this->table(['id','student_id','date','period'], $suspicious->map(fn($r) => [$r->id ?? 'N/A',$r->student_id ?? 'N/A',$r->date ?? 'N/A',$r->period ?? 'NULL'])->all());

        $this->newLine();
        $this->info('Samples (null, empty, duplicate-null groups)');

        $this->info('Null period sample:');
        $this->table(['id','student_id','date','period'], $samples['null_period']->map(fn($r) => [$r->id ?? 'N/A',$r->student_id ?? 'N/A',$r->date ?? 'N/A', 'NULL'])->all());

        $this->info('Empty period sample:');
        $this->table(['id','student_id','date','period'], $samples['empty_period']->map(fn($r) => [$r->id ?? 'N/A',$r->student_id ?? 'N/A',$r->date ?? 'N/A', $r->period ?? ''])->all());

        $this->info('Duplicate null-period groups sample:');
        $this->table(['student_id','date','count'], $samples['duplicate_null_groups_sample']->map(fn($r) => [$r->student_id,$r->date,$r->cnt])->all());

        $this->newLine();
        $this->warn('Diagnostics complete. No attendance data was modified.');

        return self::SUCCESS;
    }
}

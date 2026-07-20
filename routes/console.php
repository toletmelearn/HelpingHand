<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Console\Commands\AssignMissingTeachers;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('app:assign-missing-teachers', function () {
    $this->call('app:assign-missing-teachers');
})->purpose('Automatically assign classes and subjects to teachers who have no assignments');

Artisan::command('ledger:migrate-historical {academic_year?}', function () {
    $this->call(\App\Console\Commands\MigrateHistoricalLedger::class, [
        'academic_year' => $this->argument('academic_year')
    ]);
})->purpose('Migrate historical ledger');

Artisan::starting(function ($artisan) {
    $artisan->resolve(\App\Console\Commands\AttendanceNullPeriodDiagnosticsCommand::class);
    $artisan->resolve(\App\Console\Commands\ReconcileTerminalStatusesCommand::class);
    $artisan->resolve(\App\Console\Commands\AdminSidebarAudit::class);
    $artisan->resolve(\App\Console\Commands\RouteHealthCheck::class);
    $artisan->resolve(\App\Console\Commands\SystemRouteAudit::class);
    $artisan->resolve(\App\Console\Commands\TestDataModel::class);
    $artisan->resolve(\App\Console\Commands\UpdateBiometricSettings::class);
});


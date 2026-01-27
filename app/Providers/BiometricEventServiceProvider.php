<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\TeacherBiometricRecord;
use App\Observers\TeacherBiometricRecordObserver;

class BiometricEventServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        TeacherBiometricRecord::observe(TeacherBiometricRecordObserver::class);
    }
}
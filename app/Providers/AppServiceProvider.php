<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Share academic session globally
        View::share('current_academic_year', $this->getCurrentAcademicYear());
    }
    
    /**
     * Get current academic year in format YYYY-YY
     */
    public function getCurrentAcademicYear()
    {
        $currentMonth = date('n');
        $currentYear = date('Y');
        
        // Academic year typically starts in April (month 4)
        if ($currentMonth >= 4) {
            $startYear = $currentYear;
            $endYear = $currentYear + 1;
        } else {
            $startYear = $currentYear - 1;
            $endYear = $currentYear;
        }
        
        return $startYear . '-' . substr($endYear, -2);
    }
    
    /**
     * Get academic years for dropdown
     */
    public function getAcademicYears($count = 5)
    {
        $years = [];
        $currentYear = date('Y');
        
        for ($i = 0; $i < $count; $i++) {
            $startYear = $currentYear - $i;
            $endYear = $startYear + 1;
            $yearKey = $startYear . '-' . substr($endYear, -2);
            $years[$yearKey] = $yearKey;
        }
        
        return $years;
    }
}

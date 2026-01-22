<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Blade;

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
        // Add custom blade directive for academic year
        Blade::directive('academicYear', function () {
            return "<?php echo app(App\Providers\AppServiceProvider::class)->getCurrentAcademicYear(); ?>";
        });
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
}
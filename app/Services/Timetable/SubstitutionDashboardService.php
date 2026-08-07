<?php

namespace App\Services\Timetable;

use App\Models\TeacherSubstitution;

/**
 * T5 item 4: today's substitution count + unfilled arrangements for the
 * admin dashboard card. A tiny dedicated service (rather than an inline
 * query in the controller) purely so the "dashboard degrades gracefully"
 * requirement is mockable/testable the same way
 * ProfessionalDashboardService::getUpcomingEvents() already is.
 */
class SubstitutionDashboardService
{
    public function getTodaysSummary(): array
    {
        $today = TeacherSubstitution::whereDate('substitution_date', today())
            ->where('status', '!=', 'cancelled');

        return [
            'count' => (clone $today)->count(),
            'unfilled' => (clone $today)->whereNull('substitute_teacher_id')->count(),
        ];
    }
}

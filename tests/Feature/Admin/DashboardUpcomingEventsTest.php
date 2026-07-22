<?php

namespace Tests\Feature\Admin;

use App\Models\AcademicEvent;
use App\Models\Exam;
use App\Services\ProfessionalDashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardUpcomingEventsTest extends TestCase
{
    use RefreshDatabase;

    public function test_upcoming_events_includes_calendar_events_alongside_exams(): void
    {
        \Carbon\Carbon::setTestNow('2026-07-21 00:00:00');

        Exam::create([
            'name' => 'Unit Test 1',
            'exam_type' => 'unit_test',
            'class_name' => 'Class 10',
            'subject' => 'Science',
            'exam_date' => '2026-07-23',
            'start_time' => '09:00',
            'end_time' => '11:00',
            'total_marks' => 100,
            'passing_marks' => 33,
            'academic_year' => '2026-27',
            'status' => 'scheduled',
        ]);

        AcademicEvent::create([
            'title' => 'Founders Day',
            'type' => 'event',
            'start_date' => '2026-07-22',
            'end_date' => '2026-07-22',
            'is_active' => true,
        ]);

        AcademicEvent::create([
            'title' => 'Too Far Away',
            'type' => 'event',
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-01',
            'is_active' => true,
        ]);

        $events = (new ProfessionalDashboardService())->getUpcomingEvents();

        $titles = array_column($events, 'title');

        $this->assertContains('Founders Day', $titles);
        $this->assertContains('Unit Test 1', $titles);
        $this->assertNotContains('Too Far Away', $titles);

        // Founders Day (07-22) should sort before Unit Test 1 (07-23)
        $this->assertSame('Founders Day', $events[0]['title']);
    }

    public function test_inactive_calendar_events_are_excluded(): void
    {
        \Carbon\Carbon::setTestNow('2026-07-21 00:00:00');

        AcademicEvent::create([
            'title' => 'Cancelled Event',
            'type' => 'event',
            'start_date' => '2026-07-22',
            'end_date' => '2026-07-22',
            'is_active' => false,
        ]);

        $events = (new ProfessionalDashboardService())->getUpcomingEvents();

        $this->assertNotContains('Cancelled Event', array_column($events, 'title'));
    }
}

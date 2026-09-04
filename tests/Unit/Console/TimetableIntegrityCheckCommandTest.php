<?php

namespace Tests\Unit\Console;

use App\Models\BellTiming;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TimetableSlot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Item 7: a class-wide slot (section_id NULL) and a section-specific slot
 * of the SAME class at the SAME period don't collide at the DB unique-index
 * level (section_id_norm gives them different generated-column values), so
 * the database happily stores both even though a class-wide lesson covers
 * every section and genuinely overlaps a section-specific one. Only
 * TimetableConflictResolver::classSectionOverlapConflicts() catches this
 * today, and only for a placement going through it -- this command detects
 * the collision if it ever occurs via a write path that bypasses the
 * resolver (a raw TimetableSlot::create(), a data-fix script, a future
 * feature).
 */
class TimetableIntegrityCheckCommandTest extends TestCase
{
    use RefreshDatabase;

    private function makeClassWithSection(string $label): array
    {
        $class = SchoolClass::create(['name' => "TIC {$label} Class", 'class_order' => random_int(1, 100000), 'is_active' => true]);
        $section = Section::create(['name' => $label]);
        $this->bridgeSectionToClass($class, $section);
        $subject = Subject::create(['name' => "TIC {$label} Subject", 'code' => 'TIC' . uniqid()]);
        $teacher = Teacher::create(['name' => "TIC {$label} Teacher"]);
        $timing = BellTiming::create([
            'day_of_week' => 'Monday', 'period_name' => 'P1', 'start_time' => '08:00:00', 'end_time' => '08:45:00',
            'is_active' => true, 'is_break' => false, 'order_index' => 1,
        ]);

        return compact('class', 'section', 'subject', 'teacher', 'timing');
    }

    public function test_clean_timetable_reports_no_collisions(): void
    {
        $f = $this->makeClassWithSection('Clean');
        TimetableSlot::create([
            'school_class_id' => $f['class']->id, 'section_id' => $f['section']->id, 'bell_timing_id' => $f['timing']->id,
            'subject_id' => $f['subject']->id, 'teacher_id' => $f['teacher']->id,
            'status' => TimetableSlot::STATUS_PUBLISHED,
        ]);

        $this->artisan('timetable:check-integrity')
            ->expectsOutputToContain('No collisions found.')
            ->assertExitCode(0);
    }

    public function test_detects_a_class_wide_vs_section_specific_collision(): void
    {
        $f = $this->makeClassWithSection('Collide');
        // A second teacher -- the teacher_bell_status unique index already
        // blocks the SAME teacher double-booked at the same period, which
        // is a different, unrelated rule; using two teachers isolates the
        // class-wide/section-specific collision this command targets.
        $otherTeacher = Teacher::create(['name' => 'TIC Collide Teacher Two']);

        // Class-wide slot (no section) and a section-specific slot for the
        // SAME class + period + status -- the DB unique index allows both
        // to coexist (different section_id_norm values), even though a
        // class-wide lesson covers every section and genuinely overlaps.
        TimetableSlot::create([
            'school_class_id' => $f['class']->id, 'section_id' => null, 'bell_timing_id' => $f['timing']->id,
            'subject_id' => $f['subject']->id, 'teacher_id' => $f['teacher']->id,
            'status' => TimetableSlot::STATUS_PUBLISHED,
        ]);
        TimetableSlot::create([
            'school_class_id' => $f['class']->id, 'section_id' => $f['section']->id, 'bell_timing_id' => $f['timing']->id,
            'subject_id' => $f['subject']->id, 'teacher_id' => $otherTeacher->id,
            'status' => TimetableSlot::STATUS_PUBLISHED,
        ]);

        $this->artisan('timetable:check-integrity')
            ->expectsOutputToContain('Found 1 class-wide vs section-specific collision(s):')
            ->assertExitCode(1);
    }

    /** Two DIFFERENT sections at the same period is legitimate, not a collision -- must never be flagged. */
    public function test_two_different_sections_at_the_same_period_is_not_flagged(): void
    {
        $f = $this->makeClassWithSection('TwoSections');
        $sectionB = Section::create(['name' => 'TwoSectionsB']);
        $this->bridgeSectionToClass($f['class'], $sectionB);
        $otherTeacher = Teacher::create(['name' => 'TIC TwoSections Teacher Two']);

        TimetableSlot::create([
            'school_class_id' => $f['class']->id, 'section_id' => $f['section']->id, 'bell_timing_id' => $f['timing']->id,
            'subject_id' => $f['subject']->id, 'teacher_id' => $f['teacher']->id,
            'status' => TimetableSlot::STATUS_PUBLISHED,
        ]);
        TimetableSlot::create([
            'school_class_id' => $f['class']->id, 'section_id' => $sectionB->id, 'bell_timing_id' => $f['timing']->id,
            'subject_id' => $f['subject']->id, 'teacher_id' => $otherTeacher->id,
            'status' => TimetableSlot::STATUS_PUBLISHED,
        ]);

        $this->artisan('timetable:check-integrity')
            ->expectsOutputToContain('No collisions found.')
            ->assertExitCode(0);
    }

    /** Archived rows are historical snapshots expected to repeat the same class/period many times over -- excluded, matching the DB's own generated-column exclusion. */
    public function test_archived_rows_are_excluded_from_the_check(): void
    {
        $f = $this->makeClassWithSection('Archived');
        TimetableSlot::create([
            'school_class_id' => $f['class']->id, 'section_id' => null, 'bell_timing_id' => $f['timing']->id,
            'subject_id' => $f['subject']->id, 'teacher_id' => $f['teacher']->id,
            'status' => TimetableSlot::STATUS_ARCHIVED,
        ]);
        TimetableSlot::create([
            'school_class_id' => $f['class']->id, 'section_id' => $f['section']->id, 'bell_timing_id' => $f['timing']->id,
            'subject_id' => $f['subject']->id, 'teacher_id' => $f['teacher']->id,
            'status' => TimetableSlot::STATUS_ARCHIVED,
        ]);

        $this->artisan('timetable:check-integrity')
            ->expectsOutputToContain('No collisions found.')
            ->assertExitCode(0);
    }

    /** A published class-wide slot and a DRAFT section-specific slot represent different, non-conflicting bookings -- only rows sharing the same status collide. */
    public function test_different_statuses_do_not_false_positive(): void
    {
        $f = $this->makeClassWithSection('MixedStatus');
        TimetableSlot::create([
            'school_class_id' => $f['class']->id, 'section_id' => null, 'bell_timing_id' => $f['timing']->id,
            'subject_id' => $f['subject']->id, 'teacher_id' => $f['teacher']->id,
            'status' => TimetableSlot::STATUS_PUBLISHED,
        ]);
        TimetableSlot::create([
            'school_class_id' => $f['class']->id, 'section_id' => $f['section']->id, 'bell_timing_id' => $f['timing']->id,
            'subject_id' => $f['subject']->id, 'teacher_id' => $f['teacher']->id,
            'status' => TimetableSlot::STATUS_DRAFT,
        ]);

        $this->artisan('timetable:check-integrity')
            ->expectsOutputToContain('No collisions found.')
            ->assertExitCode(0);
    }
}

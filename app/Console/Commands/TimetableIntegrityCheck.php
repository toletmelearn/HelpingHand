<?php

namespace App\Console\Commands;

use App\Models\TimetableSlot;
use Illuminate\Console\Command;

/**
 * Item 7: a class-wide slot (section_id NULL) and a section-specific slot
 * of the SAME class at the SAME period do not collide at the unique-index
 * level -- section_id_norm gives them different generated-column values
 * (see 2026_07_27_112642_add_unique_constraints_to_timetable_slots.php),
 * so the database happily stores both even though a class-wide lesson
 * covers every section and genuinely overlaps a section-specific one.
 * Only TimetableConflictResolver::classSectionOverlapConflicts() catches
 * this today, and only for a placement going through it -- a raw
 * TimetableSlot::create(), a data-fix script, or a future write path that
 * bypasses the resolver could silently create this exact double-booking
 * with nothing to stop it.
 *
 * Read-only detection, not auto-fix: which of the two colliding rows is
 * "wrong" is a judgment call (the class-wide one may be the intended
 * whole-class lesson and the section-specific one a stale leftover, or
 * vice versa) that only a human reviewing the specific case can make
 * safely.
 */
class TimetableIntegrityCheck extends Command
{
    protected $signature = 'timetable:check-integrity';

    protected $description = 'Scan for class-wide vs section-specific timetable slot collisions the DB unique constraints cannot catch';

    public function handle(): int
    {
        $this->info('Scanning published/draft timetable slots for class-wide vs section-specific collisions...');

        // Archived rows are historical snapshots from past PUBLISH events
        // and are expected to repeat the same class/period combination many
        // times over -- excluded the same way the DB's own unique
        // constraints exclude them (class_bell_active_key is NULL for
        // archived rows).
        $slots = TimetableSlot::where('status', '!=', TimetableSlot::STATUS_ARCHIVED)
            ->with(['schoolClass', 'bellTiming', 'section'])
            ->get()
            ->groupBy(fn (TimetableSlot $s) => implode('|', [
                $s->school_class_id, $s->bell_timing_id, $s->status, $s->academic_year ?? '',
            ]));

        $collisions = [];

        foreach ($slots as $group) {
            $classWide = $group->whereNull('section_id');
            $sectionSpecific = $group->whereNotNull('section_id');

            if ($classWide->isEmpty() || $sectionSpecific->isEmpty()) {
                continue;
            }

            foreach ($classWide as $wide) {
                foreach ($sectionSpecific as $specific) {
                    $sectionLabel = $specific->section->name ?? (string) $specific->section_id;

                    $collisions[] = [
                        'Class' => $wide->schoolClass->name ?? "#{$wide->school_class_id}",
                        'Period' => $wide->bellTiming ? "{$wide->bellTiming->day_of_week} {$wide->bellTiming->period_name}" : "#{$wide->bell_timing_id}",
                        'Status' => $wide->status,
                        'Academic Year' => $wide->academic_year ?? '(none)',
                        'Class-wide slot' => $wide->id,
                        'Section-specific slot' => "{$specific->id} (Section: {$sectionLabel})",
                    ];
                }
            }
        }

        if (empty($collisions)) {
            $this->info('No collisions found.');

            return self::SUCCESS;
        }

        $this->error('Found '.count($collisions).' class-wide vs section-specific collision(s):');
        $this->table(array_keys($collisions[0]), $collisions);

        return self::FAILURE;
    }
}

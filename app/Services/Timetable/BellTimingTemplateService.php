<?php

namespace App\Services\Timetable;

use App\Models\BellTiming;
use App\Models\BellTimingTemplate;
use App\Models\BellTimingTemplateSlot;
use App\Models\TimetableSlot;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Owns every Bell Timing Template operation: create/duplicate/edit,
 * comparing a template's structure against a class's existing schedule,
 * and safely applying a template to one or many classes.
 *
 * Core safety rule (see class docblocks on BellTimingTemplate/Slot for the
 * "why"): applying a template NEVER deletes a BellTiming row that a live
 * TimetableSlot references. Where an existing row's structure position
 * still exists in the new desired structure, it is UPDATED IN PLACE
 * (same id preserved -- zero FK/cascade risk). Only genuinely excess rows
 * (positions the new structure no longer needs) are ever deleted, and only
 * after confirming no TimetableSlot references them; if any do, the whole
 * apply is aborted before anything is written (see applyToClasses()).
 */
class BellTimingTemplateService
{
    /**
     * Build a template from an explicit list of slot definitions
     * (period_name, start_time, end_time, is_break, period_type,
     * order_index, custom_label, color_code -- all HH:mm normalized
     * already by the time this is called, exactly like bulkCreate()).
     */
    public function create(array $attributes, array $slots, User $user): BellTimingTemplate
    {
        return DB::transaction(function () use ($attributes, $slots, $user) {
            $template = BellTimingTemplate::create([
                'name' => $attributes['name'],
                'description' => $attributes['description'] ?? null,
                'academic_year' => $attributes['academic_year'] ?? null,
                'semester' => $attributes['semester'] ?? null,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            $this->replaceSlots($template, $slots);

            return $template->fresh('slots');
        });
    }

    public function update(BellTimingTemplate $template, array $attributes, array $slots, User $user): BellTimingTemplate
    {
        return DB::transaction(function () use ($template, $attributes, $slots, $user) {
            $template->update([
                'name' => $attributes['name'],
                'description' => $attributes['description'] ?? null,
                'academic_year' => $attributes['academic_year'] ?? null,
                'semester' => $attributes['semester'] ?? null,
                'updated_by' => $user->id,
            ]);

            $this->replaceSlots($template, $slots);

            return $template->fresh('slots');
        });
    }

    public function duplicate(BellTimingTemplate $template, string $newName, User $user): BellTimingTemplate
    {
        return DB::transaction(function () use ($template, $newName, $user) {
            $copy = BellTimingTemplate::create([
                'name' => $newName,
                'description' => $template->description,
                'academic_year' => $template->academic_year,
                'semester' => $template->semester,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            foreach ($template->slots as $slot) {
                BellTimingTemplateSlot::create([
                    'bell_timing_template_id' => $copy->id,
                    'period_name' => $slot->period_name,
                    'start_time' => $slot->start_time->format('H:i:s'),
                    'end_time' => $slot->end_time->format('H:i:s'),
                    'is_break' => $slot->is_break,
                    'period_type' => $slot->period_type,
                    'order_index' => $slot->order_index,
                    'custom_label' => $slot->custom_label,
                    'color_code' => $slot->color_code,
                ]);
            }

            return $copy->fresh('slots');
        });
    }

    /**
     * "Save as Template": snapshot one class's existing active schedule for
     * one day into a brand-new template. Read-only against BellTiming --
     * never modifies the source class's own rows.
     */
    public function createFromExistingClass(string $classSection, string $day, array $attributes, User $user): BellTimingTemplate
    {
        $existing = BellTiming::active()
            ->byClass($classSection)
            ->byDay($day)
            ->orderBy('order_index')
            ->get();

        if ($existing->isEmpty()) {
            throw new RuntimeException("No active Bell Timing found for {$classSection} on {$day}.");
        }

        $slots = $existing->map(fn (BellTiming $t) => [
            'period_name' => $t->period_name,
            'start_time' => $t->start_time->format('H:i:s'),
            'end_time' => $t->end_time->format('H:i:s'),
            'is_break' => $t->is_break,
            'period_type' => $t->period_type,
            'order_index' => $t->order_index,
            'custom_label' => $t->custom_label,
            'color_code' => $t->color_code,
        ])->all();

        return $this->create($attributes, $slots, $user);
    }

    /**
     * Compares a template's structure to one class's existing active
     * schedule for one day. "Same structure" per the approved spec: same
     * slot COUNT, same ORDER, same is_break pattern, same period_type
     * pattern -- start/end times are deliberately NOT compared here.
     */
    public function compareStructure(BellTimingTemplate $template, string $classSection, string $day): array
    {
        $existing = BellTiming::active()
            ->byClass($classSection)
            ->byDay($day)
            ->orderBy('order_index')
            ->get();

        $templateSlots = $template->slots;

        if ($existing->isEmpty()) {
            return [
                'status' => 'none',
                'existing_count' => 0,
                'template_count' => $templateSlots->count(),
                'existing' => [],
            ];
        }

        $same = $existing->count() === $templateSlots->count()
            && $existing->values()->zip($templateSlots->values())->every(function ($pair) {
                [$e, $t] = $pair;

                return (bool) $e->is_break === (bool) $t->is_break
                    && $e->period_type === $t->period_type;
            });

        return [
            'status' => $same ? 'same' : 'different',
            'existing_count' => $existing->count(),
            'template_count' => $templateSlots->count(),
            'existing' => $existing->map(fn (BellTiming $t) => [
                'id' => $t->id,
                'period_name' => $t->period_name,
                'start_time' => $t->start_time->format('H:i'),
                'end_time' => $t->end_time->format('H:i'),
                'is_break' => $t->is_break,
                'period_type' => $t->period_type,
                'order_index' => $t->order_index,
            ])->values()->all(),
        ];
    }

    /**
     * Applies a template to multiple classes across the given days, one
     * explicit decision per class:
     *   ['action' => 'apply']                        -- no existing schedule, or same structure with no changes wanted
     *   ['action' => 'replace']                       -- same structure, admin confirmed replacing it
     *   ['action' => 'customize', 'slots' => [...]]   -- different structure, admin-edited slot list
     *   ['action' => 'copy_matching']                 -- different structure, copy only the overlapping positions
     *   ['action' => 'skip']                          -- leave this class untouched entirely
     *
     * The whole operation is one transaction: every class is validated
     * (structure re-check, FK-safety check, time-overlap check) before any
     * write happens. A 'skip' decision excludes a class from validation
     * entirely (by design -- it's not being touched). Any other class that
     * fails validation aborts the ENTIRE transaction (all-or-nothing),
     * exactly as instructed -- partial application is never left behind.
     *
     * Returns a per-class result summary; throws (rolling back) on any
     * failure with a message identifying the exact class/reason.
     */
    public function applyToClasses(
        BellTimingTemplate $template,
        array $days,
        array $classDecisions,
        ?string $academicYear,
        ?string $semester,
        User $user
    ): array {
        return DB::transaction(function () use ($template, $days, $classDecisions, $academicYear, $semester, $user) {
            $results = [];

            foreach ($classDecisions as $classSection => $decision) {
                $action = $decision['action'] ?? 'skip';

                if ($action === 'skip') {
                    $results[$classSection] = ['action' => 'skip', 'days' => []];
                    continue;
                }

                $desiredSlots = $this->resolveDesiredSlots($template, $classSection, $days[0], $action, $decision['slots'] ?? null);

                foreach ($days as $day) {
                    $results[$classSection]['days'][$day] = $this->applyToClassDay(
                        $classSection,
                        $day,
                        $desiredSlots,
                        $academicYear,
                        $semester,
                        $user,
                        $action
                    );
                }

                $results[$classSection]['action'] = $action;
            }

            return $results;
        });
    }

    /**
     * Resolves the final ordered slot list to apply for one class, given
     * the admin's chosen action. 'copy_matching' truncates the template to
     * however many slots the class's CURRENT (first target day's) schedule
     * already has, in order -- a deliberately simple, predictable rule.
     */
    private function resolveDesiredSlots(BellTimingTemplate $template, string $classSection, string $firstDay, string $action, ?array $customSlots): array
    {
        $templateSlots = $template->slots->map(fn (BellTimingTemplateSlot $s) => [
            'period_name' => $s->period_name,
            'start_time' => $s->start_time->format('H:i:s'),
            'end_time' => $s->end_time->format('H:i:s'),
            'is_break' => $s->is_break,
            'period_type' => $s->period_type,
            'order_index' => $s->order_index,
            'custom_label' => $s->custom_label,
            'color_code' => $s->color_code,
        ])->values()->all();

        if ($action === 'customize') {
            if (empty($customSlots)) {
                throw new RuntimeException("Customize was chosen for {$classSection} but no slots were provided.");
            }

            return array_values($customSlots);
        }

        if ($action === 'copy_matching') {
            $existingCount = BellTiming::active()->byClass($classSection)->byDay($firstDay)->count();

            if ($existingCount === 0) {
                throw new RuntimeException("Copy Matching Slots was chosen for {$classSection} but it has no existing schedule to match.");
            }

            return array_slice($templateSlots, 0, $existingCount);
        }

        // 'apply' or 'replace' -- use the template exactly as-is.
        return $templateSlots;
    }

    /**
     * Applies the resolved slot list to one class, one day. Existing
     * active rows for that class/day are matched to the desired slots
     * position-by-position (by order_index): overlapping positions are
     * UPDATED IN PLACE (id preserved, zero FK risk); genuinely new
     * positions are INSERTED; genuinely excess existing positions are
     * DELETED only after confirming no TimetableSlot references them.
     */
    private function applyToClassDay(string $classSection, string $day, array $desiredSlots, ?string $academicYear, ?string $semester, User $user, string $action): array
    {
        $existing = BellTiming::active()
            ->byClass($classSection)
            ->byDay($day)
            ->orderBy('order_index')
            ->get()
            ->values();

        // 'apply' means the admin was shown (and accepted) "no existing
        // schedule for this class." If that's no longer true by the time we
        // actually write -- someone else created a row in between, or the
        // decision was wrong -- refuse rather than silently reconciling an
        // unrelated row into the new structure. This is the "never blindly
        // overwrite" rule applied to the safe-looking 'apply' path too, not
        // just 'replace'/'customize'/'copy_matching'.
        if ($action === 'apply' && $existing->isNotEmpty()) {
            throw new RuntimeException(
                "Cannot apply to {$classSection} on {$day}: it already has an existing schedule that wasn't reviewed. " .
                'Go back and choose Replace, Customize, Copy Matching Slots, or Skip for this class.'
            );
        }

        $desiredCount = count($desiredSlots);
        $existingCount = $existing->count();
        $overlapCount = min($desiredCount, $existingCount);

        // Excess existing rows this new structure no longer needs.
        if ($existingCount > $desiredCount) {
            $excessIds = $existing->slice($desiredCount)->pluck('id')->all();

            if (TimetableSlot::whereIn('bell_timing_id', $excessIds)->exists()) {
                throw new RuntimeException(
                    "Cannot apply to {$classSection} on {$day}: an existing period is used by a live timetable and cannot be removed. " .
                    'Remove or regenerate that timetable first, or choose Skip for this class.'
                );
            }
        }

        // Time-overlap pre-check against OTHER bell timings for this class/day
        // not part of what we're about to update/delete -- same rule bulkCreate() uses.
        $touchedIds = $existing->take($existingCount)->pluck('id')->all();
        foreach ($desiredSlots as $slot) {
            $conflict = BellTiming::where('day_of_week', $day)
                ->where('class_section', $classSection)
                ->where('start_time', '<', $slot['end_time'])
                ->where('end_time', '>', $slot['start_time'])
                ->where('is_active', true)
                ->whereNotIn('id', $touchedIds)
                ->exists();

            if ($conflict) {
                throw new RuntimeException(
                    "Cannot apply to {$classSection} on {$day}: \"{$slot['period_name']}\" ({$slot['start_time']}-{$slot['end_time']}) " .
                    'overlaps an existing schedule entry not part of this template.'
                );
            }
        }

        $createdIds = [];
        $updatedIds = [];

        for ($i = 0; $i < $overlapCount; $i++) {
            $row = $existing[$i];
            $row->update($this->slotAttributes($desiredSlots[$i], $i, $day, $classSection, $academicYear, $semester, $user, false));
            $updatedIds[] = $row->id;
        }

        for ($i = $overlapCount; $i < $desiredCount; $i++) {
            $created = BellTiming::create($this->slotAttributes($desiredSlots[$i], $i, $day, $classSection, $academicYear, $semester, $user, true));
            $createdIds[] = $created->id;
        }

        if ($existingCount > $desiredCount) {
            BellTiming::whereIn('id', $existing->slice($desiredCount)->pluck('id')->all())->delete();
        }

        return [
            'created' => $createdIds,
            'updated' => $updatedIds,
            'deleted' => $existingCount > $desiredCount ? $existing->slice($desiredCount)->pluck('id')->all() : [],
        ];
    }

    private function slotAttributes(array $slot, int $orderIndex, string $day, string $classSection, ?string $academicYear, ?string $semester, User $user, bool $isNew): array
    {
        $attrs = [
            'day_of_week' => $day,
            'period_name' => $slot['period_name'],
            'start_time' => $slot['start_time'],
            'end_time' => $slot['end_time'],
            'class_section' => $classSection,
            'is_active' => true,
            'is_break' => (bool) ($slot['is_break'] ?? false),
            'period_type' => $slot['period_type'] ?? BellTiming::PERIOD_TYPE_TEACHING,
            'order_index' => $orderIndex,
            'academic_year' => $academicYear,
            'semester' => $semester,
            'custom_label' => $slot['custom_label'] ?? null,
            'color_code' => $slot['color_code'] ?? '#007bff',
        ];

        if ($isNew) {
            // `updated_by` exists on the bell_timings table but is
            // deliberately NOT in BellTiming::$fillable (confirmed by
            // reading the model) -- mirroring that existing behavior here
            // rather than reaching around mass-assignment protection.
            $attrs['created_by'] = $user->id;
        }

        return $attrs;
    }

    private function replaceSlots(BellTimingTemplate $template, array $slots): void
    {
        $template->slots()->delete();

        foreach (array_values($slots) as $index => $slot) {
            BellTimingTemplateSlot::create([
                'bell_timing_template_id' => $template->id,
                'period_name' => $slot['period_name'],
                'start_time' => $slot['start_time'],
                'end_time' => $slot['end_time'],
                'is_break' => (bool) ($slot['is_break'] ?? false),
                'period_type' => $slot['period_type'] ?? BellTiming::PERIOD_TYPE_TEACHING,
                'order_index' => $slot['order_index'] ?? $index,
                'custom_label' => $slot['custom_label'] ?? null,
                'color_code' => $slot['color_code'] ?? '#007bff',
            ]);
        }
    }
}

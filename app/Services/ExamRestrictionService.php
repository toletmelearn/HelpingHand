<?php

namespace App\Services;

use App\Models\AdmitCard;
use App\Models\DefaulterExamOverride;
use App\Models\DefaulterStage;
use Illuminate\Support\Facades\DB;

/**
 * Keeps a defaulting student's admit cards in sync with their stage on the
 * Defaulter Registry ladder. Mirrors the same "in_array on named stages"
 * check already used for Result Hold / TC Hold (StudentResultController,
 * AdmitCard::validateForGeneration) rather than introducing a different
 * abstraction for this one stage.
 */
class ExamRestrictionService
{
    public const RESTRICTED_STAGES = ['Exam Restriction', 'Result Hold', 'TC Hold'];

    public static function hasActiveOverride(int $studentId): bool
    {
        return DefaulterExamOverride::where('student_id', $studentId)
            ->whereNull('revoked_at')
            ->exists();
    }

    public static function isRestricted(int $studentId): bool
    {
        $stage = DefaulterStage::where('student_id', $studentId)->value('stage');

        if (!$stage || !in_array($stage, self::RESTRICTED_STAGES, true)) {
            return false;
        }

        return !self::hasActiveOverride($studentId);
    }

    /**
     * Revokes published/locked admit cards for a restricted student, or
     * restores previously-revoked ones once they're no longer restricted
     * (cleared their stage, or were granted an override). Called after
     * every stage change so admit card status never drifts from it.
     */
    public static function syncAdmitCardsForStudent(int $studentId, ?int $actingUserId = null): void
    {
        $restricted = self::isRestricted($studentId);

        if ($restricted) {
            AdmitCard::where('student_id', $studentId)
                ->whereIn('status', ['published', 'locked'])
                ->get()
                ->each(fn (AdmitCard $card) => $card->transitionTo('revoked', $actingUserId));
        } else {
            AdmitCard::where('student_id', $studentId)
                ->where('status', 'revoked')
                ->get()
                ->each(fn (AdmitCard $card) => $card->transitionTo('published', $actingUserId));
        }
    }

    /**
     * Admin/Principal/Accountant exception: lets the student sit the exam
     * (restores any revoked admit card, and unblocks new-card generation
     * via AdmitCard::validateForGeneration()) despite still owing fees.
     */
    public static function grantOverride(int $studentId, int $grantedBy, ?string $reason = null): DefaulterExamOverride
    {
        return DB::transaction(function () use ($studentId, $grantedBy, $reason) {
            // Only one active override per student -- close out any stale row first.
            DefaulterExamOverride::where('student_id', $studentId)
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now(), 'revoked_by' => $grantedBy]);

            $override = DefaulterExamOverride::create([
                'student_id' => $studentId,
                'granted_by' => $grantedBy,
                'reason' => $reason,
                'granted_at' => now(),
            ]);

            self::syncAdmitCardsForStudent($studentId, $grantedBy);

            return $override;
        });
    }

    public static function revokeOverride(int $studentId, int $revokedBy): void
    {
        DB::transaction(function () use ($studentId, $revokedBy) {
            DefaulterExamOverride::where('student_id', $studentId)
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now(), 'revoked_by' => $revokedBy]);

            self::syncAdmitCardsForStudent($studentId, $revokedBy);
        });
    }
}

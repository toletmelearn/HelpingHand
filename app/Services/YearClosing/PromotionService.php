<?php

namespace App\Services\YearClosing;

use App\Models\StudentPromotionLog;

class PromotionService
{
    /**
     * Get the promotion log for a student.
     */
    public function getLatestPromotionLog(int $studentId)
    {
        return StudentPromotionLog::where('student_id', $studentId)
            ->orderBy('id', 'desc')
            ->first();
    }

    /**
     * Log student promotion status.
     */
    public function logPromotion(int $studentId, int $academicSessionId, string $fromClass, string $toClass, int $userId, ?string $remarks = null): StudentPromotionLog
    {
        return StudentPromotionLog::create([
            'student_id' => $studentId,
            'academic_session_id' => $academicSessionId,
            'from_class' => $fromClass,
            'to_class' => $toClass,
            'promoted_by' => $userId,
            'promoted_at' => now(),
            'remarks' => $remarks,
        ]);
    }
}

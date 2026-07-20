<?php

namespace App\Observers;

use App\Models\Student;
use App\Services\StructureAdjustmentService;

class StudentObserver
{
    /**
     * Handle the Student "deleted" event (soft delete is treated as withdrawal).
     */
    public function deleted(Student $student): void
    {
        // Drop future dues when student is soft-deleted
        $adjustmentService = new StructureAdjustmentService();
        $adjustmentService->withdrawStudent($student, today()->toDateString());
    }
}

<?php

namespace App\Services\Attendance;

use App\Models\Student;

class AttendanceClassResolver
{
    public const CONFLICT_MESSAGE = 'Student class data has a conflict. Attendance cannot be marked until class data is resolved.';
    public const UNRESOLVED_MESSAGE = 'Student class could not be resolved. Attendance cannot be marked.';

    public function resolveForStudent(Student $student): array
    {
        if ($student->hasClassIdConflict()) {
            return [
                'ok' => false,
                'class' => null,
                'source' => 'conflict',
                'status' => 409,
                'message' => self::CONFLICT_MESSAGE,
            ];
        }

        $schoolClass = $student->resolveCanonicalSchoolClass();

        if ($schoolClass) {
            return [
                'ok' => true,
                'class' => $schoolClass->name,
                'source' => 'canonical',
                'status' => 200,
                'message' => null,
            ];
        }

        if ($student->class) {
            return [
                'ok' => true,
                'class' => $student->class,
                'source' => 'legacy',
                'status' => 200,
                'message' => null,
            ];
        }

        return [
            'ok' => false,
            'class' => null,
            'source' => 'unresolved',
            'status' => 422,
            'message' => self::UNRESOLVED_MESSAGE,
        ];
    }
}

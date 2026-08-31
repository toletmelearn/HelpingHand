<?php

namespace Tests\Feature\Admin;

use App\Models\Exam;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherClassSubjectAssignment;
use App\Models\TeacherLogin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Sync-audit loophole L-04: TeacherMarksController authorized marks entry
 * by matching exam.class_name/exam.subject STRINGS against
 * TeacherClassSubjectAssignment, instead of the exam's own enforced
 * class_id/subject_id FKs. school_classes.name carries no unique
 * constraint, so two class rows can legitimately share a name (this
 * codebase already hit exactly this with the legacy ClassManagement
 * table). Proves the FK-based fix distinguishes two same-named classes
 * correctly, where the old string-match would not have.
 */
class TeacherMarksClassNameCollisionTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_assigned_to_one_of_two_same_named_classes_cannot_access_the_others_exam(): void
    {
        $subject = Subject::create(['name' => 'Maths', 'code' => 'MTH' . uniqid()]);

        // Two distinct SchoolClass rows sharing the exact same name --
        // legitimate under the current schema (no unique constraint).
        $classA = SchoolClass::create(['name' => 'Class 11', 'class_order' => 11, 'is_active' => true]);
        $classB = SchoolClass::create(['name' => 'Class 11', 'class_order' => 12, 'is_active' => true]);

        $teacher = Teacher::create(['name' => 'Assigned Teacher']);
        $login = TeacherLogin::create([
            'teacher_id' => $teacher->id, 'username' => 'collision' . uniqid(), 'password' => Hash::make('secret123'),
        ]);

        // Teacher is assigned to classA only.
        TeacherClassSubjectAssignment::create([
            'teacher_id' => $teacher->id, 'class_id' => $classA->id, 'subject_id' => $subject->id,
            'is_class_teacher' => false,
        ]);

        // The exam actually belongs to classB -- a different row that
        // happens to share the same name.
        $examForClassB = Exam::create([
            'name' => 'Term Exam', 'exam_type' => 'term', 'class_id' => $classB->id,
            'class_name' => $classB->name, 'subject_id' => $subject->id, 'subject' => $subject->name,
            'exam_date' => today()->addDays(5), 'start_time' => '10:00', 'end_time' => '12:00',
            'total_marks' => 100, 'passing_marks' => 33,
            'academic_year' => '2026-27', 'status' => 'active',
        ]);

        $response = $this->actingAs($login, 'teacher')
            ->get(route('teacher.marks.show', $examForClassB->id));

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function test_teacher_assigned_to_the_matching_class_id_can_access_its_exam(): void
    {
        $subject = Subject::create(['name' => 'Maths', 'code' => 'MTH' . uniqid()]);
        $classA = SchoolClass::create(['name' => 'Class 11', 'class_order' => 11, 'is_active' => true]);
        SchoolClass::create(['name' => 'Class 11', 'class_order' => 12, 'is_active' => true]);

        $teacher = Teacher::create(['name' => 'Assigned Teacher']);
        $login = TeacherLogin::create([
            'teacher_id' => $teacher->id, 'username' => 'match' . uniqid(), 'password' => Hash::make('secret123'),
        ]);

        TeacherClassSubjectAssignment::create([
            'teacher_id' => $teacher->id, 'class_id' => $classA->id, 'subject_id' => $subject->id,
            'is_class_teacher' => false,
        ]);

        $examForClassA = Exam::create([
            'name' => 'Term Exam', 'exam_type' => 'term', 'class_id' => $classA->id,
            'class_name' => $classA->name, 'subject_id' => $subject->id, 'subject' => $subject->name,
            'exam_date' => today()->addDays(5), 'start_time' => '10:00', 'end_time' => '12:00',
            'total_marks' => 100, 'passing_marks' => 33,
            'academic_year' => '2026-27', 'status' => 'active',
        ]);

        $response = $this->actingAs($login, 'teacher')
            ->get(route('teacher.marks.show', $examForClassA->id));

        $response->assertOk();
        $response->assertSessionMissing('error');
    }
}

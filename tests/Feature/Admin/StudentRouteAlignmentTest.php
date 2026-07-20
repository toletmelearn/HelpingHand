<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;

class StudentRouteAlignmentTest extends TestCase
{
    public function test_admin_facing_class_teacher_add_student_link_uses_admin_students_create(): void
    {
        $view = file_get_contents(resource_path('views/admin/class-teacher-control/student-records.blade.php'));

        $this->assertStringContainsString("route('admin.students.create')", $view);
        $this->assertStringNotContainsString("route('students.create')", $view);
    }

    public function test_legacy_students_create_redirects_to_admin_students_create(): void
    {
        $this->withoutMiddleware();

        $this->get('/students/create')
            ->assertRedirect(route('admin.students.create'));
    }
}

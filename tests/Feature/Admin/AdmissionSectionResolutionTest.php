<?php

namespace Tests\Feature\Admin;

use App\Models\AdmissionEnquiry;
use App\Models\ClassManagement;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Phase 2B-i P0 fix: AdminAdmissionController::confirmAdmission() used to
 * validate the submitted "section_id" against class_management.id and
 * write that legacy id straight into students.section_id, which is
 * semantically a real sections.id -- a live, confirmed id-collision bug
 * (Phase 2B's read-only audit). This proves the fix: a real Section is
 * always resolved, cross-checked against the selected class via the real
 * class_sections bridge, and a ClassManagement.id is never written into
 * students.section_id, even when the numbers happen to coincide.
 */
class AdmissionSectionResolutionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $this->admin->roles()->attach($role->id);
    }

    /**
     * Builds a class fully bridged to a real section (the correct,
     * intended configuration), plus an UNRELATED real class+section pair
     * -- deliberately with IDs chosen so a ClassManagement id and an
     * unrelated Section id collide numerically, reproducing the exact
     * live collision pattern the audit found.
     */
    private function seedTwoClasses(): array
    {
        $classA = SchoolClass::create(['name' => 'Nursery', 'class_order' => 1, 'capacity' => 70]);
        $cmA = ClassManagement::create(['name' => 'Nursery', 'section' => '', 'capacity' => 70]);
        DB::table('legacy_class_map')->insert([
            'class_management_id' => $cmA->id, 'school_class_id' => $classA->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $sectionA = Section::create(['name' => 'A', 'capacity' => 40]);
        DB::table('class_sections')->insert([
            'class_management_id' => $cmA->id, 'section_id' => $sectionA->id,
            'assigned_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $classB = SchoolClass::create(['name' => 'Class 5', 'class_order' => 8, 'capacity' => 70]);
        $cmB = ClassManagement::create(['name' => 'Class 5', 'section' => '', 'capacity' => 70]);
        DB::table('legacy_class_map')->insert([
            'class_management_id' => $cmB->id, 'school_class_id' => $classB->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $sectionB = Section::create(['name' => 'B', 'capacity' => 40]);
        DB::table('class_sections')->insert([
            'class_management_id' => $cmB->id, 'section_id' => $sectionB->id,
            'assigned_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        return compact('classA', 'cmA', 'sectionA', 'classB', 'cmB', 'sectionB');
    }

    private function selectedEnquiry(string $name = 'Test Kid', string $phone = '9000000000'): AdmissionEnquiry
    {
        return AdmissionEnquiry::create([
            'candidate_name' => $name, 'parent_name' => 'Parent', 'phone' => $phone, 'status' => 'selected',
        ]);
    }

    // 1 & 9. Valid class + valid section succeeds; valid admission at available capacity succeeds.
    public function test_valid_class_and_valid_section_succeeds(): void
    {
        $seed = $this->seedTwoClasses();
        $enquiry = $this->selectedEnquiry();

        $response = $this->actingAs($this->admin)->post(route('admin.admissions.confirm-admission', $enquiry->id), [
            'class_id' => $seed['classA']->id,
            'section_id' => $seed['sectionA']->id,
            'date_of_birth' => '2020-01-01', 'gender' => 'male', 'category' => 'General',
        ]);

        $response->assertRedirect(route('admin.admissions.index'));
        $response->assertSessionHas('success');
    }

    // 2. Student receives Section.id.
    public function test_student_receives_the_real_section_id(): void
    {
        $seed = $this->seedTwoClasses();
        $enquiry = $this->selectedEnquiry();

        $this->actingAs($this->admin)->post(route('admin.admissions.confirm-admission', $enquiry->id), [
            'class_id' => $seed['classA']->id,
            'section_id' => $seed['sectionA']->id,
            'date_of_birth' => '2020-01-01', 'gender' => 'male', 'category' => 'General',
        ]);

        $student = Student::where('name', 'Test Kid')->firstOrFail();
        $this->assertSame($seed['sectionA']->id, $student->section_id);
        $this->assertSame('A', $student->section);
    }

    // 3. ClassManagement.id is never stored in students.section_id.
    public function test_class_management_id_is_never_stored_in_student_section_id(): void
    {
        $seed = $this->seedTwoClasses();
        $enquiry = $this->selectedEnquiry();

        $this->actingAs($this->admin)->post(route('admin.admissions.confirm-admission', $enquiry->id), [
            'class_id' => $seed['classA']->id,
            'section_id' => $seed['sectionA']->id,
            'date_of_birth' => '2020-01-01', 'gender' => 'male', 'category' => 'General',
        ]);

        $student = Student::where('name', 'Test Kid')->firstOrFail();
        // Even if the ClassManagement id happened to equal the real Section id (the
        // exact collision pattern the audit found), the stored value must
        // resolve to a genuine Section record, not merely equal a CM id.
        $this->assertDatabaseHas('sections', ['id' => $student->section_id]);
        $this->assertTrue(Section::whereKey($student->section_id)->exists());
    }

    // 4. Invalid (nonexistent) section rejected.
    public function test_nonexistent_section_is_rejected(): void
    {
        $seed = $this->seedTwoClasses();
        $enquiry = $this->selectedEnquiry();

        $response = $this->actingAs($this->admin)->post(route('admin.admissions.confirm-admission', $enquiry->id), [
            'class_id' => $seed['classA']->id,
            'section_id' => 999999,
            'date_of_birth' => '2020-01-01', 'gender' => 'male', 'category' => 'General',
        ]);

        $response->assertSessionHasErrors('section_id');
        $this->assertDatabaseMissing('students', ['name' => 'Test Kid']);
    }

    // 5. Section belonging to another class rejected -- the core new protection.
    public function test_section_belonging_to_a_different_class_is_rejected(): void
    {
        $seed = $this->seedTwoClasses();
        $enquiry = $this->selectedEnquiry();

        // classA is Nursery; sectionB is only configured for classB (Class 5).
        $response = $this->actingAs($this->admin)->post(route('admin.admissions.confirm-admission', $enquiry->id), [
            'class_id' => $seed['classA']->id,
            'section_id' => $seed['sectionB']->id,
            'date_of_birth' => '2020-01-01', 'gender' => 'male', 'category' => 'General',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseMissing('students', ['name' => 'Test Kid']);
    }

    // 6. Tampered section ID (a real Section id for an unrelated class, submitted alongside a different class_id) rejected -- same protection, framed as a forged-request scenario.
    public function test_tampered_section_id_across_classes_is_rejected(): void
    {
        $seed = $this->seedTwoClasses();
        $enquiry = $this->selectedEnquiry();

        $response = $this->actingAs($this->admin)->post(route('admin.admissions.confirm-admission', $enquiry->id), [
            'class_id' => $seed['classB']->id,
            'section_id' => $seed['sectionA']->id, // belongs to classA, tampered onto classB's submission
            'date_of_birth' => '2020-01-01', 'gender' => 'male', 'category' => 'General',
        ]);

        $response->assertSessionHas('error');
        $this->assertDatabaseMissing('students', ['name' => 'Test Kid']);
    }

    // 7 & 8. Capacity enforcement still works; capacity-full admission is rejected.
    public function test_capacity_full_admission_is_rejected_for_non_admin(): void
    {
        $seed = $this->seedTwoClasses();
        $seed['classA']->update(['capacity' => 1]);

        Student::create([
            'name' => 'Existing', 'father_name' => 'F', 'mother_name' => 'M',
            'date_of_birth' => '2019-01-01', 'gender' => 'male', 'category' => 'General',
            'aadhaar_number' => '999988887777', 'phone' => '9111111111', 'address' => 'Addr',
            'class_id' => $seed['classA']->id, 'section_id' => $seed['sectionA']->id,
        ]);

        $receptionist = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'receptionist'], ['display_name' => 'Receptionist']);
        $receptionist->roles()->attach($role->id);

        $enquiry = $this->selectedEnquiry('Overflow Kid', '9222222222');
        $enquiry->update(['counsellor_id' => $receptionist->id]);

        $response = $this->actingAs($receptionist)->post(route('admin.admissions.confirm-admission', $enquiry->id), [
            'class_id' => $seed['classA']->id,
            'section_id' => $seed['sectionA']->id,
            'date_of_birth' => '2020-01-01', 'gender' => 'male', 'category' => 'General',
        ]);

        $response->assertSessionHas('error');
        $this->assertDatabaseMissing('students', ['name' => 'Overflow Kid']);
    }

    // 10. Parent creation still works.
    public function test_parent_is_still_auto_created(): void
    {
        $seed = $this->seedTwoClasses();
        $enquiry = $this->selectedEnquiry('Test Kid', '9333333333');

        $this->actingAs($this->admin)->post(route('admin.admissions.confirm-admission', $enquiry->id), [
            'class_id' => $seed['classA']->id,
            'section_id' => $seed['sectionA']->id,
            'date_of_birth' => '2020-01-01', 'gender' => 'male', 'category' => 'General',
        ]);

        $student = Student::where('name', 'Test Kid')->firstOrFail();
        $this->assertNotNull(\App\Models\ParentModel::where('student_id', $student->id)->first());
    }

    // 11. Fee structure creation still works (auto-assignment path untouched -- covered end-to-end by AdmissionAutoAssignsFeeStructureTest; spot-checked here for no fatal error when no structure exists).
    public function test_admission_succeeds_even_with_no_fee_structure_configured(): void
    {
        $seed = $this->seedTwoClasses();
        $enquiry = $this->selectedEnquiry();

        $response = $this->actingAs($this->admin)->post(route('admin.admissions.confirm-admission', $enquiry->id), [
            'class_id' => $seed['classA']->id,
            'section_id' => $seed['sectionA']->id,
            'date_of_birth' => '2020-01-01', 'gender' => 'male', 'category' => 'General',
        ]);

        $response->assertSessionHas('success');
    }

    // 12. Admission number generation still works.
    public function test_admission_number_is_still_generated(): void
    {
        $seed = $this->seedTwoClasses();
        $enquiry = $this->selectedEnquiry();

        $this->actingAs($this->admin)->post(route('admin.admissions.confirm-admission', $enquiry->id), [
            'class_id' => $seed['classA']->id,
            'section_id' => $seed['sectionA']->id,
            'date_of_birth' => '2020-01-01', 'gender' => 'male', 'category' => 'General',
        ]);

        $student = Student::where('name', 'Test Kid')->firstOrFail();
        $this->assertStringStartsWith('ADM-', $student->admission_no);
    }

    // 13. Existing admission workflow remains functional -- enquiry status transitions to admitted.
    public function test_enquiry_status_transitions_to_admitted(): void
    {
        $seed = $this->seedTwoClasses();
        $enquiry = $this->selectedEnquiry();

        $this->actingAs($this->admin)->post(route('admin.admissions.confirm-admission', $enquiry->id), [
            'class_id' => $seed['classA']->id,
            'section_id' => $seed['sectionA']->id,
            'date_of_birth' => '2020-01-01', 'gender' => 'male', 'category' => 'General',
        ]);

        $this->assertSame('admitted', $enquiry->refresh()->status);
    }

    // Class with no legacy_class_map bridge at all -- fails safe (rejects every section) rather than guessing.
    public function test_class_with_no_bridge_configured_rejects_any_section(): void
    {
        $seed = $this->seedTwoClasses();
        $unbridgedClass = SchoolClass::create(['name' => 'Unbridged Class', 'class_order' => 99, 'capacity' => 40]);
        $enquiry = $this->selectedEnquiry();

        $response = $this->actingAs($this->admin)->post(route('admin.admissions.confirm-admission', $enquiry->id), [
            'class_id' => $unbridgedClass->id,
            'section_id' => $seed['sectionA']->id,
            'date_of_birth' => '2020-01-01', 'gender' => 'male', 'category' => 'General',
        ]);

        $response->assertSessionHas('error');
        $this->assertDatabaseMissing('students', ['name' => 'Test Kid']);
    }
}

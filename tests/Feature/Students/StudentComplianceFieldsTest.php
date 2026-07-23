<?php

namespace Tests\Feature\Students;

use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentComplianceFieldsTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $user->roles()->attach($role->id);

        return $user;
    }

    private function baseStudentAttributes(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Test Student',
            'father_name' => 'Test Father',
            'mother_name' => 'Test Mother',
            'date_of_birth' => '2015-01-01',
            'aadhaar_number' => (string) random_int(100000000000, 999999999999),
            'address' => 'Somewhere',
            'phone' => '9999999999',
            'mobile' => '9876543210',
            'gender' => 'male',
            'category' => 'General',
            'class' => 'Class 5',
        ], $overrides);
    }

    // --- Validation ---

    public function test_apaar_id_must_be_exactly_12_digits_when_present(): void
    {
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)->post(route('admin.students.store'), array_merge(
            $this->baseStudentAttributes(),
            ['apaar_id' => '12345']
        ));

        $response->assertSessionHasErrors('apaar_id');
        $this->assertDatabaseMissing('students', ['name' => 'Test Student']);
    }

    public function test_apaar_id_is_accepted_when_exactly_12_digits(): void
    {
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)->post(route('admin.students.store'), array_merge(
            $this->baseStudentAttributes(),
            ['apaar_id' => '123456789012']
        ));

        $response->assertSessionDoesntHaveErrors('apaar_id');
        $this->assertDatabaseHas('students', ['name' => 'Test Student', 'apaar_id' => '123456789012']);
    }

    public function test_aadhaar_number_must_be_exactly_12_digits(): void
    {
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)->post(route('admin.students.store'), array_merge(
            $this->baseStudentAttributes(),
            ['aadhaar_number' => '123']
        ));

        $response->assertSessionHasErrors('aadhaar_number');
    }

    public function test_udise_pen_and_name_as_per_aadhaar_are_optional_and_saved(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->post(route('admin.students.store'), array_merge(
            $this->baseStudentAttributes(),
            ['udise_pen' => 'UDISE-12345', 'name_as_per_aadhaar' => 'Test Student Aadhaar']
        ));

        $this->assertDatabaseHas('students', [
            'name' => 'Test Student',
            'udise_pen' => 'UDISE-12345',
            'name_as_per_aadhaar' => 'Test Student Aadhaar',
        ]);
    }

    // --- Mismatch detector ---

    public function test_has_aadhaar_name_mismatch_is_false_when_name_as_per_aadhaar_is_unset(): void
    {
        $student = Student::create($this->baseStudentAttributes(['name' => 'Ravi Kumar']));

        $this->assertFalse($student->hasAadhaarNameMismatch());
    }

    public function test_has_aadhaar_name_mismatch_is_false_for_case_and_whitespace_variants(): void
    {
        $student = Student::create($this->baseStudentAttributes([
            'name' => '  Ravi Kumar  ',
            'name_as_per_aadhaar' => 'ravi kumar',
        ]));

        $this->assertFalse($student->hasAadhaarNameMismatch());
    }

    public function test_has_aadhaar_name_mismatch_is_true_for_genuinely_different_names(): void
    {
        $student = Student::create($this->baseStudentAttributes([
            'name' => 'Ravi Kumar',
            'name_as_per_aadhaar' => 'Ravindra Kumar',
        ]));

        $this->assertTrue($student->hasAadhaarNameMismatch());
    }

    public function test_admin_students_index_filters_to_aadhaar_mismatches_only(): void
    {
        $admin = $this->makeAdmin();

        $mismatched = Student::create($this->baseStudentAttributes([
            'name' => 'Ravi Kumar',
            'name_as_per_aadhaar' => 'Ravindra Kumar',
        ]));
        $clean = Student::create($this->baseStudentAttributes([
            'name' => 'Anjali Singh',
            'name_as_per_aadhaar' => 'Anjali Singh',
        ]));

        $response = $this->actingAs($admin)->get(route('admin.students.index', ['aadhaar_mismatch' => 1]));

        $response->assertOk();
        $response->assertSee('Ravi Kumar');
        $response->assertDontSee('Anjali Singh');
    }

    // --- UDISE export ---

    public function test_udise_export_route_returns_a_downloadable_file(): void
    {
        $admin = $this->makeAdmin();
        Student::create($this->baseStudentAttributes());

        $response = $this->actingAs($admin)->get(route('admin.students.export.udise'));

        $response->assertOk();
        $response->assertHeader('content-disposition');
    }

    // --- Consent (DPDP) ---

    public function test_generic_student_update_cannot_set_consent_fields(): void
    {
        $admin = $this->makeAdmin();
        $student = Student::create($this->baseStudentAttributes());

        $this->actingAs($admin)->put(route('admin.students.update', $student->id), array_merge(
            $this->baseStudentAttributes(),
            [
                'apaar_consent_given' => true,
                'apaar_consent_by' => 'Sneaky Parent',
            ]
        ));

        $student->refresh();
        $this->assertFalse($student->apaar_consent_given);
        $this->assertNull($student->apaar_consent_by);
    }

    public function test_dedicated_consent_action_records_consent(): void
    {
        $admin = $this->makeAdmin();
        $student = Student::create($this->baseStudentAttributes());

        $response = $this->actingAs($admin)->post(route('admin.students.apaar-consent', $student->id), [
            'apaar_consent_given' => 1,
            'apaar_consent_by' => 'Ramesh Kumar (Father)',
        ]);

        $response->assertRedirect();
        $student->refresh();
        $this->assertTrue($student->apaar_consent_given);
        $this->assertSame('Ramesh Kumar (Father)', $student->apaar_consent_by);
        $this->assertNotNull($student->apaar_consent_date);
    }

    public function test_dedicated_consent_action_requires_consent_by_when_giving_consent(): void
    {
        $admin = $this->makeAdmin();
        $student = Student::create($this->baseStudentAttributes());

        $response = $this->actingAs($admin)->post(route('admin.students.apaar-consent', $student->id), [
            'apaar_consent_given' => 1,
        ]);

        $response->assertSessionHasErrors('apaar_consent_by');
        $student->refresh();
        $this->assertFalse($student->apaar_consent_given);
    }

    public function test_dedicated_consent_action_can_withdraw_consent(): void
    {
        $admin = $this->makeAdmin();
        $student = Student::create($this->baseStudentAttributes());
        $student->apaar_consent_given = true;
        $student->apaar_consent_by = 'Ramesh Kumar (Father)';
        $student->apaar_consent_date = now()->toDateString();
        $student->save();

        $this->actingAs($admin)->post(route('admin.students.apaar-consent', $student->id), [
            'apaar_consent_given' => 0,
        ]);

        $student->refresh();
        $this->assertFalse($student->apaar_consent_given);
        $this->assertNull($student->apaar_consent_by);
        $this->assertNull($student->apaar_consent_date);
    }
}

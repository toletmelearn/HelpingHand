<?php

namespace Tests\Feature\Admin;

use App\Models\AcademicSession;
use App\Models\CombinedClassGroup;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Section architecture fix: CombinedClassGroupController::store() validated
 * each member's section_id with nothing stronger than `exists:sections,id`,
 * which only proves the row exists, never that it actually belongs to that
 * member's own school_class_id. Sections are globally shared labels
 * (A/B/C/D...) resolved to a class via the legacy_class_map ->
 * class_management -> class_sections bridge (SchoolClass::validSectionIds())
 * -- the same root cause and fix already applied to TimetableController and
 * TeacherSubstitutionController. Left unvalidated, a mismatched member
 * pairing would flow straight into TimetableController::storeCombined(),
 * which trusts each member's section_id as already-correct.
 */
class CombinedClassGroupSectionOwnershipTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $user->roles()->attach($role->id);

        return $user;
    }

    private function makeSession(): AcademicSession
    {
        return AcademicSession::create(['name' => '2026-2027', 'code' => '2026-2027', 'start_date' => '2026-04-01', 'end_date' => '2027-03-31']);
    }

    private function fixtures(): array
    {
        $classA = SchoolClass::create(['name' => 'CCG Class A', 'class_order' => random_int(1, 100000), 'is_active' => true]);
        $classB = SchoolClass::create(['name' => 'CCG Class B', 'class_order' => random_int(1, 100000), 'is_active' => true]);

        $sectionForA = Section::create(['name' => 'A']);
        $this->bridgeSectionToClass($classA, $sectionForA);

        $sectionForB = Section::create(['name' => 'B']);
        $this->bridgeSectionToClass($classB, $sectionForB);

        $subject = Subject::create(['name' => 'CCG Subject', 'code' => 'CCG-' . uniqid()]);
        $session = $this->makeSession();

        return compact('classA', 'classB', 'sectionForA', 'sectionForB', 'subject', 'session');
    }

    // 6. Valid class + section succeeds.
    public function test_store_accepts_a_valid_class_and_section_pairing_for_every_member(): void
    {
        $f = $this->fixtures();
        $admin = $this->admin();

        $response = $this->actingAs($admin)->post(route('combined-class-groups.store'), [
            'name' => 'Valid Pairing Group',
            'subject_id' => $f['subject']->id,
            'academic_session_id' => $f['session']->id,
            'members' => [
                ['school_class_id' => $f['classA']->id, 'section_id' => $f['sectionForA']->id],
                ['school_class_id' => $f['classB']->id, 'section_id' => $f['sectionForB']->id],
            ],
        ]);

        $response->assertRedirect(route('combined-class-groups.index'));
        $group = CombinedClassGroup::where('name', 'Valid Pairing Group')->firstOrFail();
        $this->assertSame(2, $group->members()->count());
    }

    // 7. Invalid class + section fails.
    public function test_store_rejects_a_member_section_belonging_to_a_different_class(): void
    {
        $f = $this->fixtures();
        $admin = $this->admin();

        $response = $this->actingAs($admin)->post(route('combined-class-groups.store'), [
            'name' => 'Mismatched Group',
            'subject_id' => $f['subject']->id,
            'academic_session_id' => $f['session']->id,
            'members' => [
                // classA paired with classB's own section -- invalid.
                ['school_class_id' => $f['classA']->id, 'section_id' => $f['sectionForB']->id],
                ['school_class_id' => $f['classB']->id, 'section_id' => $f['sectionForB']->id],
            ],
        ]);

        $response->assertSessionHasErrors('error');
        $this->assertDatabaseMissing('combined_class_groups', ['name' => 'Mismatched Group']);
    }

    // 8. Every member's class/section pairing is validated -- a bad pairing later in the list is still caught.
    public function test_store_validates_every_member_not_just_the_first(): void
    {
        $f = $this->fixtures();
        $admin = $this->admin();

        $response = $this->actingAs($admin)->post(route('combined-class-groups.store'), [
            'name' => 'Second Member Bad',
            'subject_id' => $f['subject']->id,
            'academic_session_id' => $f['session']->id,
            'members' => [
                // First member is a genuinely valid pairing...
                ['school_class_id' => $f['classA']->id, 'section_id' => $f['sectionForA']->id],
                // ...second member is not.
                ['school_class_id' => $f['classB']->id, 'section_id' => $f['sectionForA']->id],
            ],
        ]);

        $response->assertSessionHasErrors('error');
        $this->assertDatabaseMissing('combined_class_groups', ['name' => 'Second Member Bad']);
    }

    // 9. A rejected request must not create partial invalid data (neither the group nor any member row).
    public function test_rejected_request_creates_no_partial_data(): void
    {
        $f = $this->fixtures();
        $admin = $this->admin();

        $countBefore = CombinedClassGroup::count();
        $memberCountBefore = \App\Models\CombinedClassGroupMember::count();

        $this->actingAs($admin)->post(route('combined-class-groups.store'), [
            'name' => 'Partial Data Check',
            'subject_id' => $f['subject']->id,
            'academic_session_id' => $f['session']->id,
            'members' => [
                ['school_class_id' => $f['classA']->id, 'section_id' => $f['sectionForA']->id],
                ['school_class_id' => $f['classB']->id, 'section_id' => $f['sectionForA']->id],
            ],
        ]);

        $this->assertSame($countBefore, CombinedClassGroup::count());
        $this->assertSame($memberCountBefore, \App\Models\CombinedClassGroupMember::count());
    }

    // A null (whole-class) section_id must remain valid -- no regression (matches existing CombinedClassGroupTest coverage).
    public function test_store_still_accepts_a_null_whole_class_section_for_a_member(): void
    {
        $f = $this->fixtures();
        $admin = $this->admin();

        $response = $this->actingAs($admin)->post(route('combined-class-groups.store'), [
            'name' => 'Whole Class Member Group',
            'subject_id' => $f['subject']->id,
            'academic_session_id' => $f['session']->id,
            'members' => [
                ['school_class_id' => $f['classA']->id, 'section_id' => null],
                ['school_class_id' => $f['classB']->id, 'section_id' => null],
            ],
        ]);

        $response->assertRedirect(route('combined-class-groups.index'));
        $this->assertDatabaseHas('combined_class_groups', ['name' => 'Whole Class Member Group']);
    }
}

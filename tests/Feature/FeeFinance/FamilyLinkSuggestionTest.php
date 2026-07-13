<?php

namespace Tests\Feature\FeeFinance;

use App\Models\Family;
use App\Models\FamilyLinkSuggestion;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Student::booted()'s sibling-by-phone detection -- confirms it only ever
 * queues a pending suggestion (never silently sets family_id), and that
 * the admin confirm/dismiss actions behave correctly.
 */
class FamilyLinkSuggestionTest extends TestCase
{
    use RefreshDatabase;

    private function makeAccountant(): User
    {
        $accountant = User::factory()->create(['role' => 'accountant']);
        $role = Role::firstOrCreate(['name' => 'accountant'], ['display_name' => 'Accountant']);
        $accountant->roles()->attach($role->id);
        return $accountant;
    }

    private function makeStudent(array $overrides = []): Student
    {
        return Student::create(array_merge([
            'name' => 'Sib Kid', 'father_name' => 'Father', 'mother_name' => 'Mother',
            'date_of_birth' => '2015-01-01', 'gender' => 'male', 'category' => 'General',
            'aadhar_number' => (string) random_int(100000000000, 999999999999),
            'address' => 'Somewhere',
        ], $overrides));
    }

    public function test_a_shared_phone_number_creates_a_pending_suggestion_not_a_silent_link()
    {
        $first = $this->makeStudent(['name' => 'Elder Kid', 'mobile' => '9111122223']);
        $second = $this->makeStudent(['name' => 'Younger Kid', 'mobile' => '9111122223']);

        $this->assertNull($second->fresh()->family_id);
        $this->assertNull($first->fresh()->family_id);

        $suggestion = FamilyLinkSuggestion::where('student_id', $second->id)->first();
        $this->assertNotNull($suggestion);
        $this->assertEquals('pending', $suggestion->status);
        $this->assertEquals($first->id, $suggestion->candidate_student_id);
        $this->assertNull($suggestion->matched_family_id);
    }

    public function test_no_suggestion_when_no_phone_overlap()
    {
        $this->makeStudent(['name' => 'Solo Kid', 'mobile' => '9000000001']);

        $this->assertEquals(0, FamilyLinkSuggestion::count());
    }

    public function test_admin_confirming_a_new_pair_creates_a_family_and_links_both()
    {
        $accountant = $this->makeAccountant();
        $first = $this->makeStudent(['name' => 'Elder Kid', 'mobile' => '9222233334']);
        $second = $this->makeStudent(['name' => 'Younger Kid', 'mobile' => '9222233334']);

        $suggestion = FamilyLinkSuggestion::where('student_id', $second->id)->first();

        $response = $this->actingAs($accountant)->post(route('admin.family-link-suggestions.confirm', $suggestion->id));

        $response->assertSessionHas('success');
        $suggestion->refresh();
        $this->assertEquals('confirmed', $suggestion->status);
        $this->assertEquals($accountant->id, $suggestion->resolved_by);

        $second->refresh();
        $first->refresh();
        $this->assertNotNull($second->family_id);
        $this->assertEquals($second->family_id, $first->family_id);
        $this->assertEquals(1, Family::count());
    }

    public function test_admin_confirming_a_match_against_an_existing_family_links_only_the_new_student()
    {
        $accountant = $this->makeAccountant();
        $existingFamily = Family::create(['guardian_name' => 'Existing Guardian', 'mobile' => '9333344445']);
        $first = $this->makeStudent(['name' => 'Already Linked Kid', 'mobile' => '9333344445', 'family_id' => $existingFamily->id]);
        $second = $this->makeStudent(['name' => 'New Sibling Kid', 'mobile' => '9333344445']);

        $suggestion = FamilyLinkSuggestion::where('student_id', $second->id)->first();
        $this->assertEquals($existingFamily->id, $suggestion->matched_family_id);
        $this->assertNull($suggestion->candidate_student_id);

        $this->actingAs($accountant)->post(route('admin.family-link-suggestions.confirm', $suggestion->id));

        $second->refresh();
        $this->assertEquals($existingFamily->id, $second->family_id);
        $this->assertEquals(1, Family::count());
    }

    public function test_admin_can_dismiss_a_suggestion_leaving_both_unlinked()
    {
        $accountant = $this->makeAccountant();
        $first = $this->makeStudent(['name' => 'Elder Kid', 'mobile' => '9444455556']);
        $second = $this->makeStudent(['name' => 'Younger Kid', 'mobile' => '9444455556']);

        $suggestion = FamilyLinkSuggestion::where('student_id', $second->id)->first();

        $response = $this->actingAs($accountant)->post(route('admin.family-link-suggestions.dismiss', $suggestion->id));

        $response->assertSessionHas('success');
        $this->assertEquals('dismissed', $suggestion->fresh()->status);
        $this->assertNull($second->fresh()->family_id);
        $this->assertNull($first->fresh()->family_id);
    }
}

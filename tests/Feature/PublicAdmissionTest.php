<?php

namespace Tests\Feature;

use App\Models\AdmissionEnquiry;
use App\Models\NotificationSetting;
use App\Models\NotificationLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PublicAdmissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Rate-limiter state lives in the cache store, which RefreshDatabase does not reset
        // between tests within the same process — flush it so tests don't bleed into each other.
        Cache::flush();
    }

    public function test_guest_can_submit_admission_enquiry(): void
    {
        $response = $this->post(route('admissions.apply.submit'), [
            'candidate_name' => 'Public Applicant',
            'parent_name' => 'Public Parent',
            'phone' => '9990001111',
            'email' => 'public.parent@example.com',
        ]);

        $response->assertRedirect(route('admissions.apply'));
        $response->assertSessionHas('success');

        $enquiry = AdmissionEnquiry::where('candidate_name', 'Public Applicant')->first();
        $this->assertNotNull($enquiry);
        $this->assertEquals('new', $enquiry->status);
        $this->assertNull($enquiry->counsellor_id);
    }

    public function test_guest_cannot_set_status_or_counsellor_via_extra_fields(): void
    {
        $admin = User::factory()->create();

        $this->post(route('admissions.apply.submit'), [
            'candidate_name' => 'Sneaky Applicant',
            'parent_name' => 'Sneaky Parent',
            'phone' => '9990002222',
            'status' => 'admitted',
            'counsellor_id' => $admin->id,
            'interview_score' => 100,
        ]);

        $enquiry = AdmissionEnquiry::where('candidate_name', 'Sneaky Applicant')->first();
        $this->assertNotNull($enquiry);
        $this->assertEquals('new', $enquiry->status);
        $this->assertNull($enquiry->counsellor_id);
        $this->assertNull($enquiry->interview_score);
    }

    public function test_duplicate_submission_does_not_create_a_second_enquiry(): void
    {
        AdmissionEnquiry::create([
            'candidate_name' => 'Existing Lead',
            'parent_name' => 'Existing Parent',
            'phone' => '9990003333',
            'status' => 'new',
        ]);

        $response = $this->post(route('admissions.apply.submit'), [
            'candidate_name' => 'Existing Lead Again',
            'parent_name' => 'Existing Parent',
            'phone' => '9990003333',
        ]);

        $response->assertRedirect(route('admissions.apply'));
        $response->assertSessionHas('success');
        $this->assertEquals(1, AdmissionEnquiry::where('phone', '9990003333')->count());
    }

    public function test_successful_submission_triggers_parent_confirmation_notification(): void
    {
        $admin = User::factory()->create();
        NotificationSetting::create([
            'event_type' => 'admission_enquiry_received',
            'notification_type' => 'sms',
            'is_enabled' => true,
            'template_body' => 'Thanks for enquiring about {{candidate_name}}.',
            'created_by' => $admin->id,
        ]);

        $this->post(route('admissions.apply.submit'), [
            'candidate_name' => 'Notify Guest Kid',
            'parent_name' => 'Notify Guest Parent',
            'phone' => '9990004444',
        ]);

        $enquiry = AdmissionEnquiry::where('candidate_name', 'Notify Guest Kid')->first();
        $this->assertEquals(1, NotificationLog::where('recipient_id', $enquiry->id)->count());
    }

    public function test_apply_form_is_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->get(route('admissions.apply'))->assertStatus(200);
        }

        $this->get(route('admissions.apply'))->assertStatus(429);
    }

    public function test_form_page_is_publicly_accessible(): void
    {
        $this->get(route('admissions.apply'))->assertStatus(200);
    }
}

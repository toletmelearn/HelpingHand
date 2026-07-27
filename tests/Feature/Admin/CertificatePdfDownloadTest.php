<?php

namespace Tests\Feature\Admin;

use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CertificatePdfDownloadTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $user->roles()->attach($role->id);

        return $user;
    }

    private function makeStudent(): Student
    {
        return Student::create([
            'name' => 'Test Student',
            'father_name' => 'Test Father',
            'mother_name' => 'Test Mother',
            'date_of_birth' => '2010-01-01',
            'aadhaar_number' => (string) random_int(100000000000, 999999999999),
            'address' => 'Somewhere',
            'phone' => '9999999999',
            'class' => 'Class 8',
        ]);
    }

    private function makeTemplate(string $type = 'tc'): CertificateTemplate
    {
        $creator = User::factory()->create();

        return CertificateTemplate::create([
            'name' => ucfirst($type) . ' Template',
            'type' => $type,
            'template_content' => 'This certifies that {{recipient.name}} was a student of this school.',
            'template_variables' => ['recipient.name'],
            'is_default' => true,
            'is_active' => true,
            'created_by' => $creator->id,
        ]);
    }

    private function makeCertificate(Student $student, string $status, string $type = 'tc'): Certificate
    {
        $creator = User::factory()->create();

        return Certificate::create([
            'certificate_type' => $type,
            'serial_number' => 'CERT-2026-' . str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT),
            'recipient_id' => $student->id,
            'recipient_type' => Student::class,
            'content_data' => ['recipient.name' => $student->name],
            'status' => $status,
            'created_by' => $creator->id,
            'published_at' => in_array($status, ['published', 'locked'], true) ? now() : null,
        ]);
    }

    public function test_admin_can_download_pdf_for_a_generated_certificate(): void
    {
        $admin = $this->makeAdmin();
        $student = $this->makeStudent();
        $this->makeTemplate('tc');
        $certificate = $this->makeCertificate($student, 'generated');

        $response = $this->actingAs($admin)->get(route('admin.certificates.download-pdf', $certificate->id));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_admin_can_download_pdf_for_a_published_certificate(): void
    {
        $admin = $this->makeAdmin();
        $student = $this->makeStudent();
        $this->makeTemplate('tc');
        $certificate = $this->makeCertificate($student, 'published');

        $response = $this->actingAs($admin)->get(route('admin.certificates.download-pdf', $certificate->id));

        $response->assertOk();
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_admin_can_download_pdf_for_a_locked_certificate(): void
    {
        $admin = $this->makeAdmin();
        $student = $this->makeStudent();
        $this->makeTemplate('tc');
        $certificate = $this->makeCertificate($student, 'locked');

        $response = $this->actingAs($admin)->get(route('admin.certificates.download-pdf', $certificate->id));

        $response->assertOk();
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_draft_certificate_cannot_be_downloaded(): void
    {
        $admin = $this->makeAdmin();
        $student = $this->makeStudent();
        $this->makeTemplate('tc');
        $certificate = $this->makeCertificate($student, 'draft');

        $response = $this->actingAs($admin)->get(route('admin.certificates.download-pdf', $certificate->id));

        $response->assertRedirect();
        $response->assertSessionHasErrors('error');
    }

    public function test_revoked_certificate_cannot_be_downloaded(): void
    {
        $admin = $this->makeAdmin();
        $student = $this->makeStudent();
        $this->makeTemplate('tc');
        $certificate = $this->makeCertificate($student, 'revoked');

        $response = $this->actingAs($admin)->get(route('admin.certificates.download-pdf', $certificate->id));

        $response->assertRedirect();
        $response->assertSessionHasErrors('error');
    }

    /**
     * downloadPdf() now requires CertificatePolicy::view() (remediation
     * Task 3), same as preview() -- but that check only runs for an
     * authenticated user; a guest is stopped earlier by the constructor's
     * 'auth' middleware and redirected to login before authorization is
     * even reached. See CertificateAuthorizationTest for the role-based
     * 403 coverage now that the policy actually exists.
     */
    public function test_guest_cannot_download_pdf(): void
    {
        $student = $this->makeStudent();
        $this->makeTemplate('tc');
        $certificate = $this->makeCertificate($student, 'generated');

        $response = $this->get(route('admin.certificates.download-pdf', $certificate->id));

        $response->assertRedirect(route('login'));
    }

    public function test_tc_certificate_pdf_shows_serial_number_and_issue_date_prominently(): void
    {
        $admin = $this->makeAdmin();
        $student = $this->makeStudent();
        $this->makeTemplate('tc');
        $certificate = $this->makeCertificate($student, 'published', 'tc');
        $certificate->refresh();

        // DomPDF's rendered content isn't reliably grep-able (compressed
        // PDF text streams), so this asserts against the same Blade view
        // DomPDF renders from, with the certificate's actual published_at
        // (the real TC issue date per CertificateController::publish()),
        // not just today's date.
        $html = view('admin.certificates.certificate-pdf', [
            'certificate' => $certificate,
            'content' => 'This certifies that Test Student was a student of this school.',
        ])->render();

        $this->assertStringContainsString($certificate->serial_number, $html);
        $this->assertStringContainsString($certificate->published_at->format('d/m/Y'), $html);
        $this->assertStringContainsString('Issue Date', $html);
    }
}

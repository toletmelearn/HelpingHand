<?php

namespace Tests\Feature\Admin;

use App\Models\Alumni;
use App\Models\AssetCategory;
use App\Models\BiometricDevice;
use App\Models\Hostel;
use App\Models\NotificationTemplate;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Priority audit finding E1: AssetCategoryController, BiometricDeviceController,
 * NotificationTemplateController, HostelController, and AdminAlumniController
 * had zero authorization -- their route groups only require auth/verified, and
 * none of the controllers called $this->authorize() or checked a role, unlike
 * every sibling Admin\* controller. Any authenticated account (teacher, parent,
 * accountant, clerk) could reach them, including writing biometric device
 * credentials (username/password/api_key) and rewriting notification templates.
 * Fixed by adding the same 'role:admin' middleware already used by
 * OperationsController/SetupWizardController -- these controllers have no
 * per-record ownership concept, unlike HomeworkNoticeController (see
 * HomeworkNoticeAuthorizationTest), so a role check is the correct minimal fix.
 */
class BackOfficeControllerAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $user->roles()->attach($role->id);

        return $user;
    }

    private function makeClerk(): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'clerk'], ['display_name' => 'Clerk']);
        $user->roles()->attach($role->id);

        return $user;
    }

    // --- AssetCategoryController ---------------------------------------------

    public function test_admin_can_view_asset_categories(): void
    {
        $response = $this->actingAs($this->makeAdmin())->get(route('admin.inventory.categories.index'));
        $response->assertOk();
    }

    public function test_non_admin_cannot_view_asset_categories(): void
    {
        $response = $this->actingAs($this->makeClerk())->get(route('admin.inventory.categories.index'));
        $response->assertForbidden();
    }

    public function test_non_admin_cannot_create_an_asset_category(): void
    {
        $response = $this->actingAs($this->makeClerk())->post(route('admin.inventory.categories.store'), [
            'name' => 'Furniture', 'type' => 'furniture',
        ]);
        $response->assertForbidden();
        $this->assertDatabaseMissing('asset_categories', ['name' => 'Furniture']);
    }

    public function test_guest_cannot_view_asset_categories(): void
    {
        $response = $this->get(route('admin.inventory.categories.index'));
        $response->assertRedirect(route('login'));
    }

    // --- BiometricDeviceController --------------------------------------------

    public function test_admin_can_view_biometric_devices(): void
    {
        $response = $this->actingAs($this->makeAdmin())->get(route('admin.biometric-devices.index'));
        $response->assertOk();
    }

    public function test_non_admin_cannot_view_biometric_devices(): void
    {
        $response = $this->actingAs($this->makeClerk())->get(route('admin.biometric-devices.index'));
        $response->assertForbidden();
    }

    public function test_non_admin_cannot_create_a_biometric_device(): void
    {
        $response = $this->actingAs($this->makeClerk())->post(route('admin.biometric-devices.store'), [
            'name' => 'Front Gate', 'ip_address' => '10.0.0.5',
        ]);
        $response->assertForbidden();
        $this->assertDatabaseMissing('biometric_devices', ['name' => 'Front Gate']);
    }

    public function test_non_admin_cannot_update_a_biometric_device(): void
    {
        $device = BiometricDevice::create([
            'name' => 'Existing Device', 'device_type' => 'zkteco', 'connection_type' => 'TCP/IP', 'ip_address' => '10.0.0.1',
        ]);

        $response = $this->actingAs($this->makeClerk())->put(route('admin.biometric-devices.update', $device), [
            'name' => 'Tampered', 'ip_address' => '10.0.0.99',
        ]);
        $response->assertForbidden();
        $this->assertSame('Existing Device', $device->fresh()->name);
    }

    public function test_guest_cannot_view_biometric_devices(): void
    {
        $response = $this->get(route('admin.biometric-devices.index'));
        $response->assertRedirect(route('login'));
    }

    // --- NotificationTemplateController ---------------------------------------

    public function test_admin_can_view_notification_templates(): void
    {
        $response = $this->actingAs($this->makeAdmin())->get(route('admin.notifications.index'));
        $response->assertOk();
    }

    public function test_non_admin_cannot_view_notification_templates(): void
    {
        $response = $this->actingAs($this->makeClerk())->get(route('admin.notifications.index'));
        $response->assertForbidden();
    }

    public function test_non_admin_cannot_create_a_notification_template(): void
    {
        $response = $this->actingAs($this->makeClerk())->post(route('admin.notifications.store'), [
            'name' => 'Phishing Template', 'event_type' => 'absence', 'channel' => 'email', 'template_content' => 'Click here',
        ]);
        $response->assertForbidden();
        $this->assertDatabaseMissing('notification_templates', ['name' => 'Phishing Template']);
    }

    public function test_non_admin_cannot_update_a_notification_template(): void
    {
        $template = NotificationTemplate::create([
            'name' => 'Existing Template', 'event_type' => 'absence', 'channel' => 'sms', 'template_content' => 'Original content',
        ]);

        $response = $this->actingAs($this->makeClerk())->put(route('admin.notifications.update', $template), [
            'name' => 'Tampered', 'event_type' => 'absence', 'channel' => 'sms', 'template_content' => 'Tampered content',
        ]);
        $response->assertForbidden();
        $this->assertSame('Original content', $template->fresh()->template_content);
    }

    public function test_guest_cannot_view_notification_templates(): void
    {
        $response = $this->get(route('admin.notifications.index'));
        $response->assertRedirect(route('login'));
    }

    // --- HostelController ------------------------------------------------------

    public function test_admin_can_view_hostel_dashboard(): void
    {
        $response = $this->actingAs($this->makeAdmin())->get(route('admin.hostels.index'));
        $response->assertOk();
    }

    public function test_non_admin_cannot_view_hostel_dashboard(): void
    {
        $response = $this->actingAs($this->makeClerk())->get(route('admin.hostels.index'));
        $response->assertForbidden();
    }

    public function test_non_admin_cannot_create_a_hostel(): void
    {
        $response = $this->actingAs($this->makeClerk())->post(route('admin.hostels.store-hostel'), [
            'name' => 'New Hostel', 'type' => 'boys', 'capacity' => 50,
        ]);
        $response->assertForbidden();
        $this->assertDatabaseMissing('hostels', ['name' => 'New Hostel']);
    }

    public function test_guest_cannot_view_hostel_dashboard(): void
    {
        $response = $this->get(route('admin.hostels.index'));
        $response->assertRedirect(route('login'));
    }

    // --- AdminAlumniController ---------------------------------------------------

    public function test_admin_can_view_alumni_list(): void
    {
        $response = $this->actingAs($this->makeAdmin())->get(route('admin.alumni.index'));
        $response->assertOk();
    }

    public function test_non_admin_cannot_view_alumni_list(): void
    {
        $response = $this->actingAs($this->makeClerk())->get(route('admin.alumni.index'));
        $response->assertForbidden();
    }

    public function test_non_admin_cannot_archive_a_student_as_alumni(): void
    {
        $student = Student::create([
            'name' => 'Grad Student', 'father_name' => 'F', 'mother_name' => 'M',
            'date_of_birth' => '2005-01-01', 'gender' => 'male', 'category' => 'General',
            'aadhaar_number' => '999988887777', 'phone' => '9000000099', 'address' => 'Addr',
        ]);

        $response = $this->actingAs($this->makeClerk())->post(route('admin.alumni.store'), [
            'student_id' => $student->id, 'graduation_year' => 2026,
        ]);
        $response->assertForbidden();
        $this->assertDatabaseMissing('alumni', ['student_id' => $student->id]);
    }

    public function test_guest_cannot_view_alumni_list(): void
    {
        $response = $this->get(route('admin.alumni.index'));
        $response->assertRedirect(route('login'));
    }
}

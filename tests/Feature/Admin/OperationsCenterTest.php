<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Backup;
use App\Models\NotificationLog;
use App\Models\AdminConfiguration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class OperationsCenterTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Create admin user & role
        $this->adminUser = User::factory()->create();
        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $this->adminUser->roles()->attach($adminRole->id);
    }

    /**
     * Test Operations landing dashboard route.
     */
    public function test_operations_dashboard_endpoint_loads()
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('operations.dashboard'));

        $response->assertStatus(200);
        $response->assertViewHas('modulesCount');
        $response->assertViewHas('healthStatus');
        $response->assertSee('Operations Center');
    }

    /**
     * Test Backups Disaster Recovery Center list, run, and deletion.
     */
    public function test_disaster_recovery_backups_flow()
    {
        // 1. Index page
        $response = $this->actingAs($this->adminUser)
            ->get(route('operations.backup'));
        $response->assertStatus(200);

        // 2. Trigger Database backup
        $postResponse = $this->actingAs($this->adminUser)
            ->post(route('operations.backup.run'), [
                'type' => 'database',
                'notes' => 'Backup before normal test run'
            ]);
        $postResponse->assertRedirect(route('operations.backup'));
        $postResponse->assertSessionHas('success');

        $this->assertDatabaseHas('backups', [
            'type' => 'database',
            'status' => 'completed'
        ]);

        $backup = Backup::first();
        $this->assertNotNull($backup);

        // Clean backup file created on disk
        $filePath = storage_path('app/' . $backup->path);
        if (file_exists($filePath)) {
            @unlink($filePath);
        }

        // 3. Delete backup record
        $delResponse = $this->actingAs($this->adminUser)
            ->delete(route('operations.backup.delete', $backup->id));
        $delResponse->assertRedirect(route('operations.backup'));
        
        $this->assertDatabaseMissing('backups', ['id' => $backup->id]);
    }

    /**
     * Test Queue Monitoring Center dashboard.
     */
    public function test_queue_monitoring_center_endpoint()
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('operations.queue'));

        $response->assertStatus(200);
        $response->assertViewHas('stats');
        $response->assertViewHas('failedJobs');
    }

    /**
     * Test Scheduler Dashboard event loader.
     */
    public function test_scheduler_dashboard_endpoint()
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('operations.scheduler'));

        $response->assertStatus(200);
        $response->assertViewHas('tasks');
    }

    /**
     * Test Notification Center logs and retrying failed.
     */
    public function test_notification_center_and_retry_flow()
    {
        // Create required NotificationSetting first
        $setting = \App\Models\NotificationSetting::create([
            'event_type' => 'fee_due',
            'notification_type' => 'email',
            'is_enabled' => true,
            'template_subject' => 'Late Fee Warning',
            'template_body' => 'Please clear outstanding dues.',
            'schedule_type' => 'immediate',
            'created_by' => $this->adminUser->id
        ]);

        // Seeding a failed notification log record
        $log = NotificationLog::create([
            'notification_setting_id' => $setting->id,
            'recipient_type' => 'App\Models\User',
            'recipient_id' => $this->adminUser->id,
            'notification_type' => 'email',
            'subject' => 'Late Fee Warning',
            'message' => 'Please clear outstanding dues.',
            'status' => 'failed',
            'failed_reason' => 'Connection timeout.',
            'retry_count' => 0
        ]);

        $response = $this->actingAs($this->adminUser)
            ->get(route('operations.notifications'));
        $response->assertStatus(200);
        $response->assertSee('Late Fee Warning');

        // Trigger retry
        $postResponse = $this->actingAs($this->adminUser)
            ->post(route('operations.notifications.retry', $log->id));
        $postResponse->assertRedirect(route('operations.notifications'));

        $log->refresh();
        $this->assertEquals('pending', $log->status);
        $this->assertEquals(1, $log->retry_count);
    }

    /**
     * Test Installation checker verification route.
     */
    public function test_installation_checker_endpoint()
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('operations.verification'));

        $response->assertStatus(200);
        $response->assertViewHas('results');
    }

    /**
     * Test Log center endpoint.
     */
    public function test_system_logs_center_endpoint()
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('operations.logs'));

        $response->assertStatus(200);
        $response->assertViewHas('categorizedLogs');
    }

    /**
     * Test Activity audit timeline endpoint.
     */
    public function test_activity_timeline_endpoint()
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('operations.timeline'));

        $response->assertStatus(200);
        $response->assertViewHas('timeline');
    }

    /**
     * Test SaaS License verification key settings.
     */
    public function test_saas_license_activation_flow()
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('operations.license'));
        $response->assertStatus(200);

        $postResponse = $this->actingAs($this->adminUser)
            ->post(route('operations.license.activate'), [
                'license_key' => 'HELPINGHAND-SaaS-PRO-2026-KEY'
            ]);
        $postResponse->assertRedirect(route('operations.license'));
        
        $this->assertEquals('HELPINGHAND-SaaS-PRO-2026-KEY', AdminConfiguration::get('license', 'key'));
    }

    /**
     * Test Maintenance Mode toggler.
     */
    public function test_maintenance_mode_toggling()
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('operations.maintenance'));
        $response->assertStatus(200);

        // Turn on
        $postOn = $this->actingAs($this->adminUser)
            ->post(route('operations.maintenance.toggle'), [
                'message' => 'Site offline for core platform database upgrade.',
                'countdown_hours' => 2
            ]);
        $postOn->assertRedirect(route('operations.maintenance'));

        $this->assertTrue(AdminConfiguration::get('maintenance', 'enabled', false));

        // Turn off
        $postOff = $this->actingAs($this->adminUser)
            ->post(route('operations.maintenance.toggle'));
        $postOff->assertRedirect(route('operations.maintenance'));

        $this->assertFalse(AdminConfiguration::get('maintenance', 'enabled', false));
    }

    /**
     * Test Performance Dashboard endpoint.
     */
    public function test_performance_dashboard_endpoint()
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('operations.performance'));

        $response->assertStatus(200);
        $response->assertViewHas('metrics');
    }
}

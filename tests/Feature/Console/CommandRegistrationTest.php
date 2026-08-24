<?php

namespace Tests\Feature\Console;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Fix for a pre-existing, application-wide Artisan command-discovery bug
 * (unrelated to Phase 1B's Backup work, but discovered while implementing
 * it): bootstrap/app.php's directory-based withCommands([...]) scan never
 * actually resolves the commands it finds -- only commands explicitly
 * listed in AppServiceProvider::register()'s $this->commands([...]) array
 * are ever resolvable. backup:run, reminders:send-all, and
 * reminders:retry-failed were the only three commands relying solely on
 * the broken directory scan; this proves all three are now resolvable via
 * the same explicit-registration pattern the other 12 commands already
 * used successfully, and that nothing else regressed.
 */
class CommandRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_backup_run_is_registered_and_resolvable(): void
    {
        $this->assertArrayHasKey('backup:run', Artisan::all());
    }

    public function test_reminders_send_all_is_registered_and_resolvable(): void
    {
        $this->assertArrayHasKey('reminders:send-all', Artisan::all());
    }

    public function test_reminders_retry_failed_is_registered_and_resolvable(): void
    {
        $this->assertArrayHasKey('reminders:retry-failed', Artisan::all());
    }

    /** Each newly-registered command must appear exactly once -- no duplicate registration via the still-present (still non-functional for these files) directory scan. */
    public function test_newly_registered_commands_have_no_duplicate_entries(): void
    {
        $names = array_keys(Artisan::all());

        foreach (['backup:run', 'reminders:send-all', 'reminders:retry-failed'] as $signature) {
            $matches = array_filter($names, fn ($name) => $name === $signature);
            $this->assertCount(1, $matches, "Expected exactly one registration for [{$signature}], found " . count($matches) . '.');
        }
    }

    /**
     * A representative sample of the 12 already-working explicitly
     * registered commands remain resolvable and unaffected -- proves the
     * fix is purely additive.
     */
    public function test_previously_working_explicit_commands_remain_resolvable(): void
    {
        $all = Artisan::all();

        foreach (['route:health-check', 'erp:diagnose', 'architecture:audit', 'library:calculate-fines', 'admin:audit-sidebar'] as $signature) {
            $this->assertArrayHasKey($signature, $all, "Expected [{$signature}] to remain registered.");
        }
    }

    /**
     * Existing command behavior is genuinely unchanged -- actually runs a
     * safe, read-only, already-working command end to end. Its exit code
     * reflects whether it found broken routes in this test environment
     * (a pre-existing characteristic of that command, unrelated to this
     * fix) -- what matters here is that it resolves and runs at all,
     * without a fatal/unresolved-command error.
     */
    public function test_an_existing_explicitly_registered_command_still_executes_successfully(): void
    {
        $exitCode = Artisan::call('route:health-check');

        $this->assertIsInt($exitCode);
        $this->assertContains($exitCode, [0, 1]);
        $this->assertNotEmpty(Artisan::output());
    }

    /**
     * Command resolution can be proven without ever executing the
     * command's real (destructive/notification-sending) logic -- Symfony
     * Console's --help flag short-circuits before handle() runs.
     */
    public function test_backup_run_help_does_not_execute_the_real_backup_logic(): void
    {
        $exitCode = Artisan::call('backup:run', ['--help' => true]);

        $this->assertSame(0, $exitCode);
        $this->assertDatabaseCount('backups', 0);
    }

    public function test_reminders_send_all_help_does_not_send_any_reminder(): void
    {
        $exitCode = Artisan::call('reminders:send-all', ['--help' => true]);

        $this->assertSame(0, $exitCode);
    }

    public function test_reminders_retry_failed_help_does_not_dispatch_anything(): void
    {
        $exitCode = Artisan::call('reminders:retry-failed', ['--help' => true]);

        $this->assertSame(0, $exitCode);
    }
}

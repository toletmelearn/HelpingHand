<?php

namespace Tests\Unit\Jobs;

use App\Jobs\GenerateTimetableJob;
use App\Models\SchoolClass;
use App\Models\TimetableGeneration;
use App\Services\Timetable\GeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * Item 4 (reliability): GenerateTimetableJob previously only rethrew its
 * exception in the testing environment, so a production failure was
 * marked FAILED, logged, and never seen by the queue worker -- no
 * automatic retry ever happened, even for a purely transient failure
 * (DB deadlock, lock timeout). Fixed by always rethrowing (Laravel's own
 * retry machinery needs to see the exception to act on it) plus
 * configuring $tries/backoff -- safe only because GeneratorService::generate()
 * is a pure read-and-compute pass with zero DB writes of its own, and the
 * job's only writes are a single atomic delete-then-insert transaction,
 * so a retried attempt can never accumulate duplicate or partial state.
 */
class GenerateTimetableJobTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function makeGeneration(): TimetableGeneration
    {
        $class = SchoolClass::create(['name' => 'GTJ Class', 'class_order' => random_int(1, 100000), 'is_active' => true]);

        return TimetableGeneration::create([
            'academic_year' => '2026-2027',
            'school_class_ids' => [$class->id],
            'style' => TimetableGeneration::STYLE_ROTATING,
            'status' => TimetableGeneration::STATUS_QUEUED,
        ]);
    }

    public function test_failed_generation_marks_status_failed_and_logs_error(): void
    {
        $generation = $this->makeGeneration();
        $job = new GenerateTimetableJob($generation->id);

        $failingService = Mockery::mock(GeneratorService::class);
        $failingService->shouldReceive('generate')->once()->andThrow(new \RuntimeException('Simulated solver crash'));

        try {
            $job->handle($failingService);
            $this->fail('Expected the job to rethrow the underlying exception.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Simulated solver crash', $e->getMessage());
        }

        $generation->refresh();
        $this->assertSame(TimetableGeneration::STATUS_FAILED, $generation->status);
        $this->assertSame('Simulated solver crash', $generation->error);
        $this->assertNotNull($generation->completed_at);
    }

    /**
     * The job must actually surface the failure to Laravel's queue worker
     * (via the unconditional rethrow proven above) AND be configured to
     * retry -- both halves are required; rethrowing with no $tries
     * configured is still only a single, terminal attempt.
     */
    public function test_job_is_configured_to_retry_transient_failures(): void
    {
        $generation = $this->makeGeneration();
        $job = new GenerateTimetableJob($generation->id);

        $this->assertGreaterThan(1, $job->tries, 'A transient failure must get more than one attempt.');
        $this->assertNotEmpty($job->backoff, 'Retries must wait between attempts, not hammer immediately.');
    }

    public function test_failed_hook_marks_the_generation_failed_if_handles_own_catch_never_ran(): void
    {
        $generation = $this->makeGeneration();
        $generation->update(['status' => TimetableGeneration::STATUS_RUNNING]);
        $job = new GenerateTimetableJob($generation->id);

        $job->failed(new \RuntimeException('Never reached handle() at all'));

        $generation->refresh();
        $this->assertSame(TimetableGeneration::STATUS_FAILED, $generation->status);
        $this->assertSame('Never reached handle() at all', $generation->error);
    }

    /** Regression guard: a generation already marked FAILED by handle()'s own catch must not be clobbered by a redundant failed() call with a different message. */
    public function test_failed_hook_does_not_overwrite_an_already_recorded_failure(): void
    {
        $generation = $this->makeGeneration();
        $generation->update(['status' => TimetableGeneration::STATUS_FAILED, 'error' => 'Original error from handle()']);
        $job = new GenerateTimetableJob($generation->id);

        $job->failed(new \RuntimeException('A different, later exception'));

        $generation->refresh();
        $this->assertSame('Original error from handle()', $generation->error);
    }
}

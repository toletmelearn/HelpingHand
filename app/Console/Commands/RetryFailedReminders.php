<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ReminderEngineService;

class RetryFailedReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reminders:retry-failed';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scan failed fee reminders and re-attempt dispatching up to 3 times';

    protected $reminderService;

    public function __construct(ReminderEngineService $reminderService)
    {
        parent::__construct();
        $this->reminderService = $reminderService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Retrying failed fee reminders...');
        $this->reminderService->retryFailedReminders();
        $this->info('Failed reminders retry run finished.');
        return Command::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ReminderEngineService;

class SendFeeReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reminders:send-all';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scan outstanding student fees and trigger due/overdue reminders based on rules';

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
        $this->info('Starting fee reminder engine...');
        $this->reminderService->processReminders();
        $this->info('Fee reminders processed successfully.');
        return Command::SUCCESS;
    }
}

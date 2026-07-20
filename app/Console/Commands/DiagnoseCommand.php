<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Operations\DiagnosticEngine;

class DiagnoseCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'erp:diagnose';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Perform real-time environment diagnostics check on database, queues, storage, and scheduler';

    /**
     * Execute the console command.
     */
    public function handle(DiagnosticEngine $engine): int
    {
        $this->info("=============================================");
        $this->info("   HelpingHand ERP System Diagnostics Tool   ");
        $this->info("=============================================");
        $this->line("");

        $this->comment("Running diagnostic tests on system environment...");
        $results = $engine->runAll();
        
        $tableRows = [];
        $hasError = false;

        foreach ($results as $item) {
            $name = $item['name'];
            $category = $item['category'];
            $res = $item['result'];
            $statusVal = strtoupper($res['status']);
            
            if ($res['status'] === 'success') {
                $statusFormatted = "<info>{$statusVal}</info>";
            } elseif ($res['status'] === 'warning') {
                $statusFormatted = "<comment>{$statusVal}</comment>";
            } else {
                $statusFormatted = "<error>{$statusVal}</error>";
                $hasError = true;
            }

            $tableRows[] = [
                $category,
                $name,
                $statusFormatted,
                $res['message']
            ];
        }

        $this->table(
            ['Category', 'Diagnostic Check', 'Status', 'Description/Details'],
            $tableRows
        );

        $this->line("");
        $this->info("=============================================");
        
        if ($hasError) {
            $this->error("   DIAGNOSTIC STATUS: FAILURE (Check logs)   ");
            $this->info("=============================================");
            return 1;
        }

        $this->info("   DIAGNOSTIC STATUS: SUCCESS (All Green)    ");
        $this->info("=============================================");
        return 0;
    }
}

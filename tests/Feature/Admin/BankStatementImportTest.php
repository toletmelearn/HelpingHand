<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\BankStatementRow;
use App\Services\Imports\ImportEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Regression coverage for importing a REAL downloaded bank statement (not
 * the hand-built 5-column template). A real HDFC (and most other Indian
 * banks') export has ~20 rows of letterhead before the real header, splits
 * money into separate Withdrawal/Deposit columns instead of one Amount
 * column, underlines the header with a line of asterisks, and appends a
 * "STATEMENT SUMMARY" footer block with totals sitting in those same
 * columns. All previously caused every row to fail with "date/amount
 * field is required" -- reported by a user uploading their own real
 * statement.
 */
class BankStatementImportTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;
    protected $importEngine;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->admin = User::factory()->create();
        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $this->admin->roles()->attach($adminRole->id);
        $this->actingAs($this->admin);

        $this->importEngine = app(ImportEngine::class);
    }

    private function realBankStatementCsv(): string
    {
        // Mirrors the real row shape of an HDFC .xls export (letterhead,
        // blank rows, real header, asterisk separator, transactions, a
        // withdrawal row, and a STATEMENT SUMMARY footer whose Debits/
        // Credits/Dr Count/Cr Count totals sit in the very same columns
        // as real transaction amounts).
        $lines = [
            'HDFC BANK Ltd.,Page No .:   1,Statement of accounts,,',
            ',,,,',
            ',,,,',
            ',,,,',
            'Account Branch :DHAMPUR,,,,',
            'M/S. PUSHP NIKETAN SCHOOL,,,Address :HDFC BANK LTD,',
            'Statement From : 13/07/2026 To : 15/07/2026,,,,',
            '****************************************,,,,',
            'Date,Narration,,Withdrawal Amt.,Deposit Amt.',
            '********,**********************************,,******************,******************',
            '13/07/26,UPI-00000034704454261-PUNEETKUMRSNGH-1@OKSBI-619444217180-UPI,,,12841',
            '14/07/26,UPI-1833000100107206-AR.TOOL029@AXL-872856937030-MOHD AREEB CLASS12 TC FEE,,,100',
            '14/07/26,NEFT CHARGES REVERSAL,,50,',
            ',,,,',
            'STATEMENT SUMMARY  :-,,,,',
            'Opening Balance,,,Debits,Credits',
            '2066449.92,,,2000000,308284',
            ',,,,',
            ',,,Dr Count,Cr Count',
            ',,,1,31',
            'Generated On:,15-Jul-2026 10:50,,Requesting Branch Code:,NET',
            '--- End Of Statement ---,,,,',
        ];

        return implode("\n", $lines) . "\n";
    }

    public function test_header_letterhead_is_skipped_and_deposit_column_becomes_amount()
    {
        $file = UploadedFile::fake()->createWithContent('statement.csv', $this->realBankStatementCsv());
        $session = $this->importEngine->initializeSession('bank_statement', $file, $this->admin->id);

        $this->assertEquals(
            ['Date', 'Narration', '', 'Withdrawal Amt.', 'Amount'],
            $session->settings['headers'],
            'Header detection should skip the bank letterhead and land on the real "Date | Narration | ... | Deposit Amt." row, with Deposit renamed to Amount.'
        );

        // The old flat Levenshtein threshold used to let 'utr' (no real
        // UTR column in this file) fuzzy-match onto 'Date' just because
        // nothing else was closer -- assert it's correctly left unmapped.
        $this->assertArrayNotHasKey('utr', $session->column_mappings);
        $this->assertEquals('Date', $session->column_mappings['date']);
        $this->assertEquals('Amount', $session->column_mappings['amount']);
    }

    public function test_only_real_deposit_transactions_import_withdrawals_separators_and_summary_footer_excluded()
    {
        $file = UploadedFile::fake()->createWithContent('statement.csv', $this->realBankStatementCsv());
        $session = $this->importEngine->initializeSession('bank_statement', $file, $this->admin->id);

        $dryRun = $this->importEngine->dryRun($session->uuid, $session->column_mappings);
        $this->assertEquals(0, $dryRun['errors'], 'No row should error -- withdrawals, separators, and the summary footer are excluded before validation, not failed by it.');
        $this->assertEquals(2, $dryRun['success'], 'Only the two real deposit rows (12841 and 100) should count -- not the withdrawal, not the STATEMENT SUMMARY totals.');

        $this->importEngine->execute($session->uuid, 'skip');

        $this->assertEquals(2, BankStatementRow::count());
        $this->assertDatabaseHas('bank_statement_rows', [
            'amount' => 12841.00,
            'utr' => '619444217180',
        ]);
        $this->assertDatabaseHas('bank_statement_rows', [
            'amount' => 100.00,
            'utr' => '872856937030',
        ]);

        // The Opening Balance figure (2066449.92) must never have been
        // misread as an Excel-serial date and smuggled in as a bogus
        // "transaction".
        $this->assertDatabaseMissing('bank_statement_rows', ['amount' => 308284.00]);
        $this->assertDatabaseMissing('bank_statement_rows', ['amount' => 2000000.00]);
    }

    public function test_dd_mm_yy_dates_are_parsed_correctly_not_swapped_to_mm_dd_yy()
    {
        $file = UploadedFile::fake()->createWithContent('statement.csv', $this->realBankStatementCsv());
        $session = $this->importEngine->initializeSession('bank_statement', $file, $this->admin->id);
        $this->importEngine->execute($session->uuid, 'skip');

        // "13/07/26" can ONLY be 13 July 2026 (no 13th month exists) --
        // the clearest possible check that day/month aren't being swapped.
        $row = BankStatementRow::where('amount', 12841.00)->first();
        $this->assertEquals('2026-07-13', $row->transaction_date->format('Y-m-d'));

        // "14/07/26" is ambiguity-free the same way, and confirms the
        // second real row parsed correctly too.
        $secondRow = BankStatementRow::where('amount', 100.00)->first();
        $this->assertEquals('2026-07-14', $secondRow->transaction_date->format('Y-m-d'));
    }

    public function test_hand_built_five_column_template_still_imports_unchanged()
    {
        // The original, already-working upload path -- a hand-prepared
        // file matching getTemplateHeaders() exactly -- must still work
        // exactly as before; transformRows() only activates when it finds
        // a genuine Withdrawal/Deposit split.
        $csv = "Date,Amount,UTR,Narration,Branch\n2026-07-13,5000,123456789012,Cash deposit,Dhampur\n";
        $file = UploadedFile::fake()->createWithContent('statement.csv', $csv);
        $session = $this->importEngine->initializeSession('bank_statement', $file, $this->admin->id);

        $this->assertEquals(['Date', 'Amount', 'UTR', 'Narration', 'Branch'], $session->settings['headers']);

        $dryRun = $this->importEngine->dryRun($session->uuid, $session->column_mappings);
        $this->assertEquals(1, $dryRun['success']);
        $this->assertEquals(0, $dryRun['errors']);

        $this->importEngine->execute($session->uuid, 'skip');
        $this->assertDatabaseHas('bank_statement_rows', ['amount' => 5000.00, 'utr' => '123456789012']);
    }
}

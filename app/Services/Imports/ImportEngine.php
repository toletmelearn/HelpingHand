<?php

namespace App\Services\Imports;

use App\Contracts\Imports\ImportDefinitionInterface;
use App\Models\ImportSession;
use App\Models\ImportError;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ImportEngine
{
    private array $definitions = [];
    private ImportNormalizer $normalizer;
    private ImportLookupCache $lookupCache;

    public function __construct(ImportNormalizer $normalizer, ImportLookupCache $lookupCache)
    {
        $this->normalizer = $normalizer;
        $this->lookupCache = $lookupCache;
    }

    /**
     * Register an import definition for a module.
     */
    public function registerDefinition(string $module, string $definitionClass): void
    {
        $this->definitions[$module] = $definitionClass;
    }

    /**
     * Resolve the definition class instance for a module.
     */
    public function getDefinition(string $module): ImportDefinitionInterface
    {
        if (!isset($this->definitions[$module])) {
            throw new \InvalidArgumentException("No import definition registered for module: {$module}");
        }

        return app($this->definitions[$module]);
    }

    /**
     * Get list of all registered import modules.
     */
    public function getRegisteredModules(): array
    {
        return array_keys($this->definitions);
    }

    /**
     * Get readiness status for a module: green, yellow, red.
     */
    public function getModuleStatus(string $module): string
    {
        if (!isset($this->definitions[$module])) {
            return 'red';
        }

        $modelClass = null;
        switch ($module) {
            case 'students':
                $modelClass = \App\Models\Student::class;
                break;
            case 'teachers':
                $modelClass = \App\Models\Teacher::class;
                break;
            case 'parents':
                $modelClass = \App\Models\ParentModel::class;
                break;
            case 'classes':
                $modelClass = \App\Models\SchoolClass::class;
                break;
            case 'sections':
                $modelClass = \App\Models\Section::class;
                break;
            case 'subjects':
                $modelClass = \App\Models\Subject::class;
                break;
            case 'routes':
                $modelClass = \App\Models\Route::class;
                break;
        }

        if ($modelClass && class_exists($modelClass)) {
            try {
                if ($modelClass::exists()) {
                    return 'green';
                }
            } catch (\Throwable $e) {
                return 'yellow';
            }
        }

        return 'yellow';
    }

    /**
     * Initialize an import session from an uploaded file.
     */
    public function initializeSession(string $module, UploadedFile $file, int $userId = null): ImportSession
    {
        $definition = $this->getDefinition($module);
        
        $uuid = Str::uuid()->toString();
        $extension = $file->getClientOriginalExtension();
        $fileName = $file->getClientOriginalName();
        $storedPath = $file->storeAs('imports', "{$uuid}.{$extension}");

        // Load the file and extract headers & preview
        $rows = $this->readFileRows($storedPath, $extension);
        
        if (empty($rows)) {
            throw new \Exception("The uploaded file is empty.");
        }

        $headers = array_map('trim', $rows[0]);
        $previewRows = array_slice($rows, 1, 3);

        // Generate auto mapping suggestions
        $suggestedMappings = $this->generateSuggestedMappings($headers, $definition->getValidationRules([]));

        // Parsing a large spreadsheet (especially .xlsx via PhpSpreadsheet) can
        // take long enough that a DB connection opened earlier in the request
        // (e.g. by session/auth middleware) sits idle past the underlying
        // socket's timeout and gets dropped -- surfacing as "MySQL server has
        // gone away" on this write even though nothing is actually wrong with
        // the database. Force a fresh connection right before writing.
        $this->ensureLiveConnection();

        return ImportSession::create([
            'uuid' => $uuid,
            'module' => $module,
            'status' => 'uploaded',
            'column_mappings' => $suggestedMappings,
            'settings' => [
                'file_name' => $fileName,
                'file_path' => $storedPath,
                'file_extension' => $extension,
                'headers' => $headers,
                'preview_rows' => $previewRows,
            ],
            'total_rows' => count($rows) - 1,
            'processed_rows' => 0,
            'success_rows' => 0,
            'error_rows' => 0,
            'created_by' => $userId,
        ]);
    }

    /**
     * Run validation dry-run without writing to database.
     */
    public function dryRun(string $uuid, array $mappings): array
    {
        $session = ImportSession::where('uuid', $uuid)->firstOrFail();
        $definition = $this->getDefinition($session->module);
        
        $session->update([
            'status' => 'processing',
            'column_mappings' => $mappings,
            'start_time' => now()
        ]);

        $filePath = $session->settings['file_path'];
        $extension = $session->settings['file_extension'];
        $rows = $this->readFileRows($filePath, $extension);
        $headers = array_shift($rows); // Remove header row

        // The transaction below can't safely auto-reconnect on a lost
        // connection, so make sure it's alive now, right after the
        // potentially slow file parse.
        $this->ensureLiveConnection();

        // Clear errors from previous runs
        $session->errors()->delete();

        $errorCount = 0;
        $successCount = 0;
        $processedCount = 0;

        $errorsToLog = [];

        DB::beginTransaction();
        try {
            foreach ($rows as $index => $row) {
                if (empty(array_filter($row))) {
                    continue;
                }

                $rowNum = $index + 2; // Row numbers are 1-based, +1 for headers, +1 for index

                // Apply corrections if present in session settings
                if (isset($session->settings['corrections'][$rowNum])) {
                    foreach ($session->settings['corrections'][$rowNum] as $headerName => $correctedValue) {
                        $headerIndex = array_search($headerName, $headers);
                        if ($headerIndex !== false) {
                            $row[$headerIndex] = $correctedValue;
                        }
                    }
                }

                $rowData = $this->mapRow($headers, $row, $mappings);
                $normalized = $this->normalizer->normalizeRow($rowData);

                // Run basic field-level validations
                $rules = $definition->getValidationRules($normalized);
                $customAttributes = [];
                foreach ($rules as $dbField => $rule) {
                    $csvHeader = $mappings[$dbField] ?? null;
                    if (!empty($csvHeader)) {
                        $customAttributes[$dbField] = "{$dbField} (column: '{$csvHeader}')";
                    } else {
                        $customAttributes[$dbField] = "{$dbField} (not mapped)";
                    }
                }
                $validator = Validator::make($normalized, $rules, [], $customAttributes);

                if ($validator->fails()) {
                    $errorsToLog[] = [
                        'import_session_id' => $session->id,
                        'row_number' => $rowNum,
                        'raw_row_data' => json_encode($row),
                        'error_message' => implode(', ', $validator->errors()->all()),
                        'created_at' => now(),
                        'updated_at' => now()
                    ];
                    $errorCount++;
                } else {
                    // Try dry run logic or custom checks
                    try {
                        // Validate relationships (e.g. Class, Section)
                        $definition->executeWrite($normalized, $session, 'skip'); // using dry-run transaction
                        $successCount++;
                    } catch (\Throwable $e) {
                        $errorsToLog[] = [
                            'import_session_id' => $session->id,
                            'row_number' => $rowNum,
                            'raw_row_data' => json_encode($row),
                            'error_message' => $e->getMessage(),
                            'created_at' => now(),
                            'updated_at' => now()
                        ];
                        $errorCount++;
                    }
                }
                $processedCount++;
            }
        } finally {
            // Always rollback changes for dry-run
            DB::rollBack();
        }

        // Insert errors to DB after rollback, in small batches. A single bulk
        // insert covering every failing row in a large spreadsheet can produce
        // a SQL statement bigger than the server's max_allowed_packet, which
        // MySQL/MariaDB responds to by dropping the connection outright
        // ("MySQL server has gone away") rather than a normal query error.
        if (!empty($errorsToLog)) {
            foreach (array_chunk($errorsToLog, 100) as $chunk) {
                ImportError::insert($chunk);
            }
        }

        $status = $errorCount > 0 ? 'failed' : 'validated';
        $session->update([
            'status' => $status,
            'processed_rows' => $processedCount,
            'success_rows' => $successCount,
            'error_rows' => $errorCount,
            'end_time' => now()
        ]);

        return [
            'status' => $status,
            'total' => $processedCount,
            'success' => $successCount,
            'errors' => $errorCount
        ];
    }

    /**
     * Execute the import session, writing changes to database.
     */
    public function execute(string $uuid, string $resolutionStrategy): array
    {
        $session = ImportSession::where('uuid', $uuid)->firstOrFail();
        $definition = $this->getDefinition($session->module);
        
        $session->update([
            'status' => 'processing',
            'start_time' => now()
        ]);

        $filePath = $session->settings['file_path'];
        $extension = $session->settings['file_extension'];
        $mappings = $session->column_mappings;
        
        $rows = $this->readFileRows($filePath, $extension);
        $headers = array_shift($rows);

        // Each row below runs inside its own transaction, which can't safely
        // auto-reconnect on a lost connection -- make sure it's alive now,
        // right after the potentially slow file parse.
        $this->ensureLiveConnection();

        $successCount = 0;
        $errorCount = 0;
        $processedCount = 0;

        $this->lookupCache->clear();

        foreach ($rows as $index => $row) {
            if (empty(array_filter($row))) {
                continue;
            }

            $rowNum = $index + 2;

            // Apply corrections if present in session settings
            if (isset($session->settings['corrections'][$rowNum])) {
                foreach ($session->settings['corrections'][$rowNum] as $headerName => $correctedValue) {
                    $headerIndex = array_search($headerName, $headers);
                    if ($headerIndex !== false) {
                        $row[$headerIndex] = $correctedValue;
                    }
                }
            }

            DB::beginTransaction();
            try {
                $rowData = $this->mapRow($headers, $row, $mappings);
                $normalized = $this->normalizer->normalizeRow($rowData);

                // Run final validation check
                $rules = $definition->getValidationRules($normalized);
                $customAttributes = [];
                foreach ($rules as $dbField => $rule) {
                    $csvHeader = $mappings[$dbField] ?? null;
                    if (!empty($csvHeader)) {
                        $customAttributes[$dbField] = "{$dbField} (column: '{$csvHeader}')";
                    } else {
                        $customAttributes[$dbField] = "{$dbField} (not mapped)";
                    }
                }
                $validator = Validator::make($normalized, $rules, [], $customAttributes);

                if ($validator->fails()) {
                    throw new \Exception(implode(', ', $validator->errors()->all()));
                }

                $res = $definition->executeWrite($normalized, $session, $resolutionStrategy);
                
                if ($res['status'] !== 'skipped') {
                    $successCount++;
                }
                
                DB::commit();
            } catch (\Throwable $e) {
                DB::rollBack();
                
                ImportError::create([
                    'import_session_id' => $session->id,
                    'row_number' => $rowNum,
                    'raw_row_data' => $row,
                    'error_message' => $e->getMessage()
                ]);
                $errorCount++;
            }
            
            $processedCount++;
            
            // Periodically save state to DB for tracking progress
            if ($processedCount % 10 === 0 || $processedCount === count($rows)) {
                $session->update([
                    'processed_rows' => $processedCount,
                    'success_rows' => $successCount,
                    'error_rows' => $errorCount,
                ]);
            }
        }

        $status = 'completed';
        $session->update([
            'status' => $status,
            'end_time' => now()
        ]);

        return [
            'status' => $status,
            'total' => $processedCount,
            'success' => $successCount,
            'errors' => $errorCount
        ];
    }

    /**
     * Rollback the import session (undo).
     */
    public function rollback(string $uuid): void
    {
        $session = ImportSession::where('uuid', $uuid)->firstOrFail();
        $definition = $this->getDefinition($session->module);

        $definition->executeRollback($session);

        $session->update(['status' => 'rolled_back']);
    }

    /**
     * Convert a php.ini shorthand size ("512M", "1G", "-1" for unlimited)
     * into a byte count. Returns -1 for unlimited.
     */
    private function iniBytes(string $value): int
    {
        $value = trim($value);
        if ($value === '' || $value === '-1') {
            return -1;
        }

        $unit = strtolower(substr($value, -1));
        $number = (int) $value;

        return match ($unit) {
            'g' => $number * 1024 * 1024 * 1024,
            'm' => $number * 1024 * 1024,
            'k' => $number * 1024,
            default => $number,
        };
    }

    /**
     * Verify the DB connection is actually alive and reconnect if it went
     * stale (e.g. the underlying socket idled out during slow file parsing).
     * Cheap on a healthy connection; avoids a spurious "server has gone away"
     * on the very next write.
     */
    private function ensureLiveConnection(): void
    {
        try {
            DB::connection()->select('SELECT 1');
        } catch (\Throwable $e) {
            DB::reconnect();
        }
    }

    /**
     * Helper to read rows from CSV or Excel file.
     */
    private function readFileRows(string $path, string $extension): array
    {
        // PhpSpreadsheet is memory-hungry even with setReadDataOnly() for a
        // large, real (multi-hundred-row) school spreadsheet. Raise the limit
        // for this operation only, rather than every request; leave it alone
        // entirely if the environment has already been configured higher.
        $currentLimitBytes = $this->iniBytes(ini_get('memory_limit'));
        $floorBytes = 1024 * 1024 * 1024; // 1GB
        if ($currentLimitBytes > 0 && $currentLimitBytes < $floorBytes) {
            ini_set('memory_limit', '1024M');
        }

        $realPath = Storage::path($path);
        $rows = [];

        if (in_array(strtolower($extension), ['csv', 'txt'])) {
            if (($handle = fopen($realPath, 'r')) !== false) {
                while (($row = fgetcsv($handle, 1000, ',')) !== false) {
                    $rows[] = $row;
                }
                fclose($handle);
            }
        } else {
            // setReadDataOnly() skips loading cell styles, formatting, and
            // other presentation objects PhpSpreadsheet otherwise builds for
            // every cell -- by far the largest source of memory usage for a
            // real (styled, formatted) school-produced spreadsheet. Loading
            // via IOFactory::load() directly, as before, kept all of that in
            // memory for no reason since only raw cell values are ever used.
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($realPath);
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($realPath);
            $worksheet = $spreadsheet->getActiveSheet();

            foreach ($worksheet->getRowIterator() as $row) {
                $rowData = [];
                $cellIterator = $row->getCellIterator();
                $cellIterator->setIterateOnlyExistingCells(false);

                foreach ($cellIterator as $cell) {
                    $rowData[] = $cell->getValue();
                }
                $rows[] = $rowData;
            }

            // Explicitly break the spreadsheet's internal object graph so its
            // (still substantial) memory is freed immediately rather than
            // lingering for the rest of the request.
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet, $worksheet, $reader);
        }

        return $this->cleanImportRows($rows);
    }

    /**
     * Clean and strip out banner/title rows from the beginning of the sheet,
     * returning the rows starting from the detected header row.
     */
    private function cleanImportRows(array $rows): array
    {
        if (empty($rows)) {
            return [];
        }

        $headerKeywords = ['name', 'class', 'section', 'father', 'mother', 'dob', 'date of birth', 'mobile', 'phone', 'address', 'roll', 'enrl'];

        $headerRowIndex = 0;
        foreach ($rows as $index => $row) {
            $matchCount = 0;
            foreach ($row as $cellValue) {
                if ($cellValue === null || $cellValue === '') {
                    continue;
                }
                $cellStr = strtolower(trim((string)$cellValue));
                foreach ($headerKeywords as $kw) {
                    if (str_contains($cellStr, $kw)) {
                        $matchCount++;
                        break; // Move to next cell
                    }
                }
            }

            // If we match 3 or more keywords, we've found the header row!
            if ($matchCount >= 3) {
                $headerRowIndex = $index;
                break;
            }
        }

        $cleaned = array_slice($rows, $headerRowIndex);

        // Strip UTF-8 BOM from the first cell of the header row if present
        if (!empty($cleaned) && !empty($cleaned[0]) && is_string($cleaned[0][0])) {
            if (str_starts_with($cleaned[0][0], "\xEF\xBB\xBF")) {
                $cleaned[0][0] = substr($cleaned[0][0], 3);
            }
        }

        return $cleaned;
    }

    /**
     * Map header and row into key/value pair according to mapped config.
     */
    private function mapRow(array $headers, array $row, array $mappings): array
    {
        $data = [];
        foreach ($mappings as $dbField => $csvHeader) {
            $headerIndex = array_search($csvHeader, $headers);
            if ($headerIndex !== false) {
                $data[$dbField] = $row[$headerIndex] ?? null;
            } else {
                $data[$dbField] = null;
            }
        }
        return $data;
    }

    /**
     * Levenshtein helper to generate column suggestions based on validation rules.
     */
    private function generateSuggestedMappings(array $headers, array $rules): array
    {
        $suggestions = [];
        $targetKeys = array_keys($rules);

        // Add additional supported helper columns (e.g. sibling_admission_no)
        $targetKeys[] = 'sibling_admission_no';

        foreach ($targetKeys as $target) {
            $bestMatch = null;
            $shortestDistance = -1;

            foreach ($headers as $header) {
                $normalizedHeader = strtolower(trim(str_replace([' ', '_', '-'], '', $header)));
                $normalizedTarget = strtolower(trim(str_replace([' ', '_', '-'], '', $target)));

                // Direct exact lookup
                if ($normalizedHeader === $normalizedTarget) {
                    $bestMatch = $header;
                    break;
                }

                // Levenshtein check
                $distance = levenshtein($normalizedHeader, $normalizedTarget);
                if ($distance < 4 && ($shortestDistance === -1 || $distance < $shortestDistance)) {
                    $bestMatch = $header;
                    $shortestDistance = $distance;
                }
            }

            if ($bestMatch) {
                $suggestions[$target] = $bestMatch;
            }
        }

        return $suggestions;
    }
}

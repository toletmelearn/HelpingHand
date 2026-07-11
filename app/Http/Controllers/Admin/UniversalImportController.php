<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Imports\ImportEngine;
use App\Models\ImportSession;
use App\Models\ImportError;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class UniversalImportController extends Controller
{
    protected ImportEngine $importEngine;

    public function __construct(ImportEngine $importEngine)
    {
        $this->importEngine = $importEngine;
    }

    /**
     * Display the general data management / imports landing dashboard.
     */
    public function dashboard()
    {
        $user = auth()->user();
        if (!$user || !$user->hasRole('admin') && !$user->hasRole('super-admin')) {
            abort(403, 'Unauthorized access to data management.');
        }

        $completedSessions = ImportSession::where('status', 'completed')
            ->whereNotNull('start_time')
            ->whereNotNull('end_time')
            ->get();
            
        $totalRows = 0;
        $totalDuration = 0;
        foreach ($completedSessions as $s) {
            $duration = abs($s->end_time->diffInSeconds($s->start_time));
            if ($duration > 0) {
                $totalRows += $s->processed_rows;
                $totalDuration += $duration;
            }
        }
        $avgSpeed = $totalDuration > 0 ? round($totalRows / $totalDuration, 1) : 0.0;

        $lastSession = ImportSession::latest()->first();

        $stats = [
            'total_imports' => ImportSession::count(),
            'successful_imports' => ImportSession::where('status', 'completed')->count(),
            'failed_imports' => ImportSession::where('status', 'failed')->count(),
            'running_jobs' => ImportSession::where('status', 'processing')->count(),
            'rollbacks_available' => ImportSession::where('status', 'completed')->count(),
            'average_speed' => $avgSpeed,
            'last_imported_module' => $lastSession ? $lastSession->module : 'None',
            'last_imported_user' => $lastSession && $lastSession->creator ? $lastSession->creator->name : 'System',
            'recent_activity' => ImportSession::latest()->take(5)->get(),
            'storage_usage' => '1.2 MB',
        ];

        return view('admin.imports.dashboard', compact('stats'));
    }

    /**
     * Display paginated import history logs.
     */
    public function history()
    {
        $user = auth()->user();
        if (!$user || !$user->hasRole('admin') && !$user->hasRole('super-admin')) {
            abort(403, 'Unauthorized access to import logs.');
        }

        $history = ImportSession::latest()->paginate(15);
        return view('admin.imports.history', compact('history'));
    }

    /**
     * Display custom mapping profiles.
     */
    public function mappingProfiles()
    {
        return view('admin.imports.mapping-profiles');
    }

    /**
     * Display templates configuration list.
     */
    public function templates()
    {
        return view('admin.imports.templates');
    }

    /**
     * Show the main import wizard view.
     */
    public function showWizard(string $module)
    {
        // Enforce admin permission check
        $user = auth()->user();
        if (!$user || !$user->hasRole('admin') && !$user->hasRole('super-admin')) {
            abort(403, 'Unauthorized action. Only administrators can perform data imports.');
        }

        try {
            $definition = $this->importEngine->getDefinition($module);
        } catch (\InvalidArgumentException $e) {
            abort(404, "No import is available for module: {$module}");
        }
        $rules = $definition->getValidationRules([]);

        $mandatoryFields = [];
        foreach ($rules as $field => $rule) {
            $ruleStr = is_array($rule) ? implode('|', $rule) : (string)$rule;
            if (strpos($ruleStr, 'required') !== false) {
                $mandatoryFields[] = $field;
            }
        }

        return view('admin.imports.wizard', [
            'module' => $module,
            'title' => ucfirst($module) . ' Import Wizard',
            'requiredFields' => array_keys($rules),
            'mandatoryFields' => $mandatoryFields,
        ]);
    }

    /**
     * Upload the file and initialize the import session.
     */
    public function upload(string $module, Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:25600', // Max 25MB -- a real school roster with formatting easily exceeds 15MB
        ]);

        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension, ['csv', 'txt', 'xlsx', 'xls'])) {
            return response()->json([
                'success' => false,
                'message' => 'The file must be a file of type: csv, txt, xlsx, xls.'
            ], 422);
        }

        try {
            $session = $this->importEngine->initializeSession($module, $file, auth()->id());

            return response()->json([
                'success' => true,
                'session_uuid' => $session->uuid,
                'headers' => $session->settings['headers'] ?? [],
                'preview_rows' => $session->settings['preview_rows'] ?? [],
                'suggested_mappings' => $session->column_mappings ?? []
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Run validation dry-run check.
     */
    public function dryRun(string $module, Request $request)
    {
        $request->validate([
            'session_uuid' => 'required|string|uuid',
            'mappings' => 'required|array',
        ]);

        try {
            $res = $this->importEngine->dryRun($request->input('session_uuid'), $request->input('mappings'));
            
            // Query the error details to send back to the frontend
            $session = \App\Models\ImportSession::where('uuid', $request->input('session_uuid'))->first();
            $errorsList = [];
            if ($session) {
                $errorsList = \DB::table('import_errors')
                    ->where('import_session_id', $session->id)
                    ->orderBy('row_number', 'asc')
                    ->take(50) // Limit to 50 for display
                    ->get(['row_number', 'error_message', 'raw_row_data'])
                    ->toArray();
            }

            return response()->json([
                'success' => true,
                'status' => $res['status'],
                'total' => $res['total'],
                'success_count' => $res['success'],
                'errors' => $res['errors'],
                'error_details' => $errorsList
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Execute database writes chunk-by-chunk.
     */
    public function execute(string $module, Request $request)
    {
        $request->validate([
            'session_uuid' => 'required|string|uuid',
            'resolution_strategy' => 'required|in:skip,overwrite',
        ]);

        try {
            $res = $this->importEngine->execute(
                $request->input('session_uuid'),
                $request->input('resolution_strategy')
            );
            return response()->json([
                'success' => true,
                'status' => $res['status'],
                'total' => $res['total'],
                'success_count' => $res['success'],
                'errors' => $res['errors']
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Rollback the import session (undo).
     */
    public function rollback(string $module, Request $request)
    {
        $request->validate([
            'session_uuid' => 'required|string|uuid',
        ]);

        try {
            $this->importEngine->rollback($request->input('session_uuid'));
            return response()->json([
                'success' => true,
                'message' => 'Successfully rolled back the import session.'
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Get real-time progress details.
     */
    public function progress(string $module, string $uuid)
    {
        $session = ImportSession::where('uuid', $uuid)->first();
        if (!$session) {
            return response()->json(['success' => false, 'message' => 'Session not found'], 404);
        }

        return response()->json([
            'success' => true,
            'status' => $session->status,
            'total_rows' => $session->total_rows,
            'processed_rows' => $session->processed_rows,
            'success_rows' => $session->success_rows,
            'error_rows' => $session->error_rows,
        ]);
    }

    /**
     * Download the error log as CSV.
     */
    public function downloadErrors(string $module, string $uuid)
    {
        $session = ImportSession::where('uuid', $uuid)->firstOrFail();
        $errors = $session->errors()->orderBy('row_number')->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="import-errors-' . $uuid . '.csv"',
        ];

        $callback = function () use ($session, $errors) {
            $file = fopen('php://output', 'w');
            
            // Add BOM for UTF-8 compatibility
            fwrite($file, "\xEF\xBB\xBF");

            // Define CSV Headers
            $csvHeaders = array_merge(['Row Number'], $session->settings['headers'] ?? [], ['Error Message']);
            fputcsv($file, $csvHeaders);

            foreach ($errors as $error) {
                $rawRow = $error->raw_row_data;
                $rowArray = is_array($rawRow) ? $rawRow : json_decode((string)$rawRow, true);
                
                $rowLine = array_merge([$error->row_number], $rowArray ?? [], [$error->error_message]);
                fputcsv($file, $rowLine);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Download dynamic CSV template for the given module based on its ImportDefinition.
     */
    public function downloadTemplate(string $module)
    {
        // Enforce admin permission check
        $user = auth()->user();
        if (!$user || !$user->hasRole('admin') && !$user->hasRole('super-admin')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $definition = $this->importEngine->getDefinition($module);
            $headers = $definition->getTemplateHeaders();

            $outputHeaders = [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="' . $module . '-template.csv"',
            ];

            $callback = function () use ($headers) {
                $file = fopen('php://output', 'w');
                // Add BOM for UTF-8 compatibility
                fwrite($file, "\xEF\xBB\xBF");
                fputcsv($file, $headers);
                fclose($file);
            };

            return response()->stream($callback, 200, $outputHeaders);
        } catch (\Throwable $e) {
            abort(404, 'Template not found for module: ' . $module);
        }
    }

    /**
     * Save a row correction to the import session settings.
     */
    public function correctRow(string $module, Request $request)
    {
        $request->validate([
            'session_uuid' => 'required|string|uuid',
            'row_number' => 'required|integer',
            'corrections' => 'required|array',
        ]);

        try {
            $session = ImportSession::where('uuid', $request->input('session_uuid'))->firstOrFail();
            
            $settings = $session->settings ?? [];
            $allCorrections = $settings['corrections'] ?? [];
            
            $rowNum = $request->input('row_number');
            $rowCorrections = $request->input('corrections');

            $allCorrections[$rowNum] = array_merge($allCorrections[$rowNum] ?? [], $rowCorrections);
            
            $settings['corrections'] = $allCorrections;
            
            $session->settings = $settings;
            $session->save();

            return response()->json([
                'success' => true,
                'message' => "Successfully saved correction for Row {$rowNum}."
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
}

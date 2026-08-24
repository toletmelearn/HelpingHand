<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Backup;
use App\Services\Backup\DatabaseBackupService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class BackupController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $this->authorize('viewAny', Backup::class);

        $backups = Backup::with('creator')->latest()->paginate(20);

        return view('admin.backups.index', compact('backups'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $this->authorize('create', Backup::class);

        return view('admin.backups.create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * V1 only supports a real database backup stored locally -- "full"/
     * "files" and "cloud" are deliberately not accepted here rather than
     * silently accepted and doing nothing, which is exactly the false-
     * success problem this fix replaces.
     */
    public function store(Request $request, DatabaseBackupService $service)
    {
        $this->authorize('create', Backup::class);

        $request->validate([
            'type' => 'required|in:database',
            'location' => 'required|in:local',
            'notes' => 'nullable|string',
        ]);

        $backup = Backup::create([
            'filename' => 'backup_' . now()->format('Y-m-d_H-i-s') . '_' . Str::random(8) . '.zip',
            'path' => 'backups/' . now()->format('Y/m/d'),
            'type' => $request->type,
            'location' => $request->location,
            'size' => 0,
            'status' => 'pending',
            'notes' => $request->notes,
            'created_by' => Auth::id(),
        ]);

        try {
            $service->create($backup);
        } catch (\Throwable $e) {
            return redirect()->route('admin.backups.index')
                ->with('error', 'Backup failed: ' . $e->getMessage());
        }

        $service->pruneOldBackups((int) config('backup.retention_count', 14));

        return redirect()->route('admin.backups.index')
            ->with('success', 'Backup completed successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Backup $backup)
    {
        $this->authorize('view', $backup);

        $backup->load('creator');

        return view('admin.backups.show', compact('backup'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Backup $backup, DatabaseBackupService $service)
    {
        $this->authorize('delete', $backup);

        try {
            $service->delete($backup);

            return redirect()->route('admin.backups.index')
                ->with('success', 'Backup deleted successfully.');
        } catch (\Throwable $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to delete backup: ' . $e->getMessage()]);
        }
    }

    /**
     * Download the backup file.
     */
    public function download(Backup $backup, DatabaseBackupService $service)
    {
        $this->authorize('download', $backup);

        $filePath = $service->filePathFor($backup);
        $backupsRoot = realpath(storage_path('app/backups'));
        $resolved = realpath($filePath);

        // Defense in depth: even though filename/path always come from the
        // Backup record (never raw user input), refuse to serve anything
        // that doesn't resolve inside the backups directory.
        if ($resolved === false || $backupsRoot === false || ! str_starts_with($resolved, $backupsRoot)) {
            abort(404);
        }

        if (! file_exists($resolved)) {
            return redirect()->back()->withErrors(['error' => 'Backup file not found.']);
        }

        return response()->download($resolved, $backup->filename);
    }

    /**
     * Schedule a backup for a future date. Still a write path that creates
     * a Backup record, so it falls under the same admin-only gate as
     * creating one directly.
     */
    public function schedule(Request $request)
    {
        $this->authorize('create', Backup::class);

        $request->validate([
            'type' => 'required|in:database',
            'location' => 'required|in:local',
            'schedule_date' => 'required|date',
            'notes' => 'nullable|string',
        ]);

        Backup::create([
            'filename' => 'scheduled_backup_' . now()->format('Y-m-d_H-i-s') . '_' . Str::random(8) . '.zip',
            'path' => 'backups/scheduled/' . now()->format('Y/m/d'),
            'type' => $request->type,
            'location' => $request->location,
            'size' => 0,
            'status' => 'pending',
            'notes' => $request->notes,
            'created_by' => Auth::id(),
            'scheduled_at' => $request->schedule_date,
        ]);

        return redirect()->route('admin.backups.index')
            ->with('success', 'Backup scheduled successfully.');
    }
}

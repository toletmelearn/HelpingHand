<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Backup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Exception;

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
        $backups = Backup::with('creator')->latest()->paginate(20);
        
        return view('admin.backups.index', compact('backups'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('admin.backups.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'type' => 'required|in:full,database,files',
            'location' => 'required|in:local,cloud',
            'notes' => 'nullable|string',
        ]);
        
        // Create backup record
        $backup = Backup::create([
            'filename' => 'backup_' . date('Y-m-d_H-i-s') . '.zip',
            'path' => 'backups/' . date('Y/m/d') . '/',
            'type' => $request->type,
            'location' => $request->location,
            'status' => 'pending',
            'notes' => $request->notes,
            'created_by' => Auth::id(),
        ]);
        
        // For now, we'll simulate the backup process
        // In a real implementation, you'd use Laravel's queue system
        try {
            // Simulate backup process
            $exitCode = Artisan::call('config:cache'); // Just a sample command to simulate
            
            $backup->update([
                'status' => 'completed',
                'completed_at' => now(),
                'size' => rand(1000000, 5000000), // Random size for demo
            ]);
        } catch (Exception $e) {
            $backup->update(['status' => 'failed']);
        }
        
        return redirect()->route('admin.backups.index')
                         ->with('success', 'Backup process started successfully. Please check status later.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Backup $backup)
    {
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
    public function destroy(Backup $backup)
    {
        try {
            // Delete the backup file
            Storage::disk('local')->delete($backup->path . $backup->filename);
            
            // Delete the database record
            $backup->delete();
            
            return redirect()->route('admin.backups.index')
                             ->with('success', 'Backup deleted successfully.');
        } catch (Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to delete backup: ' . $e->getMessage()]);
        }
    }
    
    /**
     * Download the backup file
     */
    public function download(Backup $backup)
    {
        $filePath = storage_path('app/' . $backup->path . $backup->filename);
        
        if (!file_exists($filePath)) {
            return redirect()->back()->withErrors(['error' => 'Backup file not found.']);
        }
        
        return response()->download($filePath, $backup->filename);
    }
    
    /**
     * Create a manual backup
     */
    public function createManual(Request $request)
    {
        $request->validate([
            'type' => 'required|in:full,database,files',
            'location' => 'required|in:local,cloud',
            'notes' => 'nullable|string',
        ]);
        
        // Create backup record
        $backup = Backup::create([
            'filename' => 'manual_backup_' . date('Y-m-d_H-i-s') . '.zip',
            'path' => 'backups/manual/' . date('Y/m/d') . '/',
            'type' => $request->type,
            'location' => $request->location,
            'status' => 'pending',
            'notes' => $request->notes,
            'created_by' => Auth::id(),
        ]);
        
        // Perform backup synchronously for manual backup
        try {
            $exitCode = Artisan::call('backup:run', [
                '--only-db' => $request->type === 'database',
                '--only-files' => $request->type === 'files',
            ]);
            
            $backup->update([
                'status' => $exitCode === 0 ? 'completed' : 'failed',
                'completed_at' => now(),
                'size' => filesize(storage_path('app/' . $backup->path . $backup->filename)),
            ]);
            
            return redirect()->route('admin.backups.index')
                             ->with('success', 'Manual backup created successfully.');
        } catch (Exception $e) {
            $backup->update(['status' => 'failed']);
            
            return redirect()->back()->withErrors(['error' => 'Failed to create backup: ' . $e->getMessage()]);
        }
    }
    
    /**
     * Schedule a backup
     */
    public function schedule(Request $request)
    {
        $request->validate([
            'type' => 'required|in:full,database,files',
            'location' => 'required|in:local,cloud',
            'schedule_date' => 'required|date',
            'notes' => 'nullable|string',
        ]);
        
        // Create scheduled backup record
        Backup::create([
            'filename' => 'scheduled_backup_' . date('Y-m-d_H-i-s') . '.zip',
            'path' => 'backups/scheduled/' . date('Y/m/d') . '/',
            'type' => $request->type,
            'location' => $request->location,
            'status' => 'pending',
            'notes' => $request->notes,
            'created_by' => Auth::id(),
            'scheduled_at' => $request->schedule_date,
        ]);
        
        return redirect()->route('admin.backups.index')
                         ->with('success', 'Backup scheduled successfully.');
    }
}

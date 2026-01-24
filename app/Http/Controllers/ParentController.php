<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Student;
use App\Models\Attendance;
use App\Models\Result;
use App\Models\Fee;
use App\Models\User;

class ParentController extends Controller
{
    /**
     * Display parent dashboard
     */
    public function index()
    {
        $user = auth()->user();
        
        // Get children associated with this parent
        $children = $this->getChildren($user);
        
        // Prepare data for dashboard
        $dashboardData = [];
        foreach ($children as $child) {
            $dashboardData[$child->id] = [
                'name' => $child->name,
                'class' => $child->class,
                'roll_number' => $child->roll_number,
                'attendance_percentage' => $this->getChildAttendancePercentage($child),
                'latest_result' => $this->getChildLatestResult($child),
                'pending_fees' => $this->getChildPendingFees($child)
            ];
        }
        
        return view('parents.dashboard', compact('dashboardData', 'children'));
    }
    
    /**
     * View child details
     */
    public function viewChild($id)
    {
        $user = auth()->user();
        $child = $this->getSpecificChild($user, $id);
        
        if (!$child) {
            abort(404, 'Child not found');
        }
        
        // Calculate attendance stats
        $totalDays = $child->attendances->count();
        $presentDays = $child->attendances->where('status', 'present')->count();
        $absentDays = $totalDays - $presentDays;
        $attendancePercentage = $totalDays > 0 ? round(($presentDays / $totalDays) * 100, 2) : 0;
        
        $attendanceStats = [
            'total_days' => $totalDays,
            'present_days' => $presentDays,
            'absent_days' => $absentDays,
            'percentage' => $attendancePercentage
        ];
        
        // Get recent attendances
        $recentAttendances = $child->attendances()->latest()->limit(10)->get();
        
        // Get all results
        $results = $child->results()->with('exam')->get();
        
        // Get all fees
        $fees = $child->fees()->get();
        
        return view('parents.child-details', compact('child', 'attendanceStats', 'recentAttendances', 'results', 'fees'));
    }
    
    /**
     * Get attendance for a specific child
     */
    public function getChildAttendance($id)
    {
        $user = auth()->user();
        $child = $this->getSpecificChild($user, $id);
        
        if (!$child) {
            abort(404, 'Child not found');
        }
        
        $attendanceRecords = $child->attendances()->latest()->paginate(20);
        
        return view('parents.attendance', compact('child', 'attendanceRecords'));
    }
    
    /**
     * Get results for a specific child
     */
    public function getChildResults($id)
    {
        $user = auth()->user();
        $child = $this->getSpecificChild($user, $id);
        
        if (!$child) {
            abort(404, 'Child not found');
        }
        
        $results = $child->results()->latest()->paginate(20);
        
        return view('parents.results', compact('child', 'results'));
    }
    
    /**
     * Get fee status for a specific child
     */
    public function getChildFees($id)
    {
        $user = auth()->user();
        $child = $this->getSpecificChild($user, $id);
        
        if (!$child) {
            abort(404, 'Child not found');
        }
        
        $fees = $child->fees()->latest()->paginate(20);
        
        return view('parents.fees', compact('child', 'fees'));
    }
    
    /**
     * Helper method to get children associated with parent
     */
    private function getChildren($user)
    {
        // In a real implementation, this would connect parents to their children
        // For now, we'll return all students for demo purposes
        // This should be replaced with proper parent-child relationship
        return Student::all();
    }
    
    /**
     * Helper method to get a specific child
     */
    private function getSpecificChild($user, $id)
    {
        // In a real implementation, this would check if the child belongs to the parent
        // For now, we'll return the student directly
        return Student::find($id);
    }
    
    /**
     * Calculate child's attendance percentage
     */
    private function getChildAttendancePercentage($child)
    {
        $totalDays = $child->attendances->count();
        if ($totalDays == 0) return 0;
        
        $presentDays = $child->attendances->where('status', 'present')->count();
        return round(($presentDays / $totalDays) * 100, 2);
    }
    
    /**
     * Get child's latest result
     */
    private function getChildLatestResult($child)
    {
        return $child->results()->latest()->first();
    }
    
    /**
     * Get pending fees for child
     */
    private function getChildPendingFees($child)
    {
        return $child->fees()->where(function($query) {
            $query->where('status', 'pending')->orWhere('status', 'overdue');
        })->sum('due_amount');
    }
}

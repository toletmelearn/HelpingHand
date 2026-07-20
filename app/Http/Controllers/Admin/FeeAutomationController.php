<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Student;
use App\Models\FeeCollection;
use App\Models\FeeStructure;
use App\Models\FeeStructureItem;
use App\Models\StudentFeeAssignment;
use App\Models\SchoolClass;
use Carbon\Carbon;

class FeeAutomationController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function pendingFees()
    {
        // Get students with pending fees
        $pendingFees = $this->calculatePendingFees();
        
        return view('admin.fees.pending', compact('pendingFees'));
    }

    public function defaulters()
    {
        // Get defaulter students (2+ months unpaid)
        $defaulters = $this->calculateDefaulters();
        
        return view('admin.fees.defaulters', compact('defaulters'));
    }

    public function feeDashboard()
    {
        return app(\App\Http\Controllers\Admin\AccountantDashboardController::class)->index();
    }

    private function calculatePendingFees()
    {
        $unpaidLedgerItems = \App\Models\StudentFeeLedger::with(['student.schoolClass', 'feeType'])
            ->where('debit', '>', 0)
            ->where('unpaid_amount', '>', 0)
            ->get();

        $pendingFees = [];

        foreach ($unpaidLedgerItems as $item) {
            $student = $item->student;
            if (!$student) continue;

            $pendingFees[] = [
                'student' => $student,
                'class' => $student->schoolClass ? $student->schoolClass->name : 'N/A',
                'mobile' => $student->mobile ?: 'N/A',
                'pending_amount' => (float)$item->unpaid_amount,
                'month' => $item->date ? $item->date->format('F Y') : 'N/A',
                'due_date' => $item->date ? $item->date->format('Y-m-d') : 'N/A',
                'fee_type' => $item->feeType ? $item->feeType->name : 'N/A',
                'date' => $item->date
            ];
        }

        return collect($pendingFees);
    }

    private function calculateDefaulters()
    {
        $now = now();
        $ledgers = \App\Models\StudentFeeLedger::with(['student.schoolClass'])
            ->where('debit', '>', 0)
            ->where('unpaid_amount', '>', 0)
            ->get();

        $grouped = $ledgers->groupBy('student_id');
        $defaulterData = [];

        foreach ($grouped as $studentId => $items) {
            $student = $items->first()->student;
            if (!$student) continue;

            $totalPending = $items->sum('unpaid_amount');

            // Ageing buckets
            $bucket30 = 0;
            $bucket60 = 0;
            $bucket90 = 0;
            $bucket120 = 0;

            foreach ($items as $item) {
                $days = $item->date ? $now->diffInDays($item->date) : 0;
                $amt = (float)$item->unpaid_amount;

                if ($days <= 30) {
                    $bucket30 += $amt;
                } elseif ($days <= 60) {
                    $bucket60 += $amt;
                } elseif ($days <= 90) {
                    $bucket90 += $amt;
                } else {
                    $bucket120 += $amt;
                }
            }

            $defaulterData[] = [
                'student' => $student,
                'class' => $student->schoolClass ? $student->schoolClass->name : 'N/A',
                'mobile' => $student->mobile ?: 'N/A',
                'pending_amount' => $totalPending,
                'months_due' => $items->pluck('date')->map(fn($d) => $d ? $d->format('Y-m') : null)->unique()->count(),
                'father_name' => $student->father_name ?: $student->guardian_name ?: 'N/A',
                'ageing' => [
                    '30d' => $bucket30,
                    '60d' => $bucket60,
                    '90d' => $bucket90,
                    '120d_plus' => $bucket120
                ]
            ];
        }

        return collect($defaulterData)->sortByDesc('pending_amount');
    }

    private function calculateTotalPending()
    {
        $pendingFees = $this->calculatePendingFees();
        return $pendingFees->sum('pending_amount');
    }

    private function calculateDefaulterCount()
    {
        return $this->calculateDefaulters()->count();
    }

    private function getMonthlyChartData()
    {
        $metricsService = new \App\Services\FinanceMetricsService();
        $data = [];
        for ($i = 11; $i >= 0; $i--) {
            $month = Carbon::now()->subMonths($i);
            $amount = $metricsService->getNetCollection(
                $month->copy()->startOfMonth()->toDateString(),
                $month->copy()->endOfMonth()->toDateString()
            );
                
            $data[] = [
                'month' => $month->format('M Y'),
                'amount' => $amount
            ];
        }
        
        return $data;
    }

    private function getClassWiseData()
    {
        $metricsService = new \App\Services\FinanceMetricsService();
        $classes = SchoolClass::active()->get();
        $data = [];
        
        foreach ($classes as $class) {
            $amount = $metricsService->getNetCollection(null, null, $class->id);
            
            $data[] = [
                'class' => $class->name,
                'amount' => $amount
            ];
        }
        
        return $data;
    }

    private function generateMonths($frequency)
    {
        switch ($frequency) {
            case 'monthly':
                return ['April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December', 'January', 'February', 'March'];
            case 'quarterly':
                return ['Q1', 'Q2', 'Q3', 'Q4'];
            case 'yearly':
                return ['Annual'];
            default:
                return ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        }
    }

    private function getDueDate($month, $structure)
    {
        // Return a calculated due date based on month and structure
        return Carbon::now()->format('Y-m-d');
    }

    public function sendWhatsappReminder(Request $request)
    {
        $studentId = $request->student_id;
        $student = Student::withTrashed()->with(['schoolClass'])->findOrFail($studentId);
        
        // Calculate pending amount for this student
        $pendingFees = $this->calculatePendingFees()
            ->where('student.id', $studentId);
        
        $totalPending = $pendingFees->sum('pending_amount');
        $months = $pendingFees->pluck('month')->unique()->implode(', ');
        
        // Generate WhatsApp message
        $message = "Dear Parent,\nThis is to inform you that fee of ₹{$totalPending}\nfor {$student->name} (Class {$student->schoolClass->name})\nis pending for {$months}.\n\nKindly pay soon.\n\nHelpingHand Public School";
        
        return response()->json([
            'success' => true,
            'message' => $message,
            'whatsapp_url' => "https://wa.me/{$student->mobile}?text=" . urlencode($message)
        ]);
    }
}
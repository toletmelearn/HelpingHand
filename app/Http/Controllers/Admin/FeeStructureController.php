<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\FeeStructure;
use App\Models\FeeType;
use App\Models\Student;
use App\Models\SchoolClass;
use App\Models\StudentFeeAssignment;
use App\Models\FeeStructureItem;
use App\Models\AcademicSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class FeeStructureController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $feeStructures = FeeStructure::with(['feeStructureItems.feeType'])
            ->orderBy('class_name')
            ->orderBy('academic_year')
            ->get();
        $classes = SchoolClass::active()->orderByOrder()->get();

        return view('admin.fee-structures.index', compact('feeStructures', 'classes'));
    }

    public function create()
    {
        $feeTypes = FeeType::active()->get();
        $classes = SchoolClass::active()->orderByOrder()->get();
        $lateFeeRules = \App\Models\LateFeeRule::all();
        $academicSessions = $this->academicSessionsList();

        return view('admin.fee-structures.create', compact('feeTypes', 'classes', 'lateFeeRules', 'academicSessions'));
    }

    public function store(Request $request)
    {
        try {
            DB::beginTransaction();
    
            $request->validate([
                'class_name' => 'required|string',
                'academic_year' => $this->academicYearRule(),
                'frequency' => 'required|in:monthly,quarterly,yearly,custom',
                'fee_type_id.*' => 'required|exists:fee_types,id',
                'amount.*' => 'required|numeric|min:0',
                'due_day.*' => 'nullable|integer|min:1|max:31',
                'billing_frequency.*' => 'nullable|string',
                'charge_months_raw.*' => 'nullable|string',
                'late_fee_rule_id.*' => 'nullable|exists:late_fee_rules,id',
                'exam_id.*' => 'nullable|integer',
                'installments_raw.*' => 'nullable|string'
            ]);
    
            // Check if structure already exists for this class and academic year
            $existing = FeeStructure::where('class_name', $request->class_name)
                ->where('academic_year', $request->academic_year)
                ->first();
                    
            if ($existing) {
                return redirect()->back()
                    ->with('error', 'Fee structure already exists for this class and academic year.')
                    ->withInput();
            }
    
            $feeStructure = FeeStructure::create([
                'class_name' => $request->class_name,
                'academic_year' => $request->academic_year,
                'frequency' => $request->frequency,
                'status' => 'active'
            ]);
    
            foreach ($request->fee_type_id as $index => $feeType) {
                $billingFreq = $request->billing_frequency[$index] ?? 'monthly';
                $monthsRaw = $request->charge_months_raw[$index] ?? '';
                $months = !empty($monthsRaw) ? explode(',', $monthsRaw) : [];

                if (empty($months)) {
                    if ($billingFreq === 'monthly') {
                        $months = ['April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December', 'January', 'February', 'March'];
                    } elseif ($billingFreq === 'quarterly') {
                        $months = ['Q1', 'Q2', 'Q3', 'Q4'];
                    } elseif ($billingFreq === 'yearly' || $billingFreq === 'annual') {
                        $months = ['Annual'];
                    } else {
                        $months = ['Annual'];
                    }
                }

                $item = FeeStructureItem::create([
                    'fee_structure_id' => $feeStructure->id,
                    'fee_type_id' => $feeType,
                    'amount' => $request->amount[$index],
                    'due_day' => $request->due_day[$index] ?? null,
                    'billing_frequency' => $billingFreq,
                    'charge_months' => $months,
                    'late_fee_rule_id' => $request->late_fee_rule_id[$index] ?? null,
                    'exam_id' => $request->exam_id[$index] ?? null
                ]);

                $instRaw = $request->installments_raw[$index] ?? '';
                if (!empty($instRaw)) {
                    $parts = explode(';', $instRaw);
                    foreach ($parts as $part) {
                        $subparts = explode(':', $part);
                        if (count($subparts) === 2) {
                            $item->installments()->create([
                                'month' => trim($subparts[0]),
                                'amount' => floatval(trim($subparts[1]))
                            ]);
                        }
                    }
                }
            }
    
            // Auto-assign to students of this class
            $this->autoAssignToStudents($feeStructure);
    
            DB::commit();
    
            return redirect()->route('admin.fee-structures.index')
                ->with('success', 'Fee structure created successfully!');
    
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()
                ->with('error', 'Failed to create fee structure: ' . $e->getMessage())
                ->withInput();
        }
    }

    public function edit($id)
    {
        $feeStructure = FeeStructure::with('feeStructureItems')->findOrFail($id);
        $feeTypes = FeeType::active()->get();
        $classes = SchoolClass::active()->orderByOrder()->get();
        $lateFeeRules = \App\Models\LateFeeRule::all();
        $academicSessions = $this->academicSessionsList();

        return view('admin.fee-structures.edit', compact('feeStructure', 'feeTypes', 'classes', 'lateFeeRules', 'academicSessions'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'class_name' => 'required|string',
            'academic_year' => $this->academicYearRule(),
            'frequency' => 'required|in:monthly,quarterly,yearly,custom',
            'status' => 'required|in:active,inactive',
            'fee_heads' => 'required|array|min:1',
            'fee_heads.*.fee_type_id' => 'required|exists:fee_types,id',
            'fee_heads.*.amount' => 'required|numeric|min:0',
            'fee_heads.*.due_day' => 'nullable|integer|min:1|max:31',
            'fee_heads.*.billing_frequency' => 'nullable|string',
            'fee_heads.*.charge_months_raw' => 'nullable|string',
            'fee_heads.*.late_fee_rule_id' => 'nullable|exists:late_fee_rules,id',
            'fee_heads.*.exam_id' => 'nullable|integer',
            'fee_heads.*.installments_raw' => 'nullable|string'
        ]);

        $feeStructure = FeeStructure::findOrFail($id);

        DB::beginTransaction();
        
        try {
            $feeStructure->update([
                'class_name' => $request->class_name,
                'academic_year' => $request->academic_year,
                'frequency' => $request->frequency,
                'status' => $request->status
            ]);

            // Delete existing fee structure items
            $feeStructure->feeStructureItems()->delete();

            // Create new fee structure items
            foreach ($request->fee_heads as $feeHead) {
                $billingFreq = $feeHead['billing_frequency'] ?? 'monthly';
                $monthsRaw = $feeHead['charge_months_raw'] ?? '';
                $months = !empty($monthsRaw) ? explode(',', $monthsRaw) : [];

                if (empty($months)) {
                    if ($billingFreq === 'monthly') {
                        $months = ['April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December', 'January', 'February', 'March'];
                    } elseif ($billingFreq === 'quarterly') {
                        $months = ['Q1', 'Q2', 'Q3', 'Q4'];
                    } elseif ($billingFreq === 'yearly' || $billingFreq === 'annual') {
                        $months = ['Annual'];
                    } else {
                        $months = ['Annual'];
                    }
                }

                $item = FeeStructureItem::create([
                    'fee_structure_id' => $feeStructure->id,
                    'fee_type_id' => $feeHead['fee_type_id'],
                    'amount' => $feeHead['amount'],
                    'due_day' => $feeHead['due_day'] ?? null,
                    'billing_frequency' => $billingFreq,
                    'charge_months' => $months,
                    'late_fee_rule_id' => $feeHead['late_fee_rule_id'] ?? null,
                    'exam_id' => $feeHead['exam_id'] ?? null
                ]);

                $instRaw = $feeHead['installments_raw'] ?? '';
                if (!empty($instRaw)) {
                    $parts = explode(';', $instRaw);
                    foreach ($parts as $part) {
                        $subparts = explode(':', $part);
                        if (count($subparts) === 2) {
                            $item->installments()->create([
                                'month' => trim($subparts[0]),
                                'amount' => floatval(trim($subparts[1]))
                            ]);
                        }
                    }
                }
            }

            DB::commit();

            return redirect()->route('admin.fee-structures.index')
                ->with('success', 'Fee structure updated successfully!');
        } catch (\Exception $e) {
            DB::rollback();
            return redirect()->back()
                ->with('error', 'Failed to update fee structure: ' . $e->getMessage())
                ->withInput();
        }
    }

    public function destroy($id)
    {
        $feeStructure = FeeStructure::findOrFail($id);

        // Check if any fee has been collected for this structure
        $hasCollections = $feeStructure->feeCollections()->exists();
        
        if ($hasCollections) {
            return redirect()->back()
                ->with('error', 'Cannot delete fee structure as fee collections exist for it. Please deactivate instead.');
        }

        $feeStructure->delete();

        return redirect()->route('admin.fee-structures.index')
            ->with('success', 'Fee structure deleted successfully!');
    }

    public function activate($id)
    {
        $feeStructure = FeeStructure::findOrFail($id);
        $feeStructure->update(['status' => 'active']);
        
        return redirect()->back()->with('success', 'Fee structure activated successfully!');
    }

    public function deactivate($id)
    {
        $feeStructure = FeeStructure::findOrFail($id);
        $feeStructure->update(['status' => 'inactive']);
        
        return redirect()->back()->with('success', 'Fee structure deactivated successfully!');
    }

    public function show($id)
    {
        $feeStructure = FeeStructure::with(['feeStructureItems.feeType', 'feeStructureItems.installments'])->findOrFail($id);
        
        $data = [
            'id' => $feeStructure->id,
            'class_name' => $feeStructure->class_name,
            'academic_year' => $feeStructure->academic_year,
            'frequency' => $feeStructure->frequency,
            'status' => $feeStructure->status,
            'total_amount' => $feeStructure->total_amount,
            'created_at' => $feeStructure->created_at,
            'updated_at' => $feeStructure->updated_at,
            'fee_items' => $feeStructure->feeStructureItems->map(function($item) {
                return [
                    'id' => $item->id,
                    'fee_type' => [
                        'id' => $item->feeType->id,
                        'name' => $item->feeType->name,
                        'category' => $item->feeType->category ?? 'recurring'
                    ],
                    'amount' => $item->amount,
                    'due_day' => $item->due_day,
                    'billing_frequency' => $item->billing_frequency,
                    'charge_months' => $item->charge_months,
                    'late_fee_rule_id' => $item->late_fee_rule_id,
                    'exam_id' => $item->exam_id,
                    'installments' => $item->installments->map(function($inst) {
                        return [
                            'month' => $inst->month,
                            'amount' => floatval($inst->amount)
                        ];
                    })
                ];
            })
        ];
        
        return response()->json($data);
    }

    public function feeTypeMaster()
    {
        $feeTypes = FeeType::active()->get();
        $lateFeeRules = \App\Models\LateFeeRule::all();
        return view('admin.fee-structures.master', compact('feeTypes', 'lateFeeRules'));
    }

    public function updateFeeTypeMaster(Request $request)
    {
        $request->validate([
            'default_frequency' => 'required|array',
            'default_frequency.*' => 'required|string',
            'default_charge_months_raw' => 'nullable|array',
            'default_late_fee_rule_id' => 'nullable|array',
        ]);

        DB::beginTransaction();
        try {
            foreach ($request->default_frequency as $id => $frequency) {
                $feeType = FeeType::findOrFail($id);
                $monthsRaw = $request->default_charge_months_raw[$id] ?? '';
                $months = !empty($monthsRaw) ? explode(',', $monthsRaw) : [];

                $feeType->update([
                    'default_frequency' => $frequency,
                    'default_charge_months' => $months,
                    'default_late_fee_rule_id' => $request->default_late_fee_rule_id[$id] ?: null
                ]);
            }

            DB::commit();
            return redirect()->route('admin.fee-structures.index')
                ->with('success', 'Fee Type Master defaults updated successfully!');
        } catch (\Exception $e) {
            DB::rollback();
            return redirect()->back()
                ->with('error', 'Failed to update Fee Type Master defaults: ' . $e->getMessage());
        }
    }

    public function copyStructure(Request $request)
    {
        $request->validate([
            'source_structure_id' => 'required|exists:fee_structures,id',
            'target_class_name' => 'required|string',
            'target_academic_year' => $this->academicYearRule(),
            'percentage_increase' => 'nullable|numeric|min:-100|max:1000'
        ]);

        // Check if target structure already exists
        $existing = FeeStructure::where('class_name', $request->target_class_name)
            ->where('academic_year', $request->target_academic_year)
            ->first();

        if ($existing) {
            return redirect()->back()
                ->with('error', 'A fee structure already exists for the target class and academic year.');
        }

        $source = FeeStructure::with('feeStructureItems.installments')->findOrFail($request->source_structure_id);
        $pctMultiplier = 1 + (($request->percentage_increase ?? 0) / 100);

        DB::beginTransaction();
        try {
            $targetStructure = FeeStructure::create([
                'class_name' => $request->target_class_name,
                'academic_year' => $request->target_academic_year,
                'frequency' => $source->frequency,
                'status' => 'active'
            ]);

            foreach ($source->feeStructureItems as $item) {
                $newAmount = round($item->amount * $pctMultiplier, 2);

                $targetItem = FeeStructureItem::create([
                    'fee_structure_id' => $targetStructure->id,
                    'fee_type_id' => $item->fee_type_id,
                    'amount' => $newAmount,
                    'due_day' => $item->due_day,
                    'billing_frequency' => $item->billing_frequency,
                    'charge_months' => $item->charge_months,
                    'late_fee_rule_id' => $item->late_fee_rule_id,
                    'exam_id' => $item->exam_id
                ]);

                foreach ($item->installments as $inst) {
                    $newInstAmount = round($inst->amount * $pctMultiplier, 2);
                    $targetItem->installments()->create([
                        'month' => $inst->month,
                        'amount' => $newInstAmount
                    ]);
                }
            }

            // Auto-assign to students of target class
            $this->autoAssignToStudents($targetStructure);

            DB::commit();
            return redirect()->route('admin.fee-structures.index')
                ->with('success', 'Fee structure copied successfully!');
        } catch (\Exception $e) {
            DB::rollback();
            return redirect()->back()
                ->with('error', 'Failed to copy fee structure: ' . $e->getMessage());
        }
    }

    /**
     * Real AcademicSession codes (e.g. "2025-2026") and this field's own
     * historical default (date('Y').'-'.substr(date('Y')+1,-2), e.g.
     * "2026-27") use different formats -- a free-text academic_year field
     * let that drift happen silently, and BulkFeeAssignmentService's
     * session lookup (which gates the new-vs-continuing admission-fee
     * logic) would then never match, silently disabling that protection.
     * Getting the list of real sessions for a dropdown, and validating
     * against it, closes both the UI and the API entry points to that drift.
     */
    private function academicSessionsList()
    {
        return Schema::hasTable('academic_sessions') ? AcademicSession::orderByDesc('id')->get() : collect();
    }

    private function academicYearRule(): array
    {
        $sessions = $this->academicSessionsList();
        if ($sessions->isEmpty()) {
            // No sessions configured yet for this school -- don't block fee
            // structure creation on a setup step that hasn't happened.
            return ['required', 'string', 'max:9'];
        }

        $validValues = $sessions->pluck('code')->merge($sessions->pluck('name'))->filter()->unique()->values()->all();
        return ['required', 'string', Rule::in($validValues)];
    }

    private function autoAssignToStudents($feeStructure)
    {
        // Get class ID from class name
        $schoolClass = SchoolClass::where('name', $feeStructure->class_name)->first();
        
        if (!$schoolClass) {
            return; // Class not found, skip assignment
        }

        // Get all students in this class
        $studentIds = Student::where(function($q) use ($schoolClass) {
            $q->where('class_id', $schoolClass->id)
              ->orWhere('school_class_id', $schoolClass->id);
        })
        ->pluck('id')
        ->toArray();

        if (!empty($studentIds)) {
            \App\Services\BulkFeeAssignmentService::bulkAssign($feeStructure, $studentIds);
        }
    }
}
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdvanceRebateRule;
use App\Models\FeeType;
use App\Models\Student;
use App\Models\StudentAdvanceRebate;
use App\Services\AdvanceRebateService;
use App\Services\LedgerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Rule creation and manual overrides are a financial-policy decision, per
 * the "admin/principal role" requirement (no separate principal role
 * exists; admin satisfies it alone). Gated by permission rather than a
 * hardcoded role: only admin holds view-advance-rebate-rules/
 * manage-advance-rebate-rules by default (identical behavior to the old
 * role:admin-only check), but the admin can now delegate this duty to
 * another role via Manage Permissions if they choose to.
 */
class AdvanceRebateRuleController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:view-advance-rebate-rules')->only(['index']);
        $this->middleware('permission:manage-advance-rebate-rules')->only(['create', 'store', 'edit', 'update', 'destroy', 'manualOverride']);
    }

    public function index()
    {
        $rules = AdvanceRebateRule::orderBy('name')->get();

        return view('admin.advance-rebate-rules.index', compact('rules'));
    }

    public function create()
    {
        $feeTypes = FeeType::active()->get();

        return view('admin.advance-rebate-rules.create', compact('feeTypes'));
    }

    public function store(Request $request)
    {
        $validated = $this->validateRule($request);
        $validated['created_by'] = Auth::id();

        AdvanceRebateRule::create($validated);

        return redirect()->route('admin.advance-rebate-rules.index')->with('success', 'Advance rebate rule created.');
    }

    public function edit(AdvanceRebateRule $advanceRebateRule)
    {
        $feeTypes = FeeType::active()->get();

        return view('admin.advance-rebate-rules.edit', ['rule' => $advanceRebateRule, 'feeTypes' => $feeTypes]);
    }

    public function update(Request $request, AdvanceRebateRule $advanceRebateRule)
    {
        $validated = $this->validateRule($request);
        $validated['updated_by'] = Auth::id();

        $advanceRebateRule->update($validated);

        return redirect()->route('admin.advance-rebate-rules.index')->with('success', 'Advance rebate rule updated.');
    }

    public function destroy(AdvanceRebateRule $advanceRebateRule)
    {
        $advanceRebateRule->delete();

        return redirect()->route('admin.advance-rebate-rules.index')->with('success', 'Advance rebate rule deleted.');
    }

    /**
     * Admin-forced apply/cancel/adjust for a specific student, outside the
     * automatic cutoff/coverage criteria -- always stamps approved_by.
     */
    public function manualOverride(Request $request, Student $student)
    {
        $validated = $request->validate([
            'action' => 'required|in:apply,cancel,adjust',
            'advance_rebate_rule_id' => 'required_if:action,apply|nullable|exists:advance_rebate_rules,id',
            'academic_year' => 'required|string',
            'amount' => 'required_if:action,apply,adjust|nullable|numeric|min:0.01',
            'remarks' => 'nullable|string|max:500',
        ]);

        if ($validated['action'] === 'apply') {
            $rule = AdvanceRebateRule::findOrFail($validated['advance_rebate_rule_id']);
            $feeTypeIds = AdvanceRebateService::resolveApplicableFeeTypeIds($rule);

            $snapshot = StudentAdvanceRebate::create([
                'student_id' => $student->id,
                'advance_rebate_rule_id' => $rule->id,
                'academic_year' => $validated['academic_year'],
                'fee_type_id' => $feeTypeIds[0] ?? null,
                'rebate_amount' => $validated['amount'],
                'status' => 'applied',
                'applied_at' => now(),
                'approved_by' => Auth::id(),
                'remarks' => $validated['remarks'] ?? 'Manual admin override',
            ]);

            LedgerService::postCredit(
                $student->id,
                now()->format('Y-m-d'),
                "Advance Rebate Applied (Manual Override, Rule: {$rule->name})",
                'advance_rebate',
                $snapshot->id,
                (float) $validated['amount']
            );

            return redirect()->back()->with('success', 'Advance rebate manually applied.');
        }

        if ($validated['action'] === 'cancel') {
            $snapshot = StudentAdvanceRebate::where('student_id', $student->id)
                ->where('academic_year', $validated['academic_year'])
                ->where('status', 'applied')
                ->firstOrFail();

            LedgerService::postDebit(
                $student->id,
                now()->format('Y-m-d'),
                "Advance Rebate Cancelled (Manual Override)",
                'advance_rebate',
                $snapshot->id,
                (float) $snapshot->rebate_amount
            );

            $snapshot->update([
                'status' => 'clawed_back',
                'clawback_amount' => $snapshot->rebate_amount,
                'clawback_shortfall_amount' => 0,
                'clawed_back_at' => now(),
                'approved_by' => Auth::id(),
                'remarks' => $validated['remarks'] ?? 'Manually cancelled by admin',
            ]);

            return redirect()->back()->with('success', 'Advance rebate cancelled.');
        }

        // action === 'adjust'
        $snapshot = StudentAdvanceRebate::where('student_id', $student->id)
            ->where('academic_year', $validated['academic_year'])
            ->where('status', 'applied')
            ->firstOrFail();

        $oldAmount = (float) $snapshot->rebate_amount;
        $newAmount = (float) $validated['amount'];
        $delta = round($newAmount - $oldAmount, 2);

        if ($delta > 0) {
            LedgerService::postCredit($student->id, now()->format('Y-m-d'), 'Advance Rebate Adjusted Upward (Manual Override)', 'advance_rebate', $snapshot->id, $delta);
        } elseif ($delta < 0) {
            LedgerService::postDebit($student->id, now()->format('Y-m-d'), 'Advance Rebate Adjusted Downward (Manual Override)', 'advance_rebate', $snapshot->id, abs($delta));
        }

        $snapshot->update([
            'rebate_amount' => $newAmount,
            'approved_by' => Auth::id(),
            'remarks' => $validated['remarks'] ?? $snapshot->remarks,
        ]);

        return redirect()->back()->with('success', 'Advance rebate adjusted.');
    }

    private function validateRule(Request $request): array
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:percent,fixed',
            'value' => 'required|numeric|min:0',
            'applicable_fee_types_raw' => 'nullable|string',
            'cutoff_month_day' => 'required|regex:/^\d{2}-\d{2}$/',
            'min_coverage' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $names = array_values(array_filter(array_map('trim', explode(',', $validated['applicable_fee_types_raw'] ?? ''))));
        $feeTypeIds = $names ? FeeType::whereIn('name', $names)->pluck('id')->toArray() : [];

        return [
            'name' => $validated['name'],
            'type' => $validated['type'],
            'value' => $validated['value'],
            'applicable_fee_type_ids' => $feeTypeIds ?: null,
            'cutoff_month_day' => $validated['cutoff_month_day'],
            'min_coverage' => $validated['min_coverage'] ?? 'full_session',
            'is_active' => $request->boolean('is_active'),
        ];
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DiscountRule;
use App\Models\FeeType;
use Illuminate\Http\Request;

/**
 * Rule CRUD for the discount engine (sibling/family_sibling/staff_child/
 * merit/category) -- financial-policy decision, same tier as the
 * advance-rebate rules. Gated by permission rather than a hardcoded role:
 * only admin holds view-discount-rules/manage-discount-rules by default
 * (identical behavior to the old role:admin-only check), but the admin can
 * now delegate this duty to another role via Manage Permissions if they
 * choose to. Distinct from DiscountApprovalController, which is a
 * separate, ad-hoc manual-discount-request workflow against
 * discount_approvals.
 */
class DiscountRuleController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:view-discount-rules')->only(['index']);
        $this->middleware('permission:manage-discount-rules')->only(['create', 'store', 'edit', 'update', 'destroy']);
    }

    public function index()
    {
        $rules = DiscountRule::orderBy('priority', 'desc')->get();

        return view('admin.discount-rules.index', compact('rules'));
    }

    public function create()
    {
        $feeTypes = FeeType::active()->get();
        $classes = \App\Models\SchoolClass::active()->orderByOrder()->get();

        return view('admin.discount-rules.create', compact('feeTypes', 'classes'));
    }

    public function store(Request $request)
    {
        $validated = $this->validateRule($request);

        DiscountRule::create($validated);

        return redirect()->route('admin.discount-rules.index')->with('success', 'Discount rule created.');
    }

    public function edit(DiscountRule $discountRule)
    {
        $feeTypes = FeeType::active()->get();
        $classes = \App\Models\SchoolClass::active()->orderByOrder()->get();

        return view('admin.discount-rules.edit', ['rule' => $discountRule, 'feeTypes' => $feeTypes, 'classes' => $classes]);
    }

    public function update(Request $request, DiscountRule $discountRule)
    {
        $validated = $this->validateRule($request);

        $discountRule->update($validated);

        return redirect()->route('admin.discount-rules.index')->with('success', 'Discount rule updated.');
    }

    public function destroy(DiscountRule $discountRule)
    {
        $discountRule->delete();

        return redirect()->route('admin.discount-rules.index')->with('success', 'Discount rule deleted.');
    }

    private function validateRule(Request $request): array
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:sibling,family_sibling,staff_child,merit,category,rte_quota,early_payment,gender_based,loyalty,referral,special_needs,attendance_based',
            'priority' => 'nullable|integer',
            'is_active' => 'boolean',
            'applicable_fee_types_raw' => 'nullable|string',
            'applicable_classes_raw' => 'nullable|string',
            'rank_by' => 'nullable|in:age,class',
            'youngest_child_only' => 'boolean',
            'threshold_score' => 'nullable|numeric|min:0|max:100',
            'mappings_raw' => 'nullable|string',
            'cutoff_date' => 'nullable|date',
            'gender' => 'nullable|in:male,female',
            'tiers_raw' => 'nullable|string',
            // discount_mode governs how percentage/flat_amount/rates_raw/
            // mappings_raw values below are interpreted (a % or a rupee
            // amount) -- validated as a plain number either way since the
            // 0-100 percentage cap doesn't apply once discount_mode is
            // flat_amount. Defaults to 'percentage' (matching the column's
            // own DB default) so any existing caller that predates this
            // field and never sends it keeps working unchanged.
            'discount_mode' => 'nullable|in:percentage,flat_amount',
            'percentage' => 'nullable|numeric|min:0',
            'flat_amount' => 'nullable|numeric|min:0',
            'rates_raw' => 'nullable|string',
            'valid_from' => 'nullable|date',
            'valid_until' => 'nullable|date|after_or_equal:valid_from',
            'max_cap_amount' => 'nullable|numeric|min:0',
        ]);

        $discountMode = $validated['discount_mode'] ?? 'percentage';

        if ($discountMode === 'percentage' && isset($validated['percentage']) && $validated['percentage'] > 100) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'percentage' => 'Percentage cannot exceed 100.',
            ]);
        }

        $applicableFeeTypes = $this->parseCsv($validated['applicable_fee_types_raw'] ?? '');
        $rates = array_map('floatval', $this->parseCsv($validated['rates_raw'] ?? ''));
        $mappings = [];
        foreach ($this->parseCsv($validated['mappings_raw'] ?? '') as $pair) {
            if (str_contains($pair, ':')) {
                [$key, $value] = explode(':', $pair, 2);
                $mappings[trim($key)] = (float) trim($value);
            }
        }

        // tiers_raw is "95:50,90:30,85:15" -- threshold:value for merit and
        // attendance_based, years:value for loyalty, or (early_payment)
        // "2026-03-31:10,2026-04-30:5" -- cutoff_date:value, kept as a date
        // string rather than cast to float. Same raw format, different key
        // name/type in the resulting array depending on which type it's
        // attached to.
        $tierKey = match ($validated['type']) {
            'loyalty' => 'years',
            'early_payment' => 'cutoff_date',
            default => 'threshold',
        };
        $tiers = [];
        foreach ($this->parseCsv($validated['tiers_raw'] ?? '') as $pair) {
            if (str_contains($pair, ':')) {
                [$bound, $value] = explode(':', $pair, 2);
                $bound = trim($bound);
                $tiers[] = [$tierKey => $tierKey === 'cutoff_date' ? $bound : (float) $bound, 'value' => (float) trim($value)];
            }
        }

        $applicableClasses = $this->parseCsv($validated['applicable_classes_raw'] ?? '');

        $config = array_filter([
            'applicable_fee_types' => $applicableFeeTypes ?: null,
            'applicable_classes' => $applicableClasses ?: null,
            'rank_by' => $validated['rank_by'] ?? null,
            'youngest_child_only' => $request->boolean('youngest_child_only'),
            'percentage' => $validated['percentage'] ?? null,
            'rates' => $rates ?: null,
            'threshold_score' => $validated['threshold_score'] ?? null,
            'mappings' => $mappings ?: null,
            'cutoff_date' => $validated['cutoff_date'] ?? null,
            'gender' => $validated['gender'] ?? null,
            'tiers' => $tiers ?: null,
        ], fn ($v) => $v !== null && $v !== []);

        return [
            'name' => $validated['name'],
            'type' => $validated['type'],
            'priority' => $validated['priority'] ?? 0,
            'is_active' => $request->boolean('is_active'),
            'config' => $config,
            'discount_mode' => $discountMode,
            'flat_amount' => $validated['flat_amount'] ?? null,
            'valid_from' => $validated['valid_from'] ?? null,
            'valid_until' => $validated['valid_until'] ?? null,
            'max_cap_amount' => $validated['max_cap_amount'] ?? null,
        ];
    }

    private function parseCsv(string $raw): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $raw)), fn ($v) => $v !== ''));
    }
}

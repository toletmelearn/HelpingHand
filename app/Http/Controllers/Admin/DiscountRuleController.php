<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DiscountRule;
use App\Models\FeeType;
use Illuminate\Http\Request;

/**
 * Rule CRUD for the discount engine (sibling/family_sibling/staff_child/
 * merit/category) -- role:admin exclusive since rule creation is a
 * financial-policy decision, same tier as the advance-rebate rules.
 * Distinct from DiscountApprovalController, which is a separate, ad-hoc
 * manual-discount-request workflow against discount_approvals.
 */
class DiscountRuleController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'role:admin']);
    }

    public function index()
    {
        $rules = DiscountRule::orderBy('priority', 'desc')->get();

        return view('admin.discount-rules.index', compact('rules'));
    }

    public function create()
    {
        $feeTypes = FeeType::active()->get();

        return view('admin.discount-rules.create', compact('feeTypes'));
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

        return view('admin.discount-rules.edit', ['rule' => $discountRule, 'feeTypes' => $feeTypes]);
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
            'type' => 'required|in:sibling,family_sibling,staff_child,merit,category',
            'priority' => 'nullable|integer',
            'is_active' => 'boolean',
            'applicable_fee_types_raw' => 'nullable|string',
            'rank_by' => 'nullable|in:age,class',
            'youngest_child_only' => 'boolean',
            'percentage' => 'nullable|numeric|min:0|max:100',
            'rates_raw' => 'nullable|string',
            'threshold_score' => 'nullable|numeric|min:0|max:100',
            'mappings_raw' => 'nullable|string',
        ]);

        $applicableFeeTypes = $this->parseCsv($validated['applicable_fee_types_raw'] ?? '');
        $rates = array_map('floatval', $this->parseCsv($validated['rates_raw'] ?? ''));
        $mappings = [];
        foreach ($this->parseCsv($validated['mappings_raw'] ?? '') as $pair) {
            if (str_contains($pair, ':')) {
                [$key, $value] = explode(':', $pair, 2);
                $mappings[trim($key)] = (float) trim($value);
            }
        }

        $config = array_filter([
            'applicable_fee_types' => $applicableFeeTypes ?: null,
            'rank_by' => $validated['rank_by'] ?? null,
            'youngest_child_only' => $request->boolean('youngest_child_only'),
            'percentage' => $validated['percentage'] ?? null,
            'rates' => $rates ?: null,
            'threshold_score' => $validated['threshold_score'] ?? null,
            'mappings' => $mappings ?: null,
        ], fn ($v) => $v !== null && $v !== []);

        return [
            'name' => $validated['name'],
            'type' => $validated['type'],
            'priority' => $validated['priority'] ?? 0,
            'is_active' => $request->boolean('is_active'),
            'config' => $config,
        ];
    }

    private function parseCsv(string $raw): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $raw)), fn ($v) => $v !== ''));
    }
}

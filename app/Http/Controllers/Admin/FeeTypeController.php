<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FeeType;
use App\Models\LateFeeRule;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Fee-head master CRUD. FeeStructureController::feeTypeMaster() already
 * covers editing *defaults* (frequency/charge months/late fee rule) on
 * existing types -- this controller covers identity: creating a new head,
 * renaming/describing one, and deactivating/reactivating/deleting it.
 */
class FeeTypeController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'role:accountant']);
    }

    public function index()
    {
        $feeTypes = FeeType::orderBy('name')->get();

        return view('admin.fee-types.index', compact('feeTypes'));
    }

    public function create()
    {
        $lateFeeRules = LateFeeRule::all();

        return view('admin.fee-types.create', compact('lateFeeRules'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:fee_types,name',
            'description' => 'nullable|string|max:500',
            'category' => 'nullable|string|max:50',
            'is_optional' => 'boolean',
            'default_frequency' => 'nullable|string',
            'default_late_fee_rule_id' => 'nullable|exists:late_fee_rules,id',
        ]);

        $validated['is_optional'] = $request->boolean('is_optional');
        $validated['status'] = 'active';
        $validated['category'] = $validated['category'] ?? 'recurring';

        FeeType::create($validated);

        return redirect()->route('admin.fee-types.index')->with('success', 'Fee head created successfully.');
    }

    public function edit(FeeType $feeType)
    {
        $lateFeeRules = LateFeeRule::all();

        return view('admin.fee-types.edit', compact('feeType', 'lateFeeRules'));
    }

    public function update(Request $request, FeeType $feeType)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('fee_types', 'name')->ignore($feeType->id)],
            'description' => 'nullable|string|max:500',
            'category' => 'nullable|string|max:50',
            'is_optional' => 'boolean',
            'default_frequency' => 'nullable|string',
            'default_late_fee_rule_id' => 'nullable|exists:late_fee_rules,id',
        ]);

        $validated['is_optional'] = $request->boolean('is_optional');

        $feeType->update($validated);

        return redirect()->route('admin.fee-types.index')->with('success', 'Fee head updated successfully.');
    }

    public function destroy(FeeType $feeType)
    {
        $feeType->delete();

        return redirect()->route('admin.fee-types.index')->with('success', 'Fee head deleted.');
    }

    public function activate(FeeType $feeType)
    {
        $feeType->update(['status' => 'active']);

        return redirect()->back()->with('success', 'Fee head activated.');
    }

    public function deactivate(FeeType $feeType)
    {
        $feeType->update(['status' => 'inactive']);

        return redirect()->back()->with('success', 'Fee head deactivated.');
    }
}

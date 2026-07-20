@if ($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

@php
    $config = $rule?->config ?? [];
@endphp

<div class="mb-3">
    <label class="form-label">Name</label>
    <input type="text" name="name" class="form-control" value="{{ old('name', $rule?->name ?? '') }}" required>
</div>

<div class="mb-3">
    <label class="form-label">Type</label>
    <select name="type" id="ruleType" class="form-select" required>
        @foreach([
            'sibling' => 'Sibling (legacy, father_name+mobile match)',
            'family_sibling' => 'Family Sibling (families table)',
            'staff_child' => 'Staff Child',
            'merit' => 'Merit / Scholarship',
            'category' => 'Category (SC/ST/OBC, Defence, Single Parent, Alumni, or any custom category)',
            'rte_quota' => 'RTE Quota (Right to Education)',
            'early_payment' => 'Early / Advance Payment',
            'gender_based' => 'Gender-Based (e.g. Girl Child)',
            'loyalty' => 'Loyalty / Long-Tenure (returning students)',
            'referral' => 'Referral (admitted via an existing family)',
            'special_needs' => 'Special Needs / Differently-Abled',
            'attendance_based' => 'Good Attendance',
        ] as $value => $label)
            <option value="{{ $value }}" {{ old('type', $rule?->type ?? '') === $value ? 'selected' : '' }}>{{ $label }}</option>
        @endforeach
    </select>
    <small class="text-muted">
        Category rules aren't limited to SC/ST/OBC -- map any value your school uses (e.g. "Defence":25, "Single Parent":15, "Alumni":10) in the Category Mappings field below.
    </small>
</div>

<div class="mb-3">
    <label class="form-label">Priority</label>
    <input type="number" name="priority" class="form-control" value="{{ old('priority', $rule?->priority ?? 0) }}">
</div>

<div class="mb-3">
    <label class="form-label">Applicable Fee Types (comma-separated names, blank = Tuition)</label>
    <input type="text" name="applicable_fee_types_raw" id="applicableFeeTypesRaw" class="form-control"
           value="{{ old('applicable_fee_types_raw', implode(',', $config['applicable_fee_types'] ?? [])) }}"
           placeholder="e.g. Tuition,Development Fund">
    <small class="text-muted">Leave blank to default to Tuition only. For RTE Quota, list every fee head except Security Deposit.</small>
</div>

<div class="mb-3">
    <label class="form-label">Applicable Classes (comma-separated names, blank = all classes)</label>
    <input type="text" name="applicable_classes_raw" class="form-control"
           value="{{ old('applicable_classes_raw', implode(',', $config['applicable_classes'] ?? [])) }}"
           placeholder="e.g. Class 11,Class 12">
    <small class="text-muted">
        Restricts this rule to specific classes.
        @if(isset($classes) && $classes->count())
            Available: {{ $classes->pluck('name')->implode(', ') }}
        @endif
    </small>
</div>

<div class="border rounded p-3 mb-3 bg-light">
    <h6 class="text-primary">Discount Amount</h6>
    <div class="mb-3">
        <label class="form-label">Mode</label>
        <select name="discount_mode" id="discountMode" class="form-select">
            <option value="percentage" {{ old('discount_mode', $rule?->discount_mode ?? 'percentage') === 'percentage' ? 'selected' : '' }}>Percentage of fee</option>
            <option value="flat_amount" {{ old('discount_mode', $rule?->discount_mode ?? '') === 'flat_amount' ? 'selected' : '' }}>Flat rupee amount</option>
        </select>
        <small class="text-muted">Applies to every field below -- Rates and Category Mappings hold either percentages or rupee amounts depending on this.</small>
    </div>
</div>

<div id="familySiblingFields" class="border rounded p-3 mb-3">
    <h6 class="text-primary">Family Sibling Settings</h6>
    <div class="mb-3">
        <label class="form-label">Rank By</label>
        <select name="rank_by" class="form-select">
            <option value="age" {{ ($config['rank_by'] ?? 'age') === 'age' ? 'selected' : '' }}>Age (oldest = 1st child)</option>
            <option value="class" {{ ($config['rank_by'] ?? '') === 'class' ? 'selected' : '' }}>Class (senior-most = 1st child)</option>
        </select>
    </div>
    <div class="form-check mb-3">
        <input type="hidden" name="youngest_child_only" value="0">
        <input type="checkbox" name="youngest_child_only" value="1" class="form-check-input" id="youngestChildOnly"
               {{ old('youngest_child_only', $config['youngest_child_only'] ?? false) ? 'checked' : '' }}>
        <label class="form-check-label" for="youngestChildOnly">Youngest-child rule (only the youngest sibling gets the discount)</label>
    </div>
    <div class="mb-3" id="ratesField">
        <label class="form-label" id="ratesLabel">Per-Rank Rates (comma-separated: 1st,2nd,3rd...)</label>
        <input type="text" name="rates_raw" class="form-control" value="{{ old('rates_raw', implode(',', $config['rates'] ?? [0, 25, 50])) }}">
    </div>
</div>

<div class="mb-3" id="flatPercentageField">
    <label class="form-label" id="flatPercentageLabel">Percentage (%)</label>
    <input type="number" name="percentage" id="percentageInput" class="form-control" step="0.01" min="0" max="100" value="{{ old('percentage', $config['percentage'] ?? '') }}">
</div>

<div class="mb-3" id="flatAmountField">
    <label class="form-label">Flat Amount (Rs.)</label>
    <input type="number" name="flat_amount" id="flatAmountInput" class="form-control" step="0.01" min="0" value="{{ old('flat_amount', $rule?->flat_amount ?? '') }}">
</div>

<div class="mb-3" id="thresholdField">
    <label class="form-label" id="thresholdLabel">Merit Threshold Score (%)</label>
    <input type="number" name="threshold_score" class="form-control" step="0.01" min="0" max="100" value="{{ old('threshold_score', $config['threshold_score'] ?? 85) }}">
</div>

<div class="mb-3" id="mappingsField">
    <label class="form-label">Category Mappings (e.g. SC:50,ST:50,OBC:25,Defence:25,Single Parent:15,Alumni:10)</label>
    <input type="text" name="mappings_raw" class="form-control" value="{{ old('mappings_raw', collect($config['mappings'] ?? [])->map(fn($v, $k) => "$k:$v")->implode(',')) }}">
    <small class="text-muted">Match against the "Category" field on a student's profile (any free-text value your school uses).</small>
</div>

<div class="mb-3" id="cutoffDateField">
    <label class="form-label">Pay By (cutoff date)</label>
    <input type="date" name="cutoff_date" class="form-control" value="{{ old('cutoff_date', $config['cutoff_date'] ?? '') }}">
    <small class="text-muted">Collections made on or before this date qualify for the discount.</small>
</div>

<div class="mb-3" id="genderField">
    <label class="form-label">Applies To</label>
    <select name="gender" class="form-select">
        <option value="female" {{ ($config['gender'] ?? 'female') === 'female' ? 'selected' : '' }}>Female (Girl Child)</option>
        <option value="male" {{ ($config['gender'] ?? '') === 'male' ? 'selected' : '' }}>Male</option>
    </select>
</div>

<div class="mb-3" id="tiersField">
    <label class="form-label" id="tiersLabel">Tiers (threshold:value, comma-separated -- e.g. 95:50,90:30,85:15)</label>
    <input type="text" name="tiers_raw" class="form-control" value="{{ old('tiers_raw', collect($config['tiers'] ?? [])->map(fn($t) => ($t['threshold'] ?? $t['years'] ?? $t['cutoff_date'] ?? 0) . ':' . $t['value'])->implode(',')) }}">
    <small class="text-muted" id="tiersHelp">Highest qualifying tier wins. Leave blank to use the single field above instead.</small>
</div>

<div class="mb-3" id="referralNote">
    <div class="alert alert-info mb-0">
        Eligibility is based on the "Referred By" admission number recorded on a student's profile at admission time -- any student with that field filled in qualifies. Set the discount amount above.
    </div>
</div>

<div class="border rounded p-3 mb-3">
    <h6 class="text-primary">Validity &amp; Cap <small class="text-muted fw-normal">(optional, applies to every type)</small></h6>
    <div class="row">
        <div class="col-md-4 mb-3">
            <label class="form-label">Valid From</label>
            <input type="date" name="valid_from" class="form-control" value="{{ old('valid_from', optional($rule?->valid_from)->format('Y-m-d')) }}">
        </div>
        <div class="col-md-4 mb-3">
            <label class="form-label">Valid Until</label>
            <input type="date" name="valid_until" class="form-control" value="{{ old('valid_until', optional($rule?->valid_until)->format('Y-m-d')) }}">
            <small class="text-muted">Leave both blank for a rule with no expiry.</small>
        </div>
        <div class="col-md-4 mb-3">
            <label class="form-label">Max Cap (Rs.)</label>
            <input type="number" name="max_cap_amount" class="form-control" step="0.01" min="0" value="{{ old('max_cap_amount', $rule?->max_cap_amount ?? '') }}">
            <small class="text-muted">Absolute ceiling on one application of this rule, even in percentage mode.</small>
        </div>
    </div>
</div>

<div class="form-check mb-3">
    <input type="hidden" name="is_active" value="0">
    <input type="checkbox" name="is_active" value="1" class="form-check-input" id="isActive"
           {{ old('is_active', $rule?->is_active ?? true) ? 'checked' : '' }}>
    <label class="form-check-label" for="isActive">Active</label>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const typeSelect = document.getElementById('ruleType');
    const modeSelect = document.getElementById('discountMode');

    function toggleFields() {
        const type = typeSelect.value;
        const mode = modeSelect.value;
        const rankedTypes = ['sibling', 'family_sibling'];
        const simpleValueTypes = ['staff_child', 'merit', 'rte_quota', 'early_payment', 'gender_based', 'loyalty', 'referral', 'special_needs', 'attendance_based'];
        const tieredTypes = ['merit', 'loyalty', 'early_payment', 'attendance_based'];
        const thresholdTypes = ['merit', 'attendance_based'];

        document.getElementById('familySiblingFields').style.display = type === 'family_sibling' ? '' : 'none';
        document.getElementById('thresholdField').style.display = thresholdTypes.includes(type) ? '' : 'none';
        document.getElementById('mappingsField').style.display = type === 'category' ? '' : 'none';
        document.getElementById('cutoffDateField').style.display = type === 'early_payment' ? '' : 'none';
        document.getElementById('genderField').style.display = type === 'gender_based' ? '' : 'none';
        document.getElementById('tiersField').style.display = tieredTypes.includes(type) ? '' : 'none';
        document.getElementById('referralNote').style.display = type === 'referral' ? '' : 'none';

        const thresholdLabel = document.getElementById('thresholdLabel');
        if (thresholdLabel) {
            thresholdLabel.textContent = type === 'attendance_based' ? 'Attendance Threshold (%)' : 'Merit Threshold Score (%)';
        }

        const tiersLabel = document.getElementById('tiersLabel');
        const tiersHelp = document.getElementById('tiersHelp');
        if (tiersLabel && type === 'loyalty') {
            tiersLabel.textContent = 'Tiers (years:value, comma-separated -- e.g. 5:15,3:10,1:5)';
            tiersHelp.textContent = 'Years enrolled at or above each tier qualifies for that value. Highest qualifying tier wins.';
        } else if (tiersLabel && type === 'early_payment') {
            tiersLabel.textContent = 'Tiers (cutoff_date:value, comma-separated -- e.g. 2026-03-31:10,2026-04-30:5)';
            tiersHelp.textContent = 'Pay by that date to qualify for that value. Leave blank to use the single Pay By field above instead.';
        } else if (tiersLabel) {
            tiersLabel.textContent = 'Tiers (threshold:value, comma-separated -- e.g. 95:50,90:30,85:15)';
            tiersHelp.textContent = 'Highest qualifying tier wins. Leave blank to use the single Percentage/Flat Amount field above instead (with the Threshold field).';
        }

        // family_sibling with the youngest-child rule uses the simple
        // percentage/flat field instead of the per-rank rates field.
        const youngestOnly = document.getElementById('youngestChildOnly').checked;
        const usesSimpleValue = simpleValueTypes.includes(type) || (type === 'family_sibling' && youngestOnly);
        const usesRates = rankedTypes.includes(type) && !(type === 'family_sibling' && youngestOnly);

        document.getElementById('ratesField').style.display = usesRates ? '' : 'none';
        document.getElementById('flatPercentageField').style.display = (usesSimpleValue && mode === 'percentage') ? '' : 'none';
        document.getElementById('flatAmountField').style.display = (usesSimpleValue && mode === 'flat_amount') ? '' : 'none';

        const ratesLabel = document.getElementById('ratesLabel');
        if (ratesLabel) {
            ratesLabel.textContent = mode === 'flat_amount'
                ? 'Per-Rank Rates (Rs., comma-separated: 1st,2nd,3rd...)'
                : 'Per-Rank Rates (%, comma-separated: 1st,2nd,3rd...)';
        }
    }

    typeSelect.addEventListener('change', toggleFields);
    modeSelect.addEventListener('change', toggleFields);
    document.getElementById('youngestChildOnly').addEventListener('change', toggleFields);
    toggleFields();
});
</script>

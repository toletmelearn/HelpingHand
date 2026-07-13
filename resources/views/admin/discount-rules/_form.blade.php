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
        @foreach(['sibling' => 'Sibling (legacy, father_name+mobile match)', 'family_sibling' => 'Family Sibling (families table)', 'staff_child' => 'Staff Child', 'merit' => 'Merit', 'category' => 'Category'] as $value => $label)
            <option value="{{ $value }}" {{ old('type', $rule?->type ?? '') === $value ? 'selected' : '' }}>{{ $label }}</option>
        @endforeach
    </select>
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
    <small class="text-muted">Leave blank to default to Tuition only.</small>
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
        <label class="form-label">Per-Rank Rates (%, comma-separated: 1st,2nd,3rd...)</label>
        <input type="text" name="rates_raw" class="form-control" value="{{ old('rates_raw', implode(',', $config['rates'] ?? [0, 25, 50])) }}">
    </div>
</div>

<div class="mb-3" id="flatPercentageField">
    <label class="form-label">Percentage (%) -- staff_child / merit / youngest-child rule</label>
    <input type="number" name="percentage" class="form-control" step="0.01" min="0" max="100" value="{{ old('percentage', $config['percentage'] ?? '') }}">
</div>

<div class="mb-3" id="thresholdField">
    <label class="form-label">Merit Threshold Score (%)</label>
    <input type="number" name="threshold_score" class="form-control" step="0.01" min="0" max="100" value="{{ old('threshold_score', $config['threshold_score'] ?? 85) }}">
</div>

<div class="mb-3" id="mappingsField">
    <label class="form-label">Category Mappings (e.g. SC:50,ST:50,OBC:25)</label>
    <input type="text" name="mappings_raw" class="form-control" value="{{ old('mappings_raw', collect($config['mappings'] ?? [])->map(fn($v, $k) => "$k:$v")->implode(',')) }}">
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
    function toggleFields() {
        const type = typeSelect.value;
        document.getElementById('familySiblingFields').style.display = type === 'family_sibling' ? '' : 'none';
        document.getElementById('thresholdField').style.display = type === 'merit' ? '' : 'none';
        document.getElementById('mappingsField').style.display = type === 'category' ? '' : 'none';
        document.getElementById('flatPercentageField').style.display = ['staff_child', 'merit'].includes(type) || type === 'family_sibling' ? '' : 'none';
    }
    typeSelect.addEventListener('change', toggleFields);
    toggleFields();
});
</script>

@if ($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="mb-3">
    <label class="form-label">Name</label>
    <input type="text" name="name" class="form-control" value="{{ old('name', $feeType->name ?? '') }}" required>
</div>

<div class="mb-3">
    <label class="form-label">Description</label>
    <textarea name="description" class="form-control" rows="2">{{ old('description', $feeType->description ?? '') }}</textarea>
</div>

<div class="mb-3">
    <label class="form-label">Category</label>
    <input type="text" name="category" class="form-control" value="{{ old('category', $feeType->category ?? 'recurring') }}">
    <small class="text-muted">Free-text label (e.g. "recurring", "one-time", "deposit"). "deposit" has special handling -- it automatically creates a Security Deposit record when collected.</small>
</div>

<div class="mb-3">
    <label class="form-label">Default Frequency</label>
    <select name="default_frequency" class="form-select">
        <option value="">— Not set —</option>
        @foreach(['monthly' => 'Monthly', 'quarterly' => 'Quarterly', 'yearly' => 'Yearly / Annual', 'session_wise_admission' => 'Session Admission', 'exam_wise' => 'Exam Wise', 'custom' => 'Custom Months'] as $value => $label)
            <option value="{{ $value }}" {{ old('default_frequency', $feeType->default_frequency ?? '') === $value ? 'selected' : '' }}>{{ $label }}</option>
        @endforeach
    </select>
</div>

<div class="mb-3">
    <label class="form-label">Default Late Fee Rule</label>
    <select name="default_late_fee_rule_id" class="form-select">
        <option value="">No Late Fee</option>
        @foreach($lateFeeRules as $rule)
            <option value="{{ $rule->id }}" {{ old('default_late_fee_rule_id', $feeType->default_late_fee_rule_id ?? '') == $rule->id ? 'selected' : '' }}>
                {{ $rule->name }}
            </option>
        @endforeach
    </select>
</div>

<div class="form-check">
    <input type="hidden" name="is_optional" value="0">
    <input type="checkbox" name="is_optional" value="1" class="form-check-input" id="is_optional"
           {{ old('is_optional', $feeType->is_optional ?? false) ? 'checked' : '' }}>
    <label class="form-check-label" for="is_optional">Optional (not mandatory for every student)</label>
</div>

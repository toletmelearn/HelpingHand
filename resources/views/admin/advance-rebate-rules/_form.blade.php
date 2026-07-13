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
    <input type="text" name="name" class="form-control" value="{{ old('name', $rule?->name ?? '') }}" required>
</div>

<div class="mb-3">
    <label class="form-label">Type</label>
    <select name="type" class="form-select" required>
        <option value="percent" {{ old('type', $rule?->type ?? '') === 'percent' ? 'selected' : '' }}>Percent</option>
        <option value="fixed" {{ old('type', $rule?->type ?? '') === 'fixed' ? 'selected' : '' }}>Fixed Amount</option>
    </select>
</div>

<div class="mb-3">
    <label class="form-label">Value (% or ₹)</label>
    <input type="number" name="value" class="form-control" step="0.01" min="0" value="{{ old('value', $rule?->value ?? '') }}" required>
</div>

<div class="mb-3">
    <label class="form-label">Applicable Fee Heads (comma-separated, blank = Tuition only)</label>
    <input type="text" name="applicable_fee_types_raw" class="form-control"
           value="{{ old('applicable_fee_types_raw', collect($rule?->applicable_fee_type_ids ?? [])->map(fn($id) => \App\Models\FeeType::find($id)?->name)->filter()->implode(',')) }}"
           placeholder="e.g. Tuition">
</div>

<div class="mb-3">
    <label class="form-label">Cutoff Date (MM-DD, recurring every session)</label>
    <input type="text" name="cutoff_month_day" class="form-control" pattern="\d{2}-\d{2}" placeholder="04-15"
           value="{{ old('cutoff_month_day', $rule?->cutoff_month_day ?? '') }}" required>
    <small class="text-muted">e.g. 04-15 means April 15th of the session's start year (or the following calendar year for Jan-Mar cutoffs).</small>
</div>

<div class="mb-3">
    <label class="form-label">Minimum Coverage</label>
    <select name="min_coverage" class="form-select">
        <option value="full_session" {{ old('min_coverage', $rule?->min_coverage ?? 'full_session') === 'full_session' ? 'selected' : '' }}>Full Session (all applicable dues fully paid)</option>
    </select>
</div>

<div class="form-check mb-3">
    <input type="hidden" name="is_active" value="0">
    <input type="checkbox" name="is_active" value="1" class="form-check-input" id="isActive"
           {{ old('is_active', $rule?->is_active ?? true) ? 'checked' : '' }}>
    <label class="form-check-label" for="isActive">Active</label>
</div>

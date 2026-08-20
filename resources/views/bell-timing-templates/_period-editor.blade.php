{{--
    Shared period-row editor for Create/Edit Template. Deliberately a
    self-contained copy of the canonical-hidden-input + 24h/12h paired
    widget pattern from resources/views/bell-timing/bulk-create.blade.php
    (the protected, already-deployed baseline) rather than an extraction
    from it -- this new feature must not risk that file. Behavior is
    identical: a hidden HH:mm canonical input is the only thing submitted;
    the visible 24h/12h widgets are just alternate ways to read/write it.

    Expects (all optional): $slots -- array of ['period_name','start_time'
    (HH:mm),'end_time','is_break','order_index','custom_label','color_code']
    to pre-fill, e.g. from old('slots') on a validation failure or from an
    existing template being edited.
--}}
@php
    $slots = $slots ?? [];
    if (empty($slots)) {
        $slots = [['order_index' => 0]];
    }
    $splitTime = function (?string $hhmm) {
        if (!$hhmm || !preg_match('/^(\d{1,2}):(\d{2})/', $hhmm, $m)) {
            return ['h24' => '', 'm' => '', 'h12' => '', 'ampm' => 'AM'];
        }
        $h = (int) $m[1];
        $min = (int) $m[2];
        $h12 = ($h % 12) ?: 12;
        $ampm = $h < 12 ? 'AM' : 'PM';
        return ['h24' => $h, 'm' => $min, 'h12' => $h12, 'ampm' => $ampm];
    };
@endphp
<style>
    .period-row { border: 1px solid #dee2e6; border-radius: .375rem; padding: 1rem; margin-bottom: 1rem; background-color: #f8f9fa; }
    body:not([data-time-format="12"]) .time-input-12h { display: none !important; }
    body[data-time-format="12"] .time-input-24h { display: none !important; }
    .time-h, .time-m { width: 4.2em; text-align: center; }
    .time-ampm { width: 5.5em; }
    .time-h.is-invalid, .time-m.is-invalid, .time-ampm.is-invalid { background-image: none !important; padding-right: .5rem !important; }
</style>

<div class="card mb-4">
    <div class="card-body py-2 d-flex align-items-center gap-3 flex-wrap">
        <strong><i class="bi bi-clock"></i> Time Format</strong>
        <div class="btn-group" role="group">
            <input type="radio" class="btn-check" name="time_format_ui" id="fmt24" autocomplete="off" value="24">
            <label class="btn btn-outline-primary btn-sm" for="fmt24">24 Hour</label>
            <input type="radio" class="btn-check" name="time_format_ui" id="fmt12" autocomplete="off" value="12">
            <label class="btn btn-outline-primary btn-sm" for="fmt12">12 Hour (AM/PM)</label>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-list-ol"></i> Periods</h5>
        <button type="button" class="btn btn-light btn-sm" onclick="addPeriod()"><i class="bi bi-plus-circle"></i> Add Period</button>
    </div>
    <div class="card-body">
        <div id="periodsContainer">
            @foreach($slots as $i => $p)
                @php
                    $idx = $i + 1;
                    $st = $splitTime($p['start_time'] ?? null);
                    $et = $splitTime($p['end_time'] ?? null);
                @endphp
                <div class="period-row" id="period_{{ $idx }}">
                    <div class="row">
                        <div class="col-md-3">
                            <label class="form-label">Period Name *</label>
                            <input type="text" class="form-control @error('slots.'.$i.'.period_name') is-invalid @enderror" name="slots[{{ $i }}][period_name]" value="{{ $p['period_name'] ?? '' }}" placeholder="e.g., Period 1" required>
                        </div>
                        <div class="col-md-3 time-field-wrap">
                            <label class="form-label">Start Time *</label>
                            <input type="hidden" name="slots[{{ $i }}][start_time]" class="time-canonical @error('slots.'.$i.'.start_time') is-invalid @enderror" value="{{ $p['start_time'] ?? '' }}">
                            <div class="time-input-24h d-flex align-items-center gap-1">
                                <input type="number" class="form-control form-control-sm time-h @error('slots.'.$i.'.start_time') is-invalid @enderror" min="0" max="23" placeholder="HH" value="{{ $st['h24'] }}">
                                <span>:</span>
                                <input type="number" class="form-control form-control-sm time-m @error('slots.'.$i.'.start_time') is-invalid @enderror" min="0" max="59" placeholder="MM" value="{{ $st['m'] }}">
                            </div>
                            <div class="time-input-12h d-flex align-items-center gap-1">
                                <input type="number" class="form-control form-control-sm time-h @error('slots.'.$i.'.start_time') is-invalid @enderror" min="1" max="12" placeholder="HH" value="{{ $st['h12'] }}">
                                <span>:</span>
                                <input type="number" class="form-control form-control-sm time-m @error('slots.'.$i.'.start_time') is-invalid @enderror" min="0" max="59" placeholder="MM" value="{{ $st['m'] }}">
                                <select class="form-select form-select-sm time-ampm">
                                    <option value="AM" {{ $st['ampm']=='AM'?'selected':'' }}>AM</option>
                                    <option value="PM" {{ $st['ampm']=='PM'?'selected':'' }}>PM</option>
                                </select>
                            </div>
                            @error('slots.'.$i.'.start_time') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-3 time-field-wrap">
                            <label class="form-label">End Time *</label>
                            <input type="hidden" name="slots[{{ $i }}][end_time]" class="time-canonical @error('slots.'.$i.'.end_time') is-invalid @enderror" value="{{ $p['end_time'] ?? '' }}">
                            <div class="time-input-24h d-flex align-items-center gap-1">
                                <input type="number" class="form-control form-control-sm time-h @error('slots.'.$i.'.end_time') is-invalid @enderror" min="0" max="23" placeholder="HH" value="{{ $et['h24'] }}">
                                <span>:</span>
                                <input type="number" class="form-control form-control-sm time-m @error('slots.'.$i.'.end_time') is-invalid @enderror" min="0" max="59" placeholder="MM" value="{{ $et['m'] }}">
                            </div>
                            <div class="time-input-12h d-flex align-items-center gap-1">
                                <input type="number" class="form-control form-control-sm time-h @error('slots.'.$i.'.end_time') is-invalid @enderror" min="1" max="12" placeholder="HH" value="{{ $et['h12'] }}">
                                <span>:</span>
                                <input type="number" class="form-control form-control-sm time-m @error('slots.'.$i.'.end_time') is-invalid @enderror" min="0" max="59" placeholder="MM" value="{{ $et['m'] }}">
                                <select class="form-select form-select-sm time-ampm">
                                    <option value="AM" {{ $et['ampm']=='AM'?'selected':'' }}>AM</option>
                                    <option value="PM" {{ $et['ampm']=='PM'?'selected':'' }}>PM</option>
                                </select>
                            </div>
                            @error('slots.'.$i.'.end_time') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Order</label>
                            <input type="number" class="form-control" name="slots[{{ $i }}][order_index]" value="{{ $p['order_index'] ?? $i }}" min="0">
                        </div>
                        <div class="col-md-1">
                            <label class="form-label">Type</label>
                            <select class="form-select" name="slots[{{ $i }}][is_break]">
                                <option value="0" {{ (string)($p['is_break'] ?? '0') === '0' ? 'selected' : '' }}>Class</option>
                                <option value="1" {{ (string)($p['is_break'] ?? '0') === '1' ? 'selected' : '' }}>Break</option>
                            </select>
                        </div>
                    </div>
                    <div class="row mt-2">
                        <div class="col-md-5">
                            <label class="form-label">Custom Label</label>
                            <input type="text" class="form-control" name="slots[{{ $i }}][custom_label]" value="{{ $p['custom_label'] ?? '' }}" placeholder="e.g., Math Period">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">Color Code</label>
                            <input type="color" class="form-control form-control-color" name="slots[{{ $i }}][color_code]" value="{{ $p['color_code'] ?? '#007bff' }}">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">&nbsp;</label>
                            <button type="button" class="btn btn-danger btn-sm w-100" onclick="removePeriod({{ $idx }})"><i class="bi bi-trash"></i> Remove</button>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>

<script>
    let periodCounter = {{ count($slots) }};
    const TIME_FORMAT_KEY = 'bellTimingTemplateTimeFormat';
    function pad2(n) { return String(n).padStart(2, '0'); }
    function to12h(hhmm) {
        if (!hhmm || !/^\d{1,2}:\d{2}/.test(hhmm)) return { h: '', m: '', ampm: 'AM' };
        const [H, M] = hhmm.split(':').map(Number);
        return { h: (H % 12) || 12, m: M, ampm: H < 12 ? 'AM' : 'PM' };
    }
    function from12h(h, m, ampm) {
        let H = parseInt(h, 10); const M = parseInt(m, 10);
        if (isNaN(H) || isNaN(M)) return '';
        if (ampm === 'PM' && H !== 12) H += 12;
        if (ampm === 'AM' && H === 12) H = 0;
        return pad2(H) + ':' + pad2(M);
    }
    function from24h(h, m) {
        const H = parseInt(h, 10); const M = parseInt(m, 10);
        if (isNaN(H) || isNaN(M)) return '';
        return pad2(H) + ':' + pad2(M);
    }
    function getTimeFormat() { return localStorage.getItem(TIME_FORMAT_KEY) || '24'; }
    function timeFieldHtml(idx, field, label, value24) {
        value24 = value24 || '';
        const parts = value24.split(':');
        const h24 = parts[0] !== undefined && parts[0] !== '' ? parseInt(parts[0], 10) : '';
        const m = parts[1] !== undefined && parts[1] !== '' ? parseInt(parts[1], 10) : '';
        const t12 = to12h(value24);
        return `
            <div class="time-field-wrap">
                <label class="form-label">${label} *</label>
                <input type="hidden" name="slots[${idx-1}][${field}]" class="time-canonical" value="${value24}">
                <div class="time-input-24h d-flex align-items-center gap-1">
                    <input type="number" class="form-control form-control-sm time-h" min="0" max="23" placeholder="HH" value="${h24===''?'':h24}">
                    <span>:</span>
                    <input type="number" class="form-control form-control-sm time-m" min="0" max="59" placeholder="MM" value="${m===''?'':m}">
                </div>
                <div class="time-input-12h d-flex align-items-center gap-1">
                    <input type="number" class="form-control form-control-sm time-h" min="1" max="12" placeholder="HH" value="${t12.h}">
                    <span>:</span>
                    <input type="number" class="form-control form-control-sm time-m" min="0" max="59" placeholder="MM" value="${t12.m}">
                    <select class="form-select form-select-sm time-ampm">
                        <option value="AM" ${t12.ampm==='AM'?'selected':''}>AM</option>
                        <option value="PM" ${t12.ampm==='PM'?'selected':''}>PM</option>
                    </select>
                </div>
            </div>`;
    }
    function syncToCanonical(canonicalInput) {
        const wrap = canonicalInput.closest('.time-field-wrap'); if (!wrap) return;
        const mode = getTimeFormat();
        let val = '';
        if (mode === '12') {
            const c = wrap.querySelector('.time-input-12h');
            val = from12h(c.querySelector('.time-h').value, c.querySelector('.time-m').value, c.querySelector('.time-ampm').value);
        } else {
            const c = wrap.querySelector('.time-input-24h');
            val = from24h(c.querySelector('.time-h').value, c.querySelector('.time-m').value);
        }
        canonicalInput.value = val;
    }
    function syncFromCanonical(canonicalInput) {
        const wrap = canonicalInput.closest('.time-field-wrap'); if (!wrap) return;
        const value24 = canonicalInput.value;
        const parts = (value24 || '').split(':');
        const h24 = parts[0] !== undefined && parts[0] !== '' ? parseInt(parts[0], 10) : '';
        const m = parts[1] !== undefined && parts[1] !== '' ? parseInt(parts[1], 10) : '';
        const c24 = wrap.querySelector('.time-input-24h');
        c24.querySelector('.time-h').value = (h24===''||isNaN(h24))?'':h24;
        c24.querySelector('.time-m').value = (m===''||isNaN(m))?'':m;
        const t12 = to12h(value24);
        const c12 = wrap.querySelector('.time-input-12h');
        c12.querySelector('.time-h').value = t12.h;
        c12.querySelector('.time-m').value = t12.m;
        c12.querySelector('.time-ampm').value = t12.ampm;
    }
    function applyTimeFormat(mode) {
        document.body.setAttribute('data-time-format', mode);
        document.querySelectorAll('.time-canonical').forEach(syncFromCanonical);
        const radio = document.getElementById(mode === '12' ? 'fmt12' : 'fmt24');
        if (radio) radio.checked = true;
    }
    function setTimeFormat(mode) { localStorage.setItem(TIME_FORMAT_KEY, mode); applyTimeFormat(mode); }

    function addPeriod() {
        periodCounter++;
        const html = `
            <div class="period-row" id="period_${periodCounter}">
                <div class="row">
                    <div class="col-md-3"><label class="form-label">Period Name *</label>
                        <input type="text" class="form-control" name="slots[${periodCounter-1}][period_name]" placeholder="e.g., Period 1" required></div>
                    <div class="col-md-3">${timeFieldHtml(periodCounter, 'start_time', 'Start Time', '')}</div>
                    <div class="col-md-3">${timeFieldHtml(periodCounter, 'end_time', 'End Time', '')}</div>
                    <div class="col-md-2"><label class="form-label">Order</label>
                        <input type="number" class="form-control" name="slots[${periodCounter-1}][order_index]" value="${periodCounter-1}" min="0"></div>
                    <div class="col-md-1"><label class="form-label">Type</label>
                        <select class="form-select" name="slots[${periodCounter-1}][is_break]"><option value="0">Class</option><option value="1">Break</option></select></div>
                </div>
                <div class="row mt-2">
                    <div class="col-md-5"><label class="form-label">Custom Label</label>
                        <input type="text" class="form-control" name="slots[${periodCounter-1}][custom_label]" placeholder="e.g., Math Period"></div>
                    <div class="col-md-5"><label class="form-label">Color Code</label>
                        <input type="color" class="form-control form-control-color" name="slots[${periodCounter-1}][color_code]" value="#007bff"></div>
                    <div class="col-md-2"><label class="form-label">&nbsp;</label>
                        <button type="button" class="btn btn-danger btn-sm w-100" onclick="removePeriod(${periodCounter})"><i class="bi bi-trash"></i> Remove</button></div>
                </div>
            </div>`;
        document.getElementById('periodsContainer').insertAdjacentHTML('beforeend', html);
        applyTimeFormat(getTimeFormat());
    }
    function removePeriod(id) { document.getElementById(`period_${id}`).remove(); }

    document.addEventListener('DOMContentLoaded', function () { applyTimeFormat(getTimeFormat()); });
    document.getElementById('fmt24').addEventListener('change', () => setTimeFormat('24'));
    document.getElementById('fmt12').addEventListener('change', () => setTimeFormat('12'));
    document.getElementById('periodsContainer').addEventListener('input', function (e) {
        if (e.target.classList.contains('time-h') || e.target.classList.contains('time-m')) {
            const wrap = e.target.closest('.time-field-wrap');
            syncToCanonical(wrap.querySelector('.time-canonical'));
        }
    });
    document.getElementById('periodsContainer').addEventListener('change', function (e) {
        if (e.target.classList.contains('time-ampm') || e.target.classList.contains('time-h') || e.target.classList.contains('time-m')) {
            const wrap = e.target.closest('.time-field-wrap');
            syncToCanonical(wrap.querySelector('.time-canonical'));
        }
    });
</script>

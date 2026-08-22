<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bulk Edit Bell Timings - HelpingHand</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        /* Time-format toggle: exactly one of these two widgets is visible per
           time field, driven by [data-time-format] on <body>. The hidden
           canonical input (always HH:mm, 24-hour) is the only thing actually
           submitted -- both widgets just read/write it. Self-contained copy
           of the same pattern used in bulk-create.blade.php. */
        body:not([data-time-format="12"]) .time-input-12h { display: none !important; }
        body[data-time-format="12"] .time-input-24h { display: none !important; }
        .time-input-24h, .time-input-12h { flex-wrap: nowrap; }
        .time-h, .time-m { width: 4.2em; text-align: center; }
        .time-ampm { width: 5.5em; }
    </style>
</head>
<body>
    <div class="container mt-4 mb-5" style="max-width: 800px;">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="bi bi-pencil-square"></i> Bulk Edit Bell Timings</h1>
            <a href="{{ route('bell-timing.bulk-edit') }}" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Back</a>
        </div>

        @if($errors->any())
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle"></i> Please fix the following errors:
                <ul class="mb-0 mt-2">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="card mb-4">
            <div class="card-header bg-secondary text-white"><h5 class="mb-0">Selected</h5></div>
            <div class="card-body">
                <ul class="mb-0">
                    @foreach($groupsSummary as $g)
                        <li>{{ $g['class_section'] ?? 'All Classes' }} &mdash; {{ $g['day_of_week'] }} &mdash; {{ $g['period_count'] }} period{{ $g['period_count'] == 1 ? '' : 's' }}</li>
                    @endforeach
                </ul>
            </div>
        </div>

        <!-- Time Format -->
        <div class="card mb-4">
            <div class="card-body py-2 d-flex align-items-center gap-3 flex-wrap">
                <strong><i class="bi bi-clock"></i> Time Format</strong>
                <div class="btn-group" role="group" aria-label="Time format">
                    <input type="radio" class="btn-check" name="time_format_ui" id="fmt24" autocomplete="off" value="24">
                    <label class="btn btn-outline-primary btn-sm" for="fmt24">24 Hour</label>

                    <input type="radio" class="btn-check" name="time_format_ui" id="fmt12" autocomplete="off" value="12">
                    <label class="btn btn-outline-primary btn-sm" for="fmt12">12 Hour (AM/PM)</label>
                </div>
            </div>
        </div>

        <form action="{{ route('bell-timing.bulk-edit.preview') }}" method="POST" id="targetForm">
            @csrf
            @foreach($selections as $i => $selection)
                <input type="hidden" name="groups[{{ $i }}][selected]" value="1">
                <input type="hidden" name="groups[{{ $i }}][class_section]" value="{{ $selection['class_section'] }}">
                <input type="hidden" name="groups[{{ $i }}][day_of_week]" value="{{ $selection['day_of_week'] }}">
                <input type="hidden" name="groups[{{ $i }}][academic_year]" value="{{ $selection['academic_year'] }}">
                <input type="hidden" name="groups[{{ $i }}][semester]" value="{{ $selection['semester'] }}">
            @endforeach

            <div class="card mb-3">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-bullseye"></i> Which Period?</h5>
                </div>
                <div class="card-body">
                    <label for="target_period_name" class="form-label">Period Name *</label>
                    <select class="form-select @error('target_period_name') is-invalid @enderror" id="target_period_name" name="target_period_name" required>
                        <option value="">Select Period</option>
                        @foreach($periodNames as $name)
                            <option value="{{ $name }}" {{ old('target_period_name') == $name ? 'selected' : '' }}>{{ $name }}</option>
                        @endforeach
                    </select>
                    @error('target_period_name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <div class="form-text">
                        Matched independently per class/day by exact period name -- a schedule where this
                        period doesn't exist, or exists more than once, will be shown as "not updated" on
                        the next screen rather than guessed.
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="bi bi-ui-checks"></i> What to Change</h5>
                </div>
                <div class="card-body">
                    <div class="form-check mb-2">
                        <input class="form-check-input change-toggle" type="checkbox" id="change_time" name="change_time" value="1" {{ old('change_time') == '1' ? 'checked' : '' }}>
                        <label class="form-check-label fw-bold" for="change_time">Change Time</label>
                    </div>
                    <div class="row change-fields" data-for="change_time" style="{{ old('change_time') == '1' ? '' : 'display:none;' }}">
                        <div class="col-md-6">
                            <div class="mb-2 time-field-wrap">
                                <label class="form-label">New Start Time</label>
                                <input type="hidden" name="new_start_time" class="time-canonical @error('new_start_time') is-invalid @enderror" value="{{ old('new_start_time') }}">
                                <div class="time-input-24h d-flex align-items-center gap-1">
                                    <input type="number" class="form-control form-control-sm time-h" min="0" max="23" placeholder="HH">
                                    <span>:</span>
                                    <input type="number" class="form-control form-control-sm time-m" min="0" max="59" placeholder="MM">
                                </div>
                                <div class="time-input-12h d-flex align-items-center gap-1">
                                    <input type="number" class="form-control form-control-sm time-h" min="1" max="12" placeholder="HH">
                                    <span>:</span>
                                    <input type="number" class="form-control form-control-sm time-m" min="0" max="59" placeholder="MM">
                                    <select class="form-select form-select-sm time-ampm">
                                        <option value="AM">AM</option>
                                        <option value="PM">PM</option>
                                    </select>
                                </div>
                                @error('new_start_time')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-2 time-field-wrap">
                                <label class="form-label">New End Time</label>
                                <input type="hidden" name="new_end_time" class="time-canonical @error('new_end_time') is-invalid @enderror" value="{{ old('new_end_time') }}">
                                <div class="time-input-24h d-flex align-items-center gap-1">
                                    <input type="number" class="form-control form-control-sm time-h" min="0" max="23" placeholder="HH">
                                    <span>:</span>
                                    <input type="number" class="form-control form-control-sm time-m" min="0" max="59" placeholder="MM">
                                </div>
                                <div class="time-input-12h d-flex align-items-center gap-1">
                                    <input type="number" class="form-control form-control-sm time-h" min="1" max="12" placeholder="HH">
                                    <span>:</span>
                                    <input type="number" class="form-control form-control-sm time-m" min="0" max="59" placeholder="MM">
                                    <select class="form-select form-select-sm time-ampm">
                                        <option value="AM">AM</option>
                                        <option value="PM">PM</option>
                                    </select>
                                </div>
                                @error('new_end_time')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div class="form-check mb-2 mt-3">
                        <input class="form-check-input change-toggle" type="checkbox" id="change_period_name" name="change_period_name" value="1" {{ old('change_period_name') == '1' ? 'checked' : '' }}>
                        <label class="form-check-label fw-bold" for="change_period_name">Rename Period</label>
                    </div>
                    <div class="change-fields" data-for="change_period_name" style="{{ old('change_period_name') == '1' ? '' : 'display:none;' }}">
                        <input type="text" class="form-control @error('new_period_name') is-invalid @enderror" name="new_period_name" value="{{ old('new_period_name') }}" placeholder="e.g., Period 3">
                        @error('new_period_name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="form-check mb-2 mt-3">
                        <input class="form-check-input change-toggle" type="checkbox" id="change_custom_label" name="change_custom_label" value="1" {{ old('change_custom_label') == '1' ? 'checked' : '' }}>
                        <label class="form-check-label fw-bold" for="change_custom_label">Change Custom Label</label>
                    </div>
                    <div class="change-fields" data-for="change_custom_label" style="{{ old('change_custom_label') == '1' ? '' : 'display:none;' }}">
                        <input type="text" class="form-control @error('new_custom_label') is-invalid @enderror" name="new_custom_label" value="{{ old('new_custom_label') }}" placeholder="e.g., Math Period">
                        @error('new_custom_label')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="form-check mb-2 mt-3">
                        <input class="form-check-input change-toggle" type="checkbox" id="change_color_code" name="change_color_code" value="1" {{ old('change_color_code') == '1' ? 'checked' : '' }}>
                        <label class="form-check-label fw-bold" for="change_color_code">Change Color</label>
                    </div>
                    <div class="change-fields" data-for="change_color_code" style="{{ old('change_color_code') == '1' ? '' : 'display:none;' }}">
                        <input type="color" class="form-control form-control-color @error('new_color_code') is-invalid @enderror" name="new_color_code" value="{{ old('new_color_code', '#007bff') }}">
                        @error('new_color_code')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-warning btn-lg">
                <i class="bi bi-eye"></i> Review Changes
            </button>
        </form>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Same self-contained dual-widget time pattern as bulk-create.blade.php.
        const TIME_FORMAT_STORAGE_KEY = 'bulkBellTimingTimeFormat';

        function pad2(n) { return String(n).padStart(2, '0'); }

        function to12h(hhmm) {
            if (!hhmm || !/^\d{1,2}:\d{2}/.test(hhmm)) return { h: '', m: '', ampm: 'AM' };
            const [H, M] = hhmm.split(':').map(Number);
            const h12 = (H % 12) || 12;
            const ampm = H < 12 ? 'AM' : 'PM';
            return { h: h12, m: M, ampm };
        }

        function from12h(h, m, ampm) {
            let H = parseInt(h, 10);
            const M = parseInt(m, 10);
            if (isNaN(H) || isNaN(M)) return '';
            if (ampm === 'PM' && H !== 12) H += 12;
            if (ampm === 'AM' && H === 12) H = 0;
            return pad2(H) + ':' + pad2(M);
        }

        function from24h(h, m) {
            const H = parseInt(h, 10);
            const M = parseInt(m, 10);
            if (isNaN(H) || isNaN(M)) return '';
            return pad2(H) + ':' + pad2(M);
        }

        function getTimeFormat() {
            return localStorage.getItem(TIME_FORMAT_STORAGE_KEY) || '24';
        }

        function syncToCanonical(canonicalInput) {
            const wrap = canonicalInput.closest('.time-field-wrap');
            if (!wrap) return;
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
            const wrap = canonicalInput.closest('.time-field-wrap');
            if (!wrap) return;
            const value24 = canonicalInput.value;
            const parts = (value24 || '').split(':');
            const h24 = parts[0] !== undefined && parts[0] !== '' ? parseInt(parts[0], 10) : '';
            const m = parts[1] !== undefined && parts[1] !== '' ? parseInt(parts[1], 10) : '';
            const c24 = wrap.querySelector('.time-input-24h');
            c24.querySelector('.time-h').value = (h24 === '' || isNaN(h24)) ? '' : h24;
            c24.querySelector('.time-m').value = (m === '' || isNaN(m)) ? '' : m;
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

        function setTimeFormat(mode) {
            localStorage.setItem(TIME_FORMAT_STORAGE_KEY, mode);
            applyTimeFormat(mode);
        }

        document.addEventListener('DOMContentLoaded', function () {
            applyTimeFormat(getTimeFormat());
        });

        document.getElementById('fmt24').addEventListener('change', () => setTimeFormat('24'));
        document.getElementById('fmt12').addEventListener('change', () => setTimeFormat('12'));

        document.getElementById('targetForm').addEventListener('input', function (e) {
            if (e.target.classList.contains('time-h') || e.target.classList.contains('time-m')) {
                const wrap = e.target.closest('.time-field-wrap');
                syncToCanonical(wrap.querySelector('.time-canonical'));
            }
        });
        document.getElementById('targetForm').addEventListener('change', function (e) {
            if (e.target.classList.contains('time-ampm') || e.target.classList.contains('time-h') || e.target.classList.contains('time-m')) {
                const wrap = e.target.closest('.time-field-wrap');
                syncToCanonical(wrap.querySelector('.time-canonical'));
            }
        });

        // Toggle the visibility of each field group alongside its checkbox --
        // purely a display convenience; the backend independently validates
        // required_if against the checkbox value regardless of what's shown.
        document.querySelectorAll('.change-toggle').forEach(function (toggle) {
            toggle.addEventListener('change', function () {
                const target = document.querySelector('.change-fields[data-for="' + toggle.id + '"]');
                if (target) target.style.display = toggle.checked ? '' : 'none';
            });
        });
    </script>
</body>
</html>

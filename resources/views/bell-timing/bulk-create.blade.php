<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bulk Create Bell Timings - HelpingHand</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        .period-row {
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
            padding: 1rem;
            margin-bottom: 1rem;
            background-color: #f8f9fa;
        }
        .day-selector {
            cursor: pointer;
            transition: all 0.2s;
        }
        .day-selector:hover {
            transform: scale(1.05);
        }
        .day-selector.selected {
            background-color: #007bff;
            color: white;
            border-color: #007bff !important;
        }
        /* Time-format toggle: exactly one of these two widgets is visible per
           time field, driven by [data-time-format] on <body>. The hidden
           canonical input (always HH:mm, 24-hour) is the only thing actually
           submitted -- both widgets just read/write it. */
        /* !important needed: Bootstrap's .d-flex utility (used on both
           widgets for their internal layout) is itself !important, so a
           plain display:none here would never win against it. */
        body:not([data-time-format="12"]) .time-input-12h { display: none !important; }
        body[data-time-format="12"] .time-input-24h { display: none !important; }
        .time-input-24h, .time-input-12h { flex-wrap: nowrap; }
        .time-h, .time-m { width: 4.2em; text-align: center; }
        .time-ampm { width: 5.5em; }
        /* Bootstrap's .is-invalid adds a background-image (a red circled
           exclamation icon) plus right-padding to make room for it. At
           these narrow widths that icon completely overlaps the 1-2 digit
           value, visually replacing it -- the number is still there and
           still editable, it's just invisible. The red border alone is
           already a clear enough invalid indicator here (the error message
           is also shown directly below the field), so drop the icon/padding
           for just these narrow inputs and keep the border. */
        .time-h.is-invalid, .time-m.is-invalid, .time-ampm.is-invalid {
            background-image: none !important;
            padding-right: 0.5rem !important;
        }
    </style>
</head>
<body>
    @php
        // Restore whatever the user typed before a validation failure.
        // Laravel already flashes `periods`/`days`/etc. to the session on a
        // failed $request->validate() -- the only thing missing was the
        // view actually reading it back. Falls back to a single blank
        // period row on a genuinely fresh visit, matching prior behavior.
        $oldPeriods = old('periods', []);
        if (empty($oldPeriods)) {
            $oldPeriods = [1 => ['order_index' => 1]];
        }
        $maxPeriodIndex = max(array_keys($oldPeriods));
        $oldDays = old('days', []);

        // Splits a canonical "HH:mm" (24-hour) string into both the
        // 24-hour and 12-hour representations the two widgets need. The
        // canonical value itself never changes shape -- this is purely for
        // pre-filling the visible controls on first render.
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
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="bi bi-file-earmark-plus"></i> Bulk Create Bell Timings</h1>
            <a href="{{ route('bell-timing.index') }}" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Back to List
            </a>
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
                <span class="small text-muted">Switching format converts your entered times -- nothing is lost.</span>
            </div>
        </div>

        <!-- Flash Messages -->
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle"></i> {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @if(session('errors'))
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle"></i> Some errors occurred:
                <ul class="mb-0 mt-2">
                    @foreach(session('errors') as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

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

        <form action="{{ route('bell-timing.bulk-create') }}" method="POST" id="bulkForm">
            @csrf

            <div class="row">
                <div class="col-md-8">
                    <!-- Days Selection -->
                    <div class="card mb-4">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="bi bi-calendar-check"></i> Select Days</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                @foreach($daysOfWeek as $day)
                                    <div class="col-md-4 mb-2">
                                        <div class="form-check day-selector border p-3 rounded {{ in_array($day, $oldDays) ? 'selected' : '' }}"
                                             onclick="toggleDay(this, '{{ $day }}')">
                                            <input type="checkbox" class="form-check-input d-none"
                                                   name="days[]" value="{{ $day }}" id="day_{{ $day }}"
                                                   {{ in_array($day, $oldDays) ? 'checked' : '' }}>
                                            <label class="form-check-label fw-bold mb-0" for="day_{{ $day }}">
                                                <i class="bi bi-calendar-day"></i> {{ $day }}
                                            </label>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                            <div class="mt-2">
                                <button type="button" class="btn btn-outline-primary btn-sm" onclick="selectAllDays()">
                                    <i class="bi bi-check-all"></i> Select All
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="clearAllDays()">
                                    <i class="bi bi-x-circle"></i> Clear All
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Class and Academic Year -->
                    <div class="card mb-4">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0"><i class="bi bi-mortarboard"></i> Class Information</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="class_section" class="form-label">Class/Section *</label>
                                        <select class="form-select" id="class_section" name="class_section" required>
                                            <option value="">Select Class</option>
                                            @foreach($classSections as $section)
                                                <option value="{{ $section }}" {{ old('class_section') == $section ? 'selected' : '' }}>{{ $section }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="academic_year" class="form-label">Academic Year *</label>
                                        <select class="form-select" id="academic_year" name="academic_year" required>
                                            <option value="">Select Year</option>
                                            @foreach($academicYears as $year)
                                                <option value="{{ $year }}" {{ old('academic_year') == $year ? 'selected' : '' }}>{{ $year }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="semester" class="form-label">Semester</label>
                                <select class="form-select" id="semester" name="semester">
                                    <option value="">Select Semester</option>
                                    <option value="First" {{ old('semester') == 'First' ? 'selected' : '' }}>First</option>
                                    <option value="Second" {{ old('semester') == 'Second' ? 'selected' : '' }}>Second</option>
                                    <option value="Third" {{ old('semester') == 'Third' ? 'selected' : '' }}>Third</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Periods Configuration -->
                    <div class="card">
                        <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="bi bi-list-ol"></i> Configure Periods</h5>
                            <button type="button" class="btn btn-light btn-sm" onclick="addPeriod()">
                                <i class="bi bi-plus-circle"></i> Add Period
                            </button>
                        </div>
                        <div class="card-body">
                            <div id="periodsContainer">
                                @foreach($oldPeriods as $i => $p)
                                    @php
                                        $st = $splitTime($p['start_time'] ?? null);
                                        $et = $splitTime($p['end_time'] ?? null);
                                    @endphp
                                    <div class="period-row" id="period_{{ $i }}">
                                        <div class="row">
                                            <div class="col-md-3">
                                                <div class="mb-2">
                                                    <label class="form-label">Period Name *</label>
                                                    <input type="text" class="form-control @error('periods.'.$i.'.period_name') is-invalid @enderror"
                                                           name="periods[{{ $i }}][period_name]"
                                                           value="{{ $p['period_name'] ?? '' }}"
                                                           placeholder="e.g., Period 1" required>
                                                    @error('periods.'.$i.'.period_name')
                                                        <div class="invalid-feedback">{{ $message }}</div>
                                                    @enderror
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="mb-2 time-field-wrap">
                                                    <label class="form-label">Start Time *</label>
                                                    <input type="hidden" name="periods[{{ $i }}][start_time]"
                                                           class="time-canonical @error('periods.'.$i.'.start_time') is-invalid @enderror"
                                                           value="{{ $p['start_time'] ?? '' }}">
                                                    <div class="time-input-24h d-flex align-items-center gap-1">
                                                        <input type="number" class="form-control form-control-sm time-h @error('periods.'.$i.'.start_time') is-invalid @enderror" min="0" max="23" placeholder="HH" value="{{ $st['h24'] }}">
                                                        <span>:</span>
                                                        <input type="number" class="form-control form-control-sm time-m @error('periods.'.$i.'.start_time') is-invalid @enderror" min="0" max="59" placeholder="MM" value="{{ $st['m'] }}">
                                                    </div>
                                                    <div class="time-input-12h d-flex align-items-center gap-1">
                                                        <input type="number" class="form-control form-control-sm time-h @error('periods.'.$i.'.start_time') is-invalid @enderror" min="1" max="12" placeholder="HH" value="{{ $st['h12'] }}">
                                                        <span>:</span>
                                                        <input type="number" class="form-control form-control-sm time-m @error('periods.'.$i.'.start_time') is-invalid @enderror" min="0" max="59" placeholder="MM" value="{{ $st['m'] }}">
                                                        <select class="form-select form-select-sm time-ampm">
                                                            <option value="AM" {{ $st['ampm'] == 'AM' ? 'selected' : '' }}>AM</option>
                                                            <option value="PM" {{ $st['ampm'] == 'PM' ? 'selected' : '' }}>PM</option>
                                                        </select>
                                                    </div>
                                                    @error('periods.'.$i.'.start_time')
                                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                                    @enderror
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="mb-2 time-field-wrap">
                                                    <label class="form-label">End Time *</label>
                                                    <input type="hidden" name="periods[{{ $i }}][end_time]"
                                                           class="time-canonical @error('periods.'.$i.'.end_time') is-invalid @enderror"
                                                           value="{{ $p['end_time'] ?? '' }}">
                                                    <div class="time-input-24h d-flex align-items-center gap-1">
                                                        <input type="number" class="form-control form-control-sm time-h @error('periods.'.$i.'.end_time') is-invalid @enderror" min="0" max="23" placeholder="HH" value="{{ $et['h24'] }}">
                                                        <span>:</span>
                                                        <input type="number" class="form-control form-control-sm time-m @error('periods.'.$i.'.end_time') is-invalid @enderror" min="0" max="59" placeholder="MM" value="{{ $et['m'] }}">
                                                    </div>
                                                    <div class="time-input-12h d-flex align-items-center gap-1">
                                                        <input type="number" class="form-control form-control-sm time-h @error('periods.'.$i.'.end_time') is-invalid @enderror" min="1" max="12" placeholder="HH" value="{{ $et['h12'] }}">
                                                        <span>:</span>
                                                        <input type="number" class="form-control form-control-sm time-m @error('periods.'.$i.'.end_time') is-invalid @enderror" min="0" max="59" placeholder="MM" value="{{ $et['m'] }}">
                                                        <select class="form-select form-select-sm time-ampm">
                                                            <option value="AM" {{ $et['ampm'] == 'AM' ? 'selected' : '' }}>AM</option>
                                                            <option value="PM" {{ $et['ampm'] == 'PM' ? 'selected' : '' }}>PM</option>
                                                        </select>
                                                    </div>
                                                    @error('periods.'.$i.'.end_time')
                                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                                    @enderror
                                                </div>
                                            </div>
                                            <div class="col-md-2">
                                                <div class="mb-2">
                                                    <label class="form-label">Order</label>
                                                    <input type="number" class="form-control @error('periods.'.$i.'.order_index') is-invalid @enderror"
                                                           name="periods[{{ $i }}][order_index]"
                                                           value="{{ $p['order_index'] ?? $i }}" min="0">
                                                    @error('periods.'.$i.'.order_index')
                                                        <div class="invalid-feedback">{{ $message }}</div>
                                                    @enderror
                                                </div>
                                            </div>
                                            <div class="col-md-1">
                                                <div class="mb-2">
                                                    <label class="form-label">Type</label>
                                                    <select class="form-select" name="periods[{{ $i }}][is_break]">
                                                        <option value="0" {{ (string) ($p['is_break'] ?? '0') === '0' ? 'selected' : '' }}>Class</option>
                                                        <option value="1" {{ (string) ($p['is_break'] ?? '0') === '1' ? 'selected' : '' }}>Break</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-5">
                                                <div class="mb-2">
                                                    <label class="form-label">Custom Label</label>
                                                    <input type="text" class="form-control" name="periods[{{ $i }}][custom_label]"
                                                           value="{{ $p['custom_label'] ?? '' }}" placeholder="e.g., Math Period">
                                                </div>
                                            </div>
                                            <div class="col-md-5">
                                                <div class="mb-2">
                                                    <label class="form-label">Color Code</label>
                                                    <input type="color" class="form-control form-control-color"
                                                           name="periods[{{ $i }}][color_code]" value="{{ $p['color_code'] ?? '#007bff' }}">
                                                </div>
                                            </div>
                                            <div class="col-md-2">
                                                <div class="mb-2">
                                                    <label class="form-label">&nbsp;</label>
                                                    <button type="button" class="btn btn-danger btn-sm w-100" onclick="removePeriod({{ $i }})">
                                                        <i class="bi bi-trash"></i> Remove
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            <div class="mt-3">
                                <button type="button" class="btn btn-outline-primary" onclick="addCommonPeriods()">
                                    <i class="bi bi-magic"></i> Add Common Schedule
                                </button>
                                <button type="button" class="btn btn-outline-secondary" onclick="clearAllPeriods()">
                                    <i class="bi bi-trash"></i> Clear All Periods
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header bg-warning text-dark">
                            <h5 class="mb-0"><i class="bi bi-lightbulb"></i> Instructions</h5>
                        </div>
                        <div class="card-body">
                            <ol>
                                <li>Select the days you want to create schedules for</li>
                                <li>Choose the class/section and academic year</li>
                                <li>Add periods with their start/end times</li>
                                <li>Mark break periods if applicable</li>
                                <li>Review all settings before submitting</li>
                            </ol>
                            <hr>
                            <h6><i class="bi bi-exclamation-triangle"></i> Important Notes:</h6>
                            <ul>
                                <li>All selected days will get the same period structure</li>
                                <li>Time conflicts will be checked automatically</li>
                                <li>Existing schedules will not be overwritten</li>
                                <li>You can edit individual schedules after creation</li>
                            </ul>
                        </div>
                    </div>

                    <div class="card mt-4">
                        <div class="card-header bg-secondary text-white">
                            <h5 class="mb-0"><i class="bi bi-check-circle"></i> Review & Submit</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label">Selected Days:</label>
                                <div id="selectedDaysPreview" class="small text-muted">None selected</div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Periods to Create:</label>
                                <div id="periodsCountPreview" class="small text-muted">0 periods</div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Total Schedules:</label>
                                <div id="totalSchedulesPreview" class="small text-muted">0 schedules</div>
                            </div>
                            <button type="submit" class="btn btn-success w-100" id="submitBtn" disabled>
                                <i class="bi bi-save"></i> Create All Schedules
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Starts past the highest index already rendered server-side (from
        // old('periods') on a validation-failure reload, or 1 for a fresh
        // blank row) so a subsequent "Add Period" click never collides with
        // a restored row's name="periods[N][...]" index.
        let periodCounter = {{ $maxPeriodIndex }};

        // ---------------------------------------------------------------
        // Time format (12-hour / 24-hour) support.
        //
        // The hidden `.time-canonical` input is the single source of truth
        // -- always "HH:mm" 24-hour, exactly what's submitted and exactly
        // what the backend already validates. The two visible widgets
        // (.time-input-24h / .time-input-12h) are just alternate ways to
        // read and write that same value; only one is ever visible at a
        // time, chosen by [data-time-format] on <body>. This avoids
        // depending on the browser's native <input type="time"> picker,
        // whose displayed AM/PM behavior is locale-dependent and was the
        // root cause of the original "01:30" (meant 1:30 PM, read as 1:30
        // AM) confusion.
        // ---------------------------------------------------------------
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

        // Builds one Start/End Time field: hidden canonical + both widgets.
        function timeFieldHtml(idx, field, label, value24) {
            value24 = value24 || '';
            const parts = value24.split(':');
            const h24 = parts[0] !== undefined && parts[0] !== '' ? parseInt(parts[0], 10) : '';
            const m = parts[1] !== undefined && parts[1] !== '' ? parseInt(parts[1], 10) : '';
            const t12 = to12h(value24);
            return `
                <div class="mb-2 time-field-wrap">
                    <label class="form-label">${label} *</label>
                    <input type="hidden" name="periods[${idx}][${field}]" class="time-canonical" value="${value24}">
                    <div class="time-input-24h d-flex align-items-center gap-1">
                        <input type="number" class="form-control form-control-sm time-h" min="0" max="23" placeholder="HH" value="${h24 === '' ? '' : h24}">
                        <span>:</span>
                        <input type="number" class="form-control form-control-sm time-m" min="0" max="59" placeholder="MM" value="${m === '' ? '' : m}">
                    </div>
                    <div class="time-input-12h d-flex align-items-center gap-1">
                        <input type="number" class="form-control form-control-sm time-h" min="1" max="12" placeholder="HH" value="${t12.h}">
                        <span>:</span>
                        <input type="number" class="form-control form-control-sm time-m" min="0" max="59" placeholder="MM" value="${t12.m}">
                        <select class="form-select form-select-sm time-ampm">
                            <option value="AM" ${t12.ampm === 'AM' ? 'selected' : ''}>AM</option>
                            <option value="PM" ${t12.ampm === 'PM' ? 'selected' : ''}>PM</option>
                        </select>
                    </div>
                </div>
            `;
        }

        // Recomputes the hidden canonical value from whichever widget is
        // currently visible (i.e. whatever the user is actually editing).
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

        // Re-populates BOTH widgets from the canonical value -- used when
        // switching formats, so the format that's about to become visible
        // always shows the correct converted numbers.
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

        function toggleDay(element, dayValue) {
            const checkbox = element.querySelector('input[type="checkbox"]');
            const isSelected = checkbox.checked;

            checkbox.checked = !isSelected;
            element.classList.toggle('selected', !isSelected);

            updatePreview();
        }

        function selectAllDays() {
            document.querySelectorAll('.day-selector').forEach(element => {
                const checkbox = element.querySelector('input[type="checkbox"]');
                checkbox.checked = true;
                element.classList.add('selected');
            });
            updatePreview();
        }

        function clearAllDays() {
            document.querySelectorAll('.day-selector').forEach(element => {
                const checkbox = element.querySelector('input[type="checkbox"]');
                checkbox.checked = false;
                element.classList.remove('selected');
            });
            updatePreview();
        }

        function addPeriod() {
            periodCounter++;
            const container = document.getElementById('periodsContainer');

            const periodHtml = `
                <div class="period-row" id="period_${periodCounter}">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="mb-2">
                                <label class="form-label">Period Name *</label>
                                <input type="text" class="form-control" name="periods[${periodCounter}][period_name]"
                                       placeholder="e.g., Period 1" required>
                            </div>
                        </div>
                        <div class="col-md-3">
                            ${timeFieldHtml(periodCounter, 'start_time', 'Start Time', '')}
                        </div>
                        <div class="col-md-3">
                            ${timeFieldHtml(periodCounter, 'end_time', 'End Time', '')}
                        </div>
                        <div class="col-md-2">
                            <div class="mb-2">
                                <label class="form-label">Order</label>
                                <input type="number" class="form-control" name="periods[${periodCounter}][order_index]"
                                       value="${periodCounter}" min="0">
                            </div>
                        </div>
                        <div class="col-md-1">
                            <div class="mb-2">
                                <label class="form-label">Type</label>
                                <select class="form-select" name="periods[${periodCounter}][is_break]">
                                    <option value="0">Class</option>
                                    <option value="1">Break</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-5">
                            <div class="mb-2">
                                <label class="form-label">Custom Label</label>
                                <input type="text" class="form-control" name="periods[${periodCounter}][custom_label]"
                                       placeholder="e.g., Math Period">
                            </div>
                        </div>
                        <div class="col-md-5">
                            <div class="mb-2">
                                <label class="form-label">Color Code</label>
                                <input type="color" class="form-control form-control-color"
                                       name="periods[${periodCounter}][color_code]" value="#007bff">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="mb-2">
                                <label class="form-label">&nbsp;</label>
                                <button type="button" class="btn btn-danger btn-sm w-100" onclick="removePeriod(${periodCounter})">
                                    <i class="bi bi-trash"></i> Remove
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            container.insertAdjacentHTML('beforeend', periodHtml);
            applyTimeFormat(getTimeFormat());
            updatePreview();
        }

        function removePeriod(id) {
            document.getElementById(`period_${id}`).remove();
            updatePreview();
        }

        function addCommonPeriods() {
            // Clear existing periods
            document.getElementById('periodsContainer').innerHTML = '';
            periodCounter = 0;

            // Add common school periods
            const commonPeriods = [
                {name: 'Morning Assembly', start: '08:00', end: '08:30', break: 1},
                {name: 'Period 1', start: '08:30', end: '09:20', break: 0},
                {name: 'Period 2', start: '09:20', end: '10:10', break: 0},
                {name: 'Break', start: '10:10', end: '10:30', break: 1},
                {name: 'Period 3', start: '10:30', end: '11:20', break: 0},
                {name: 'Period 4', start: '11:20', end: '12:10', break: 0},
                {name: 'Lunch Break', start: '12:10', end: '13:00', break: 1},
                {name: 'Period 5', start: '13:00', end: '13:50', break: 0},
                {name: 'Period 6', start: '13:50', end: '14:40', break: 0}
            ];

            commonPeriods.forEach((period, index) => {
                periodCounter++;
                const container = document.getElementById('periodsContainer');

                const periodHtml = `
                    <div class="period-row" id="period_${periodCounter}">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="mb-2">
                                    <label class="form-label">Period Name *</label>
                                    <input type="text" class="form-control" name="periods[${periodCounter}][period_name]"
                                           value="${period.name}" required>
                                </div>
                            </div>
                            <div class="col-md-3">
                                ${timeFieldHtml(periodCounter, 'start_time', 'Start Time', period.start)}
                            </div>
                            <div class="col-md-3">
                                ${timeFieldHtml(periodCounter, 'end_time', 'End Time', period.end)}
                            </div>
                            <div class="col-md-2">
                                <div class="mb-2">
                                    <label class="form-label">Order</label>
                                    <input type="number" class="form-control" name="periods[${periodCounter}][order_index]"
                                           value="${index}" min="0">
                                </div>
                            </div>
                            <div class="col-md-1">
                                <div class="mb-2">
                                    <label class="form-label">Type</label>
                                    <select class="form-select" name="periods[${periodCounter}][is_break]">
                                        <option value="0" ${period.break === 0 ? 'selected' : ''}>Class</option>
                                        <option value="1" ${period.break === 1 ? 'selected' : ''}>Break</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-5">
                                <div class="mb-2">
                                    <label class="form-label">Custom Label</label>
                                    <input type="text" class="form-control" name="periods[${periodCounter}][custom_label]">
                                </div>
                            </div>
                            <div class="col-md-5">
                                <div class="mb-2">
                                    <label class="form-label">Color Code</label>
                                    <input type="color" class="form-control form-control-color"
                                           name="periods[${periodCounter}][color_code]" value="#${period.break ? 'ffc107' : '007bff'}">
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="mb-2">
                                    <label class="form-label">&nbsp;</label>
                                    <button type="button" class="btn btn-danger btn-sm w-100" onclick="removePeriod(${periodCounter})">
                                        <i class="bi bi-trash"></i> Remove
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                `;

                container.insertAdjacentHTML('beforeend', periodHtml);
            });

            applyTimeFormat(getTimeFormat());
            updatePreview();
        }

        function clearAllPeriods() {
            document.getElementById('periodsContainer').innerHTML = '';
            periodCounter = 0;
            updatePreview();
        }

        function updatePreview() {
            // Update selected days preview
            const selectedDays = Array.from(document.querySelectorAll('input[name="days[]"]:checked'))
                                    .map(cb => cb.value);
            const daysPreview = document.getElementById('selectedDaysPreview');
            daysPreview.textContent = selectedDays.length > 0 ? selectedDays.join(', ') : 'None selected';

            // Update periods count
            const periodsCount = document.querySelectorAll('#periodsContainer > .period-row').length;
            document.getElementById('periodsCountPreview').textContent = `${periodsCount} periods`;

            // Update total schedules
            const totalSchedules = selectedDays.length * periodsCount;
            document.getElementById('totalSchedulesPreview').textContent = `${totalSchedules} schedules (${selectedDays.length} days × ${periodsCount} periods)`;

            // Enable/disable submit button
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.disabled = !(selectedDays.length > 0 && periodsCount > 0);
        }

        // Period rows are now rendered server-side (from old('periods'), or
        // a single default blank row on a fresh visit) so a
        // validation-failure reload no longer wipes out what the user
        // already typed. Just sync the preview panel and time-format
        // widgets to whatever rows are already in the DOM.
        document.addEventListener('DOMContentLoaded', function() {
            applyTimeFormat(getTimeFormat());
            updatePreview();
        });

        document.getElementById('fmt24').addEventListener('change', () => setTimeFormat('24'));
        document.getElementById('fmt12').addEventListener('change', () => setTimeFormat('12'));

        // Event delegation: covers period rows added later by addPeriod()/
        // addCommonPeriods() too, without needing to re-wire listeners.
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

        // Update preview when form changes
        document.addEventListener('change', updatePreview);
        document.addEventListener('input', updatePreview);
    </script>
</body>
</html>

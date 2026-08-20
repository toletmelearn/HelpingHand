<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Before Applying - HelpingHand</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body>
    <div class="container mt-4 mb-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="bi bi-eye"></i> Review Before Applying</h1>
            <a href="{{ route('bell-timing-templates.apply.form', $template) }}" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Back</a>
        </div>

        <div class="alert alert-info">
            <strong>Template:</strong> {{ $template->name }} ({{ $template->slots->count() }} periods) &nbsp;|&nbsp;
            <strong>Days:</strong> {{ implode(', ', $days) }}
        </div>

        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        @php
            $noneCount = collect($comparisons)->where('status', 'none')->count();
            $sameCount = collect($comparisons)->where('status', 'same')->count();
            $diffCount = collect($comparisons)->where('status', 'different')->count();
        @endphp
        <div class="alert alert-secondary">
            <strong>Summary:</strong>
            {{ $noneCount }} class(es) have no existing schedule (safe to apply directly).
            {{ $sameCount }} class(es) already have the same structure.
            {{ $diffCount }} class(es) have a different structure and need your decision.
        </div>

        <form action="{{ route('bell-timing-templates.apply.confirm', $template) }}" method="POST" id="applyForm">
            @csrf
            @foreach($days as $day)
                <input type="hidden" name="days[]" value="{{ $day }}">
            @endforeach
            <input type="hidden" name="academic_year" value="{{ $academicYear }}">
            <input type="hidden" name="semester" value="{{ $semester }}">

            @foreach($comparisons as $classSection => $cmp)
                <div class="card mb-3">
                    <div class="card-header d-flex justify-content-between align-items-center
                        {{ $cmp['status']=='none' ? 'bg-light' : ($cmp['status']=='same' ? 'bg-success text-white' : 'bg-warning') }}">
                        <strong>{{ $classSection }}</strong>
                        <span>
                            @if($cmp['status']=='none')
                                <i class="bi bi-plus-circle"></i> No existing schedule -- will be added
                            @elseif($cmp['status']=='same')
                                <i class="bi bi-check-circle"></i> Same structure ({{ $cmp['existing_count'] }} periods)
                            @else
                                <i class="bi bi-exclamation-triangle"></i> Different structure ({{ $cmp['existing_count'] }} existing vs {{ $cmp['template_count'] }} in template)
                            @endif
                        </span>
                    </div>
                    <div class="card-body">
                        @if($cmp['status']=='none')
                            <input type="hidden" name="decisions[{{ $classSection }}][action]" value="apply">
                            <p class="text-muted mb-0 small">This class will receive all {{ $cmp['template_count'] }} periods from the template on {{ implode(', ', $days) }}.</p>
                        @else
                            @if(!empty($cmp['existing']))
                                <table class="table table-sm">
                                    <thead><tr><th>Existing Period</th><th>Time</th><th>Type</th></tr></thead>
                                    <tbody>
                                        @foreach($cmp['existing'] as $ex)
                                            <tr><td>{{ $ex['period_name'] }}</td><td>{{ $ex['start_time'] }}-{{ $ex['end_time'] }}</td><td>{{ $ex['is_break'] ? 'Break' : 'Class' }}</td></tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            @endif

                            <div class="mb-2">
                                <label class="form-label">What should happen to {{ $classSection }}?</label>
                                <select class="form-select decision-select" data-class="{{ $classSection }}" onchange="toggleCustomize(this)">
                                    <option value="skip" selected>Skip this class (leave it unchanged)</option>
                                    @if($cmp['status']=='same')
                                        <option value="replace">Replace with template (confirm overwrite)</option>
                                    @else
                                        <option value="customize">Customize (choose which periods to apply)</option>
                                        <option value="copy_matching">Copy Matching Slots (first {{ $cmp['existing_count'] }} periods only)</option>
                                    @endif
                                </select>
                            </div>

                            @if($cmp['status']=='different')
                                <div class="customize-block border rounded p-3 bg-light" id="customize-{{ \Illuminate\Support\Str::slug($classSection) }}" style="display:none;">
                                    <p class="small text-muted">Uncheck any period you don't want applied to {{ $classSection }}:</p>
                                    @foreach($template->slots as $i => $slot)
                                        <div class="row align-items-center mb-1 customize-row" data-class="{{ $classSection }}">
                                            <div class="col-auto">
                                                <input type="checkbox" class="form-check-input include-check" checked>
                                            </div>
                                            <div class="col">{{ $slot->period_name }} ({{ $slot->start_time->format('h:i A') }} - {{ $slot->end_time->format('h:i A') }}) {{ $slot->is_break ? '[Break]' : '' }}</div>
                                            <input type="hidden" class="slot-field" data-field="period_name" value="{{ $slot->period_name }}">
                                            <input type="hidden" class="slot-field" data-field="start_time" value="{{ $slot->start_time->format('H:i') }}">
                                            <input type="hidden" class="slot-field" data-field="end_time" value="{{ $slot->end_time->format('H:i') }}">
                                            <input type="hidden" class="slot-field" data-field="is_break" value="{{ $slot->is_break ? '1' : '0' }}">
                                            <input type="hidden" class="slot-field" data-field="period_type" value="{{ $slot->period_type }}">
                                            <input type="hidden" class="slot-field" data-field="custom_label" value="{{ $slot->custom_label }}">
                                            <input type="hidden" class="slot-field" data-field="color_code" value="{{ $slot->color_code }}">
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        @endif
                    </div>
                </div>
            @endforeach

            <div class="d-flex gap-2 mt-4">
                <button type="submit" class="btn btn-primary btn-lg"><i class="bi bi-check-circle"></i> Apply Schedule</button>
                <a href="{{ route('bell-timing-templates.index') }}" class="btn btn-outline-secondary btn-lg">Cancel</a>
            </div>
        </form>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleCustomize(select) {
            const cls = select.dataset.class;
            const block = document.getElementById('customize-' + slugify(cls));
            if (!block) return;
            block.style.display = select.value === 'customize' ? 'block' : 'none';
        }
        function slugify(s) {
            return s.toLowerCase().trim().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '');
        }

        // On submit: turn each decision-select into decisions[Class][action],
        // and for any class set to "customize", build decisions[Class][slots][n][field]
        // hidden inputs from only the checked rows.
        document.getElementById('applyForm').addEventListener('submit', function () {
            document.querySelectorAll('.decision-select').forEach(function (select) {
                const cls = select.dataset.class;
                const action = select.value;
                const hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = `decisions[${cls}][action]`;
                hidden.value = action;
                select.closest('.card-body').appendChild(hidden);

                if (action === 'customize') {
                    const block = document.getElementById('customize-' + slugify(cls));
                    let n = 0;
                    block.querySelectorAll('.customize-row').forEach(function (row) {
                        const included = row.querySelector('.include-check').checked;
                        if (!included) return;
                        row.querySelectorAll('.slot-field').forEach(function (field) {
                            const h = document.createElement('input');
                            h.type = 'hidden';
                            h.name = `decisions[${cls}][slots][${n}][${field.dataset.field}]`;
                            h.value = field.value;
                            block.appendChild(h);
                        });
                        n++;
                    });
                }
            });
        });
    </script>
</body>
</html>

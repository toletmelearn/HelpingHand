<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bulk Delete Bell Timings - HelpingHand</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body>
    <div class="container mt-4 mb-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="bi bi-trash3"></i> Bulk Delete Bell Timings</h1>
            <a href="{{ route('bell-timing.index') }}" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Back to List</a>
        </div>

        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif
        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle"></i>
            Select the class/day schedules you want to delete. The next screen will show exactly which
            periods are safe to delete and which are blocked because they are still in use -- nothing is
            deleted from this page.
        </div>

        @if($groups->isEmpty())
            <div class="alert alert-info">No Bell Timing schedules exist yet.</div>
        @else
            <form action="{{ route('bell-timing.bulk-delete.preview') }}" method="POST">
                @csrf
                <div class="card mb-3">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-list-check"></i> Select Schedules</h5>
                        <div>
                            <button type="button" class="btn btn-light btn-sm" onclick="toggleAll(true)">Select All</button>
                            <button type="button" class="btn btn-light btn-sm" onclick="toggleAll(false)">Clear All</button>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th style="width: 40px;"></th>
                                    <th>Class</th>
                                    <th>Day</th>
                                    <th>Periods</th>
                                    <th>Academic Year</th>
                                    <th>Semester</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($groups as $i => $g)
                                    <tr>
                                        <td>
                                            <input type="checkbox" class="form-check-input group-check" id="grp{{ $i }}"
                                                   name="groups[{{ $i }}][selected]" value="1">
                                            <input type="hidden" name="groups[{{ $i }}][class_section]" value="{{ $g->class_section }}">
                                            <input type="hidden" name="groups[{{ $i }}][day_of_week]" value="{{ $g->day_of_week }}">
                                            <input type="hidden" name="groups[{{ $i }}][academic_year]" value="{{ $g->academic_year }}">
                                            <input type="hidden" name="groups[{{ $i }}][semester]" value="{{ $g->semester }}">
                                        </td>
                                        <td><label for="grp{{ $i }}">{{ $g->class_section ?? 'All Classes' }}</label></td>
                                        <td>{{ $g->day_of_week }}</td>
                                        <td>{{ $g->period_count }} period{{ $g->period_count == 1 ? '' : 's' }}</td>
                                        <td>{{ $g->academic_year ?? '—' }}</td>
                                        <td>{{ $g->semester ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <button type="submit" class="btn btn-danger btn-lg" id="previewBtn" disabled>
                    <i class="bi bi-eye"></i> Review Selected Schedules
                </button>
            </form>
        @endif
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleAll(state) {
            document.querySelectorAll('.group-check').forEach(cb => cb.checked = state);
            updateButton();
        }
        function updateButton() {
            const anyChecked = document.querySelectorAll('.group-check:checked').length > 0;
            const btn = document.getElementById('previewBtn');
            if (btn) btn.disabled = !anyChecked;
        }
        document.querySelectorAll('.group-check').forEach(cb => cb.addEventListener('change', updateButton));
    </script>
</body>
</html>

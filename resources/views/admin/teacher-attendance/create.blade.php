@extends('layouts.admin')

@section('title', 'Mark Teacher Attendance')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">Mark Teacher Attendance</h4>
                    <p class="text-muted mb-0">Mark attendance for teachers on {{ \Carbon\Carbon::parse($date)->format('F j, Y') }}</p>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.teacher-attendance.store') }}" method="POST">
                        @csrf
                        <input type="hidden" name="date" value="{{ $date }}">
                        
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Teacher ID</th>
                                        <th>Name</th>
                                        <th>Subject</th>
                                        <th>Status</th>
                                        <th>Remarks</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($teachers as $teacher)
                                    <tr>
                                        <td>{{ $teacher->id }}</td>
                                        <td>{{ $teacher->name ?? 'N/A' }}</td>
                                        <td>{{ $teacher->subject_specialization ?? 'N/A' }}</td>
                                        <td>
                                            <input type="hidden" name="teacher_ids[]" value="{{ $teacher->id }}">
                                            <select name="statuses[]" class="form-control" required>
                                                <option value="">Select Status</option>
                                                @foreach($statuses as $key => $label)
                                                    <option value="{{ $key }}" 
                                                        {{ $teacher->teacherAttendances->first()?->status === $key ? 'selected' : ($key === 'present' && !$teacher->teacherAttendances->first() ? 'selected' : '') }}> 
                                                        {{ $label }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td>
                                            <input type="text" name="remarks[]" class="form-control" 
                                                   value="{{ $teacher->teacherAttendances->first()?->remarks ?? '' }}"
                                                   placeholder="Optional remarks">
                                        </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="5" class="text-center">No teachers found</td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="d-flex justify-content-between mt-3">
                            <a href="{{ route('admin.teacher-attendance.index') }}" class="btn btn-secondary">
                                <i class="bi bi-arrow-left"></i> Back
                            </a>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-success" onclick="markAllPresent()">
                                    <i class="bi bi-check-all"></i> Mark All Present
                                </button>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check-circle"></i> Save Attendance
                                </button>
                            </div>
                        </div>
                        
                        <script>
                        function markAllPresent() {
                            // Confirm with user first
                            if (confirm('Are you sure you want to mark ALL teachers as present for {{ \Carbon\Carbon::parse($date)->format("F j, Y") }}?')) {
                                // Set all status dropdowns to 'present'
                                document.querySelectorAll('select[name="statuses[]"]').forEach(function(select) {
                                    select.value = 'present';
                                });
                                
                                // Add default remark to all remark inputs
                                document.querySelectorAll('input[name="remarks[]"]').forEach(function(input) {
                                    if (input.value.trim() === '') {
                                        input.value = 'Auto-marked as present';
                                    }
                                });
                            }
                        }
                        </script>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
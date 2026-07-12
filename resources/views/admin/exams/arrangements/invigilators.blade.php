@extends('layouts.admin')

@section('title', 'Exam Invigilator Duties')

@section('content')
<div class="container-fluid">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800">🕵️ Invigilator Duties</h1>
            <p class="text-muted mb-0">Assign invigilator duties to staff members for <strong>{{ $exam->name }}</strong> (Date: {{ date('d M Y', strtotime($exam->exam_date)) }} | Time: {{ $exam->start_time }} - {{ $exam->end_time }}).</p>
        </div>
        <a href="{{ route('admin.exams.arrangements.index') }}" class="btn btn-secondary shadow-sm">
            <i class="bi bi-arrow-left"></i> Back to Arrangements
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="row">
        <!-- Duties Assignment Panel -->
        <div class="col-12 mb-4">
            <div class="card shadow">
                <div class="card-header py-3 bg-light">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="bi bi-person-badge-fill"></i> Invigilator Duty Assignments
                    </h6>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.exams.arrangements.invigilators.save', $exam->id) }}" method="POST">
                        @csrf
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Room Number / Lab Name</th>
                                        <th>Assigned Teacher (Invigilator)</th>
                                        <th>Responsibility Role</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($rooms as $index => $room)
                                    <tr>
                                        <td class="fw-bold">
                                            {{ $room }}
                                            <input type="hidden" name="duties[{{ $index }}][room_number]" value="{{ $room }}">
                                        </td>
                                        <td>
                                            <select name="duties[{{ $index }}][teacher_id]" class="form-select" required>
                                                <option value="">-- Select Teacher --</option>
                                                @foreach($teachers as $teacher)
                                                    <option value="{{ $teacher->id }}" {{ old('duties.'.$index.'.teacher_id', $duties[$room]->teacher_id ?? '') == $teacher->id ? 'selected' : '' }}>
                                                        {{ $teacher->name }} ({{ $teacher->designation ?? 'Teacher' }})
                                                    </option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td>
                                            <select name="duties[{{ $index }}][role]" class="form-select" required>
                                                <option value="Main Invigilator" {{ old('duties.'.$index.'.role', $duties[$room]->role ?? '') == 'Main Invigilator' ? 'selected' : '' }}>Main Invigilator</option>
                                                <option value="Assistant Invigilator" {{ old('duties.'.$index.'.role', $duties[$room]->role ?? '') == 'Assistant Invigilator' ? 'selected' : '' }}>Assistant Invigilator</option>
                                            </select>
                                        </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="3" class="text-center py-4 text-muted">
                                            <i class="bi bi-info-circle" style="font-size: 1.5rem;"></i>
                                            <div class="mt-2">No rooms have been scheduled in seating arrangements. Generate seating arrangements first to retrieve room lists automatically.</div>
                                        </td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        @if(count($rooms) > 0)
                        <div class="text-end mt-4">
                            <button type="submit" class="btn btn-success px-4 shadow">
                                <i class="bi bi-save"></i> Save Invigilator Assignments
                            </button>
                        </div>
                        @endif
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

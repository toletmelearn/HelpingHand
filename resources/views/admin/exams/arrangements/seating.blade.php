@extends('layouts.admin')

@section('title', 'Exam Seating Arrangement')

@section('content')
<div class="container-fluid">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800">🪑 Seating Arrangement</h1>
            <p class="text-muted mb-0">Configure exam rooms and seats for <strong>{{ $exam->name }}</strong> (Class: {{ $exam->class_name }} | Subject: {{ $exam->subject }}).</p>
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

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="row">
        <!-- Auto Generator Panel -->
        <div class="col-lg-4 mb-4">
            <div class="card shadow">
                <div class="card-header py-3 bg-light">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="bi bi-magic"></i> Auto-Generate Seating
                    </h6>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.exams.arrangements.seating.generate', $exam->id) }}" method="POST">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">Room Number / Lab Name</label>
                            <input type="text" name="room_number" class="form-control" placeholder="e.g. Room 101, Main Auditorium" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Seat Number Prefix</label>
                            <input type="text" name="seat_prefix" class="form-control" placeholder="e.g. S- , Row-A-" value="S-">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Starting Seat Number</label>
                            <input type="number" name="start_number" class="form-control" min="1" value="1" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 shadow-sm">
                            <i class="bi bi-gear-fill"></i> Generate Seating Arrangements
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Seating Details Form -->
        <div class="col-lg-8 mb-4">
            <div class="card shadow">
                <div class="card-header py-3 bg-light">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="bi bi-list-columns"></i> Student Seating Registry
                    </h6>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.exams.arrangements.seating.save', $exam->id) }}" method="POST">
                        @csrf
                        <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
                            <table class="table table-striped table-bordered align-middle">
                                <thead class="table-light sticky-top">
                                    <tr>
                                        <th>Roll No</th>
                                        <th>Photo</th>
                                        <th>Student Name</th>
                                        <th>Room Number / Lab</th>
                                        <th>Seat Number</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($students as $index => $student)
                                    <tr>
                                        <td>{{ $student->roll_number ?? ($index + 1) }}</td>
                                        <td>
                                            <img src="{{ $student->photo_url }}" alt="{{ $student->name }}"
                                                 class="rounded-circle" width="28" height="28" style="object-fit: cover;">
                                        </td>
                                        <td class="fw-bold">
                                            {{ $student->name }}
                                            <input type="hidden" name="seating[{{ $index }}][student_id]" value="{{ $student->id }}">
                                        </td>
                                        <td>
                                            <input type="text" 
                                                   name="seating[{{ $index }}][room_number]" 
                                                   class="form-control form-control-sm" 
                                                   value="{{ old('seating.'.$index.'.room_number', $seating[$student->id]->room_number ?? '') }}" 
                                                   placeholder="e.g. Room 101" 
                                                   required>
                                        </td>
                                        <td>
                                            <input type="text" 
                                                   name="seating[{{ $index }}][seat_number]" 
                                                   class="form-control form-control-sm" 
                                                   value="{{ old('seating.'.$index.'.seat_number', $seating[$student->id]->seat_number ?? '') }}" 
                                                   placeholder="e.g. S-10" 
                                                   required>
                                        </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="5" class="text-center py-4 text-muted">No students registered in this class.</td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        @if(count($students) > 0)
                        <div class="text-end mt-4">
                            <button type="submit" class="btn btn-success px-4 shadow">
                                <i class="bi bi-save"></i> Save Seating Arrangements
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

@extends('layouts.teacher')

@section('content')
<div class="container-fluid py-4">
    <div class="row">
        <div class="col-md-12 mb-4">
            <h1 class="h3 mb-2 text-gray-800">Slow Learners & Remedial logs</h1>
            <p class="mb-4">Monitor slow learners, record diagnostic evaluations, and update remedial progress notes.</p>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="row">
        <!-- Add Entry Form -->
        <div class="col-md-4 mb-4">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Log Diagnostic / Remedial Notes</h6>
                </div>
                <div class="card-body">
                    <form action="{{ route('teacher.remedial.store') }}" method="POST">
                        @csrf
                        <div class="form-group mb-3">
                            <label>Select Student</label>
                            <select name="student_id" class="form-control" required>
                                @foreach($students as $student)
                                    <option value="{{ $student->id }}">{{ $student->name }} (Class: {{ $student->class }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group mb-3">
                            <label>Select Subject</label>
                            <select name="subject_id" class="form-control" required>
                                @foreach($subjects as $subj)
                                    <option value="{{ $subj->id }}">{{ $subj->name }} ({{ $subj->code }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group mb-3">
                            <label>Diagnostic Date</label>
                            <input type="date" name="diagnostic_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                        </div>
                        <div class="form-group mb-3">
                            <label>Remedial Plan & Notes</label>
                            <textarea name="remedial_notes" class="form-control" rows="4" placeholder="Describe the student's learning weaknesses and your remedial teaching plan..." required></textarea>
                        </div>
                        <div class="form-group mb-3">
                            <label>Progress Status</label>
                            <select name="progress_status" class="form-control" required>
                                <option value="stagnant">Stagnant / Needs Focus</option>
                                <option value="improving">Improving</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Log Remedial Plan</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- History Lists -->
        <div class="col-md-8 mb-4">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Active Remedial Teaching Logs</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Subject</th>
                                    <th>Diagnostic Date</th>
                                    <th>Remedial Plan</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($records as $rec)
                                    <tr>
                                        <td><strong>{{ $rec->student->name }}</strong></td>
                                        <td>{{ $rec->subject->name }}</td>
                                        <td>{{ $rec->diagnostic_date }}</td>
                                        <td>{{ $rec->remedial_notes }}</td>
                                        <td>
                                            @if($rec->progress_status === 'improving')
                                                <span class="badge bg-success">Improving</span>
                                            @else
                                                <span class="badge bg-warning text-dark">Stagnant</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center">No slow learner logs recorded yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

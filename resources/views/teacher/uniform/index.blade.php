@extends('layouts.teacher')

@section('content')
<div class="container-fluid py-4">
    <div class="row">
        <div class="col-md-12 mb-4">
            <h1 class="h3 mb-2 text-gray-800">Class Dress Code & Uniform Check</h1>
            <p class="mb-4">Log daily student uniform compliance and add remarks for dress-code violations.</p>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="row">
        <!-- Check Form -->
        <div class="col-md-4 mb-4">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Log Uniform Check</h6>
                </div>
                <div class="card-body">
                    <form action="{{ route('teacher.uniform.store') }}" method="POST">
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
                            <label>Check Date</label>
                            <input type="date" name="check_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                        </div>
                        <div class="form-group mb-3">
                            <label>Status</label>
                            <select name="is_compliant" class="form-control" required>
                                <option value="1">Compliant</option>
                                <option value="0">Non-Compliant (Violation)</option>
                            </select>
                        </div>
                        <div class="form-group mb-3">
                            <label>Remarks / Deficiency Details</label>
                            <input type="text" name="remarks" class="form-control" placeholder="e.g. Missing tie, unpolished shoes">
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Log Check</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- History Lists -->
        <div class="col-md-8 mb-4">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Recent Dress Code Checks</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Check Date</th>
                                    <th>Status</th>
                                    <th>Remarks</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($checks as $check)
                                    <tr>
                                        <td><strong>{{ $check->student->name }}</strong></td>
                                        <td>{{ $check->check_date }}</td>
                                        <td>
                                            @if($check->is_compliant)
                                                <span class="badge bg-success">Compliant</span>
                                            @else
                                                <span class="badge bg-danger">Non-Compliant</span>
                                            @endif
                                        </td>
                                        <td>{{ $check->remarks ?? 'N/A' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center">No uniform checks registered yet.</td>
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

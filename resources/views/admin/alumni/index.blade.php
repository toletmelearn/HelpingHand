@extends('layouts.admin')

@section('content')
<div class="container-fluid py-4">
    <div class="row">
        <div class="col-md-12 mb-4">
            <h1 class="h3 mb-2 text-gray-800">Alumni Registry & Student Archiving</h1>
            <p class="mb-4">Graduate current students to Alumni profiles to archive records and track graduation stats.</p>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="row">
        <!-- Archive Student Form -->
        <div class="col-md-4 mb-4">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Graduate Student to Alumni</h6>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.alumni.store') }}" method="POST">
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
                            <label>Graduation Year</label>
                            <input type="number" name="graduation_year" class="form-control" value="{{ date('Y') }}" required>
                        </div>
                        <div class="form-group mb-3">
                            <label>Current Occupation</label>
                            <input type="text" name="current_occupation" class="form-control" placeholder="e.g. Software Engineer, Doctor">
                        </div>
                        <div class="form-group mb-3">
                            <label>Contact Email</label>
                            <input type="email" name="contact_email" class="form-control" placeholder="e.g. alumni@example.com">
                        </div>
                        <div class="form-group mb-3">
                            <label>Graduation Feedback</label>
                            <textarea name="feedback" class="form-control" rows="3" placeholder="Alumni notes or feedback..."></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Graduate Student</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Alumni List -->
        <div class="col-md-8 mb-4">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Graduated Alumni List</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <th>Alumni Name</th>
                                    <th>Graduation Year</th>
                                    <th>Occupation</th>
                                    <th>Contact Email</th>
                                    <th>Notes/Feedback</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($alumniList as $alumni)
                                    <tr>
                                        <td><strong>{{ $alumni->student->name }}</strong></td>
                                        <td>{{ $alumni->graduation_year }}</td>
                                        <td>{{ $alumni->current_occupation ?? 'N/A' }}</td>
                                        <td>{{ $alumni->contact_email ?? 'N/A' }}</td>
                                        <td>{{ $alumni->feedback ?? 'N/A' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center">No alumni profiles registered yet.</td>
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

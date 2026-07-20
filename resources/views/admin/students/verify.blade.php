@extends('layouts.admin')

@section('title', 'Student Verification')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h3 class="card-title mb-0">Student Verification</h3>
                        <form method="GET">
                            <div class="input-group">
                                <input type="text" name="search" class="form-control" placeholder="Search Students" value="{{ request('search') }}">
                                <button type="submit" class="btn btn-outline-secondary">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                <!-- /.card-header -->
                <div class="card-body table-responsive p-0">
                    <table class="table table-hover text-nowrap">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Father's Name</th>
                                <th>Mother's Name</th>
                                <th>Date of Birth</th>
                                <th>Documents</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($students as $student)
                            <tr>
                                <td>{{ $student->id }}</td>
                                <td>{{ $student->name }}</td>
                                <td>{{ $student->father_name }}</td>
                                <td>{{ $student->mother_name }}</td>
                                <td>{{ $student->date_of_birth ? $student->date_of_birth->format('d/m/Y') : '-' }}</td>
                                <td>
                                    @if($student->documents->count() > 0)
                                        <span class="badge bg-info">{{ $student->documents->count() }} docs</span>
                                    @else
                                        <span class="badge bg-warning text-dark">No docs</span>
                                    @endif
                                </td>
                                <td>
                                    @if($student->is_verified)
                                        <span class="badge bg-success">Verified</span>
                                    @else
                                        <span class="badge bg-danger">Pending</span>
                                    @endif
                                </td>
                                <td>
                                    <a href="{{ route('admin.students.verify.show', $student) }}" class="btn btn-sm btn-primary">Verify</a>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="8" class="text-center">No students found</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <!-- /.card-body -->
                <div class="card-footer clearfix">
                    {{ $students->links() }}
                </div>
            </div>
            <!-- /.card -->
        </div>
    </div>
</div>
@endsection
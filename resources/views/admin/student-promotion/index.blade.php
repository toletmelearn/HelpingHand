@extends('layouts.admin')

@section('title', 'Student Promotions')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">Student Promotions</h4>
                    <a href="{{ route('admin.student-promotions.create') }}" class="btn btn-primary float-right">
                        <i class="fas fa-forward"></i> Promote Students
                    </a>
                </div>
                <div class="card-body">
                    @if(session('success'))
                        <div class="alert alert-success">
                            {{ session('success') }}
                        </div>
                    @endif

                    <div class="table-responsive">
                        <table class="table table-striped table-bordered">
                            <thead>
                                <tr>
                                    <th>Class</th>
                                    <th>Student Count</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($studentsByClass as $student)
                                    <tr>
                                        <td>{{ $student->class }}</td>
                                        <td>
                                            @php
                                                $count = App\Models\Student::where('class', $student->class)->count();
                                            @endphp
                                            {{ $count }}
                                        </td>
                                        <td>
                                            <a href="{{ route('admin.student-promotions.create') }}?from_class={{ urlencode($student->class) }}" 
                                               class="btn btn-sm btn-primary">
                                                Promote Class
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-center">No students found</td>
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

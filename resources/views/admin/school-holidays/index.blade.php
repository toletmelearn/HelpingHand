@extends('layouts.admin')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-12">
            <h1 class="h3 mb-4">School Holidays</h1>

            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Holiday Calendar</h6>
                    <a href="{{ route('admin.school-holidays.create') }}" class="btn btn-primary btn-sm">
                        <i class="fas fa-plus"></i> Add Holiday
                    </a>
                </div>
                <div class="card-body">
                    @if(session('success'))
                        <div class="alert alert-success">{{ session('success') }}</div>
                    @endif
                    @if(session('error'))
                        <div class="alert alert-danger">{{ session('error') }}</div>
                    @endif

                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Academic Year</th>
                                    <th>Name</th>
                                    <th>Start Date</th>
                                    <th>End Date</th>
                                    <th>Type</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($holidays as $holiday)
                                    <tr>
                                        <td>{{ $holiday->academic_year }}</td>
                                        <td>{{ $holiday->holiday_name }}</td>
                                        <td>{{ $holiday->start_date->format('d M Y') }}</td>
                                        <td>{{ $holiday->end_date->format('d M Y') }}</td>
                                        <td><span class="badge bg-info text-dark">{{ ucfirst($holiday->holiday_type) }}</span></td>
                                        <td>
                                            <a href="{{ route('admin.school-holidays.edit', $holiday->id) }}" class="btn btn-sm btn-warning">Edit</a>
                                            <form action="{{ route('admin.school-holidays.destroy', $holiday->id) }}" method="POST" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Delete this holiday?')">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center">No holidays configured</td>
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

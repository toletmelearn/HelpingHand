@extends('layouts.admin')

@section('title', 'Academic Calendar')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h1>Academic Calendar</h1>
        <a href="{{ route('admin.academic-events.create') }}" class="btn btn-primary">Add Calendar Entry</a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Title</th>
                            <th>Type</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Session</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($events as $event)
                        <tr>
                            <td>{{ $event->id }}</td>
                            <td>{{ $event->title }}</td>
                            <td><span class="badge bg-secondary">{{ ucfirst($event->type) }}</span></td>
                            <td>{{ $event->start_date->format('d-m-Y') }}</td>
                            <td>{{ $event->end_date->format('d-m-Y') }}</td>
                            <td>{{ $event->academicSession->name ?? '—' }}</td>
                            <td>
                                @if($event->is_active)
                                    <span class="badge bg-success">Active</span>
                                @else
                                    <span class="badge bg-danger">Inactive</span>
                                @endif
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm" role="group">
                                    <a href="{{ route('admin.academic-events.show', $event) }}" class="btn btn-info">View</a>
                                    <a href="{{ route('admin.academic-events.edit', $event) }}" class="btn btn-warning">Edit</a>
                                    <form action="{{ route('admin.academic-events.destroy', $event) }}" method="POST" class="d-inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure?')">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="text-center">No calendar entries found</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $events->links() }}
        </div>
    </div>
</div>
@endsection

@extends('layouts.app')

@section('title', 'Daily Teaching Work Management')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4>Daily Teaching Work</h4>
                    <a href="{{ route('admin.daily-teaching-work.create') }}" class="btn btn-primary">Add New Entry</a>
                </div>
                <div class="card-body">
                    <!-- Filters -->
                    <form method="GET" class="mb-4">
                        <div class="row g-3">
                            <div class="col-md-2">
                                <input type="date" name="date" class="form-control" value="{{ request('date') }}" placeholder="Date">
                            </div>
                            <div class="col-md-2">
                                <select name="class_name" class="form-control">
                                    <option value="">All Classes</option>
                                    @foreach($classes as $class)
                                        <option value="{{ $class }}" {{ request('class_name') == $class ? 'selected' : '' }}>
                                            {{ $class }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select name="section" class="form-control">
                                    <option value="">All Sections</option>
                                    @foreach($sections as $section)
                                        <option value="{{ $section }}" {{ request('section') == $section ? 'selected' : '' }}>
                                            {{ $section }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select name="subject" class="form-control">
                                    <option value="">All Subjects</option>
                                    @foreach($subjects as $subject)
                                        <option value="{{ $subject }}" {{ request('subject') == $subject ? 'selected' : '' }}>
                                            {{ $subject }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select name="teacher_id" class="form-control">
                                    <option value="">All Teachers</option>
                                    @foreach($teachers as $teacher)
                                        <option value="{{ $teacher->id }}" {{ request('teacher_id') == $teacher->id ? 'selected' : '' }}>
                                            {{ $teacher->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary w-100">Filter</button>
                                <a href="{{ route('admin.daily-teaching-work.index') }}" class="btn btn-secondary w-100 mt-1">Clear</a>
                            </div>
                        </div>
                    </form>

                    <!-- Table -->
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Class</th>
                                    <th>Section</th>
                                    <th>Subject</th>
                                    <th>Teacher</th>
                                    <th>Topic Covered</th>
                                    <th>Attachments</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($dailyWorks as $work)
                                <tr>
                                    <td>{{ $work->date->format('d-m-Y') }}</td>
                                    <td>{{ $work->class_name }}</td>
                                    <td>{{ $work->section }}</td>
                                    <td>{{ $work->subject }}</td>
                                    <td>{{ $work->teacher->name ?? 'N/A' }}</td>
                                    <td>{{ Str::limit($work->topic_covered, 50) }}</td>
                                    <td>
                                        @if($work->hasAttachments())
                                            <span class="badge bg-primary">{{ $work->getAttachmentCount() }} files</span>
                                        @else
                                            <span class="badge bg-secondary">None</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge bg-{{ $work->status == 'published' ? 'success' : ($work->status == 'draft' ? 'warning' : 'secondary') }}">
                                            {{ ucfirst($work->status) }}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="{{ route('admin.daily-teaching-work.show', $work) }}" class="btn btn-sm btn-info">View</a>
                                            <a href="{{ route('admin.daily-teaching-work.edit', $work) }}" class="btn btn-sm btn-primary">Edit</a>
                                            
                                            <form action="{{ route('admin.daily-teaching-work.destroy', $work) }}" method="POST" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-danger" 
                                                        onclick="return confirm('Are you sure you want to delete this daily teaching work?')">
                                                    Delete
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="9" class="text-center">No daily teaching work found.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                        
                        {{ $dailyWorks->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
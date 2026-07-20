@extends('layouts.admin')

@section('title', 'Homework Management')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4><i class="fas fa-tasks"></i> Homework Management</h4>
                </div>
                
                <!-- Filter Section -->
                <form method="GET" action="{{ route('admin.homework.index') }}">
                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <label for="assigned_by" class="form-label">Select Teacher</label>
                            <select name="assigned_by" id="assigned_by" class="form-control">
                                <option value="">All Teachers</option>
                                @foreach($teachers as $teacher)
                                    <option value="{{ $teacher->id }}" {{ request('assigned_by') == $teacher->id ? 'selected' : '' }}>
                                        {{ $teacher->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        
                        <div class="col-md-3">
                            <label for="class_id" class="form-label">Select Class</label>
                            <select name="class_id" id="class_id" class="form-control">
                                <option value="">All Classes</option>
                                @foreach($classes as $class)
                                    <option value="{{ $class->id }}" {{ request('class_id') == $class->id ? 'selected' : '' }}>
                                        {{ $class->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        
                        <div class="col-md-3">
                            <label for="date" class="form-label">Select Date</label>
                            <input type="date" name="date" id="date" class="form-control" value="{{ request('date') }}">
                        </div>
                        
                        <div class="col-md-3">
                            <label>&nbsp;</label>
                            <div class="d-block">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search"></i> Filter
                                </button>
                                <a href="{{ route('admin.homework.index') }}" class="btn btn-secondary ms-1">
                                    <i class="fas fa-sync"></i> Reset
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Summary Section -->
                    @if($summary)
                    <div class="alert alert-info">
                        <h5>Summary</h5>
                        <p>
                            <strong>Total Homework:</strong> {{ $summary['total_homework'] }} |
                            <strong>Teacher:</strong> {{ $summary['teacher_name'] }} |
                            <strong>Class:</strong> {{ $summary['class_name'] }} |
                            <strong>Date:</strong> {{ $summary['date'] }}
                        </p>
                    </div>
                    @else
                    <div class="alert alert-info text-center">
                        <h5><i class="fas fa-info-circle"></i> Showing all homework ({{ $homeworks->count() }} records)</h5>
                    </div>
                    @endif
                    
                    <!-- Homework Table -->
                    @if($homeworks->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered">
                            <thead class="table-dark">
                                <tr>
                                    <th>Teacher</th>
                                    <th>Class</th>
                                    <th>Subject</th>
                                    <th>Title</th>
                                    <th>Description</th>
                                    <th>Due Date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($homeworks as $hw)
                                <tr>
                                    <td>{{ $hw->teacherLogin->name ?? 'N/A' }}</td>
                                    <td>{{ $hw->schoolClass->name ?? 'N/A' }}</td>
                                    <td>{{ $hw->subject->name ?? 'N/A' }}</td>
                                    <td>{{ Str::limit($hw->title, 20) }}</td>
                                    <td>{{ Str::limit(strip_tags($hw->description), 50) }}</td>
                                    <td>{{ $hw->due_date ?? 'N/A' }}</td>
                                    <td>
                                        <span class="badge bg-{{ $hw->status === 'active' ? 'success' : ($hw->status === 'inactive' ? 'secondary' : 'info') }}">
                                            {{ ucfirst($hw->status) }}
                                        </span>
                                    </td>
                                    <td>
                                        <a href="{{ route('admin.homework.show', $hw) }}" class="btn btn-sm btn-info">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @else
                    <div class="alert alert-info text-center">
                        <h5>No homework found for the selected filters.</h5>
                    </div>
                    @endif
                </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
@extends('layouts.admin')

@section('title', 'Lesson Plans Management')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4><i class="fas fa-book-open"></i> Lesson Plans Management</h4>
                </div>
                
                <!-- Filter Section -->
                <form method="GET" action="{{ route('admin.lesson-plans.index') }}">
                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <label for="teacher_id" class="form-label">Select Teacher</label>
                            <select name="teacher_id" id="teacher_id" class="form-control">
                                <option value="">All Teachers</option>
                                @foreach($teachers as $teacher)
                                    <option value="{{ $teacher->id }}" {{ request('teacher_id') == $teacher->id ? 'selected' : '' }}>
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
                                <a href="{{ route('admin.lesson-plans.index') }}" class="btn btn-secondary ms-1">
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
                            <strong>Total Plans:</strong> {{ $summary['total_plans'] }} |
                            <strong>Teacher:</strong> {{ $summary['teacher_name'] }} |
                            <strong>Class:</strong> {{ $summary['class_name'] }} |
                            <strong>Date:</strong> {{ $summary['date'] }}
                        </p>
                    </div>
                    @else
                    <div class="alert alert-info text-center">
                        <h5><i class="fas fa-info-circle"></i> Showing all lesson plans ({{ $lessonPlans->count() }} records)</h5>
                    </div>
                    @endif
                    
                    <!-- Lesson Plans Table -->
                    @if($lessonPlans->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered">
                            <thead class="table-dark">
                                <tr>
                                    <th>Teacher</th>
                                    <th>Class</th>
                                    <th>Subject</th>
                                    <th>Title</th>
                                    <th>Date</th>
                                    <th>Type</th>
                                    <th>Visibility</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($lessonPlans as $plan)
                                <tr>
                                    <td>{{ $plan->teacher->name ?? 'N/A' }}</td>
                                    <td>{{ $plan->class->name ?? 'N/A' }}</td>
                                    <td>{{ $plan->subject->name ?? 'N/A' }}</td>
                                    <td>{{ Str::limit($plan->title, 30) }}</td>
                                    <td>{{ $plan->date }}</td>
                                    <td>
                                        <span class="badge bg-{{ $plan->plan_type === 'daily' ? 'primary' : ($plan->plan_type === 'weekly' ? 'info' : 'secondary') }}">
                                            {{ ucfirst($plan->plan_type) }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-{{ $plan->show_to_parents ? 'success' : 'warning' }}">
                                            {{ $plan->show_to_parents ? 'Visible' : 'Hidden' }}
                                        </span>
                                    </td>
                                    <td>
                                        <a href="{{ route('admin.lesson-plans.show', $plan) }}" class="btn btn-sm btn-info">
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
                        <h5>No lesson plans found for the selected filters.</h5>
                    </div>
                    @endif
                </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
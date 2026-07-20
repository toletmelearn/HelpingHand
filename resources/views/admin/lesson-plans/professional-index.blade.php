@extends('layouts.admin')

@section('title', 'Professional Lesson Plans Management')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">
                        <i class="fas fa-book-open"></i> All Lesson Plans
                    </h4>
                </div>
                
                <div class="card-body">
                    <!-- Filter Section -->
                    <div class="card mb-4">
                        <div class="card-header bg-light">
                            <h5 class="mb-0"><i class="fas fa-filter"></i> Filter Lesson Plans</h5>
                        </div>
                        <div class="card-body">
                            <form action="{{ route('admin.professional-lesson-plans.index') }}" method="GET">
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label for="class_id">Class</label>
                                            <select name="class_id" id="class_id" class="form-control">
                                                <option value="">All Classes</option>
                                                @foreach($classes as $class)
                                                    <option value="{{ $class->id }}" {{ request('class_id') == $class->id ? 'selected' : '' }}>
                                                        {{ $class->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label for="subject_id">Subject</label>
                                            <select name="subject_id" id="subject_id" class="form-control">
                                                <option value="">All Subjects</option>
                                                @foreach($subjects as $subject)
                                                    <option value="{{ $subject->id }}" {{ request('subject_id') == $subject->id ? 'selected' : '' }}>
                                                        {{ $subject->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label for="teacher_id">Teacher</label>
                                            <select name="teacher_id" id="teacher_id" class="form-control">
                                                <option value="">All Teachers</option>
                                                @foreach($teachers as $teacher)
                                                    <option value="{{ $teacher->id }}" {{ request('teacher_id') == $teacher->id ? 'selected' : '' }}>
                                                        {{ $teacher->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label for="plan_type">Plan Type</label>
                                            <select name="plan_type" id="plan_type" class="form-control">
                                                <option value="">All Types</option>
                                                <option value="daily" {{ request('plan_type') == 'daily' ? 'selected' : '' }}>Daily</option>
                                                <option value="weekly" {{ request('plan_type') == 'weekly' ? 'selected' : '' }}>Weekly</option>
                                                <option value="monthly" {{ request('plan_type') == 'monthly' ? 'selected' : '' }}>Monthly</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label for="date_from">Date From</label>
                                            <input type="date" name="date_from" id="date_from" class="form-control" 
                                                   value="{{ request('date_from') }}">
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label for="date_to">Date To</label>
                                            <input type="date" name="date_to" id="date_to" class="form-control" 
                                                   value="{{ request('date_to') }}">
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label for="parent_visible">Parent Visibility</label>
                                            <select name="parent_visible" id="parent_visible" class="form-control">
                                                <option value="">All</option>
                                                <option value="1" {{ request('parent_visible') == '1' ? 'selected' : '' }}>Visible to Parents</option>
                                                <option value="0" {{ request('parent_visible') == '0' ? 'selected' : '' }}>Not Visible to Parents</option>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-3">
                                        <div class="form-group" style="margin-top: 32px;">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-search"></i> Filter
                                            </button>
                                            <a href="{{ route('admin.professional-lesson-plans.index') }}" class="btn btn-secondary">
                                                <i class="fas fa-times"></i> Clear
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Results Section -->
                    @if($lessonPlans->isEmpty())
                        <div class="text-center py-5">
                            <i class="fas fa-book-open display-4 text-muted"></i>
                            <h4 class="mt-3 text-muted">No Lesson Plans Found</h4>
                            <p class="text-muted">No lesson plans match your current filter criteria.</p>
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="thead-light">
                                    <tr>
                                        <th>Date</th>
                                        <th>Title</th>
                                        <th>Class</th>
                                        <th>Subject</th>
                                        <th>Teacher</th>
                                        <th>Type</th>
                                        <th>Parent Visible</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($lessonPlans as $plan)
                                    <tr>
                                        <td>{{ $plan->date->format('d M Y') }}</td>
                                        <td>
                                            <strong>{{ $plan->title }}</strong>
                                            @if($plan->topic)
                                                <br><small class="text-muted">{{ Str::limit($plan->topic, 50) }}</small>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="badge badge-info">{{ $plan->class->name ?? 'N/A' }}</span>
                                        </td>
                                        <td>{{ $plan->subject->name ?? 'N/A' }}</td>
                                        <td>{{ $plan->teacher->name ?? 'N/A' }}</td>
                                        <td>
                                            <span class="badge badge-{{ $plan->plan_type == 'daily' ? 'success' : ($plan->plan_type == 'weekly' ? 'warning' : 'primary') }}">
                                                {{ ucfirst($plan->plan_type) }}
                                            </span>
                                        </td>
                                        <td>
                                            @if($plan->show_to_parents)
                                                <span class="badge badge-success">
                                                    <i class="fas fa-check"></i> Yes
                                                </span>
                                            @else
                                                <span class="badge badge-secondary">
                                                    <i class="fas fa-times"></i> No
                                                </span>
                                            @endif
                                        </td>
                                        <td>{{ $plan->created_at->format('d M Y') }}</td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <a href="{{ route('admin.professional-lesson-plans.show', $plan) }}" 
                                                   class="btn btn-sm btn-outline-primary" title="View">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <form action="{{ route('admin.professional-lesson-plans.destroy', $plan) }}" 
                                                      method="POST" class="d-inline" 
                                                      onsubmit="return confirm('Are you sure you want to delete this lesson plan? This action cannot be undone.')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="mt-4">
                            {{ $lessonPlans->appends(request()->query())->links() }}
                        </div>
                        
                        <div class="mt-3">
                            <p class="text-muted">
                                Showing {{ $lessonPlans->firstItem() }} to {{ $lessonPlans->lastItem() }} of {{ $lessonPlans->total() }} lesson plans
                            </p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
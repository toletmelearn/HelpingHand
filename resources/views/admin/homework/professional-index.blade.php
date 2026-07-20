@extends('layouts.admin')

@section('title', 'Professional Homework Management')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">
                        <i class="fas fa-tasks"></i> All Homework Assignments
                    </h4>
                </div>
                
                <div class="card-body">
                    <!-- Filter Section -->
                    <div class="card mb-4">
                        <div class="card-header bg-light">
                            <h5 class="mb-0"><i class="fas fa-filter"></i> Filter Homework</h5>
                        </div>
                        <div class="card-body">
                            <form action="{{ route('admin.professional-homework.index') }}" method="GET">
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
                                                        {{ $teacher->username }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label for="type">Type</label>
                                            <select name="type" id="type" class="form-control">
                                                <option value="">All Types</option>
                                                <option value="homework" {{ request('type') == 'homework' ? 'selected' : '' }}>Homework</option>
                                                <option value="notice" {{ request('type') == 'notice' ? 'selected' : '' }}>Notice</option>
                                                <option value="announcement" {{ request('type') == 'announcement' ? 'selected' : '' }}>Announcement</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label for="priority">Priority</label>
                                            <select name="priority" id="priority" class="form-control">
                                                <option value="">All Priorities</option>
                                                <option value="low" {{ request('priority') == 'low' ? 'selected' : '' }}>Low</option>
                                                <option value="medium" {{ request('priority') == 'medium' ? 'selected' : '' }}>Medium</option>
                                                <option value="high" {{ request('priority') == 'high' ? 'selected' : '' }}>High</option>
                                            </select>
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
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="form-group">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-search"></i> Filter
                                            </button>
                                            <a href="{{ route('admin.professional-homework.index') }}" class="btn btn-secondary">
                                                <i class="fas fa-times"></i> Clear
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Results Section -->
                    @if($homeworks->isEmpty())
                        <div class="text-center py-5">
                            <i class="fas fa-tasks display-4 text-muted"></i>
                            <h4 class="mt-3 text-muted">No Homework Found</h4>
                            <p class="text-muted">No homework assignments match your current filter criteria.</p>
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="thead-light">
                                    <tr>
                                        <th>Title</th>
                                        <th>Class</th>
                                        <th>Subject</th>
                                        <th>Teacher</th>
                                        <th>Type</th>
                                        <th>Due Date</th>
                                        <th>Priority</th>
                                        <th>Parent Visible</th>
                                        <th>Published</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($homeworks as $homework)
                                    <tr>
                                        <td>
                                            <strong>{{ $homework->title }}</strong>
                                            <br><small class="text-muted">{{ Str::limit($homework->description, 50) }}</small>
                                        </td>
                                        <td>{{ $homework->schoolClass->name ?? 'N/A' }}</td>
                                        <td>{{ $homework->subject->name ?? 'N/A' }}</td>
                                        <td>{{ $homework->teacherLogin->username ?? 'N/A' }}</td>
                                        <td>
                                            <span class="badge badge-{{ $homework->type == 'homework' ? 'primary' : ($homework->type == 'notice' ? 'warning' : 'info') }}">
                                                {{ ucfirst($homework->type) }}
                                            </span>
                                        </td>
                                        <td>{{ $homework->due_date ? $homework->due_date->format('d M Y') : 'N/A' }}</td>
                                        <td>
                                            <span class="badge badge-{{ $homework->priority == 'high' ? 'danger' : ($homework->priority == 'medium' ? 'warning' : 'secondary') }}">
                                                {{ ucfirst($homework->priority) }}
                                            </span>
                                        </td>
                                        <td>
                                            @if($homework->visible_to_parent)
                                                <span class="badge badge-success">
                                                    <i class="fas fa-check"></i> Yes
                                                </span>
                                            @else
                                                <span class="badge badge-secondary">
                                                    <i class="fas fa-times"></i> No
                                                </span>
                                            @endif
                                        </td>
                                        <td>{{ $homework->publish_date->format('d M Y') }}</td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <a href="{{ route('admin.professional-homework.show', $homework) }}" 
                                                   class="btn btn-sm btn-outline-primary" title="View">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <form action="{{ route('admin.professional-homework.destroy', $homework) }}" 
                                                      method="POST" class="d-inline" 
                                                      onsubmit="return confirm('Are you sure you want to delete this homework? This action cannot be undone.')">
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
                            {{ $homeworks->appends(request()->query())->links() }}
                        </div>
                        
                        <div class="mt-3">
                            <p class="text-muted">
                                Showing {{ $homeworks->firstItem() }} to {{ $homeworks->lastItem() }} of {{ $homeworks->total() }} homework assignments
                            </p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
@extends('layouts.teacher')

@section('title', 'Professional Lesson Plans')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">
                        <i class="fas fa-book-open"></i> My Lesson Plans
                    </h4>
                    <a href="{{ route('teacher.professional-lesson-plans.create') }}" class="btn btn-light">
                        <i class="fas fa-plus"></i> Create New Lesson Plan
                    </a>
                </div>
                
                <div class="card-body">
                    @if($lessonPlans->isEmpty())
                        <div class="text-center py-5">
                            <i class="fas fa-book-open display-4 text-muted"></i>
                            <h4 class="mt-3 text-muted">No Lesson Plans Found</h4>
                            <p class="text-muted">You haven't created any lesson plans yet.</p>
                            <a href="{{ route('teacher.professional-lesson-plans.create') }}" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Create Your First Lesson Plan
                            </a>
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
                                                <a href="{{ route('teacher.professional-lesson-plans.show', $plan) }}" 
                                                   class="btn btn-sm btn-outline-primary" title="View">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="{{ route('teacher.professional-lesson-plans.edit', $plan) }}" 
                                                   class="btn btn-sm btn-outline-warning" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <form action="{{ route('teacher.professional-lesson-plans.destroy', $plan) }}" 
                                                      method="POST" class="d-inline" 
                                                      onsubmit="return confirm('Are you sure you want to delete this lesson plan?')">
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
                            {{ $lessonPlans->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
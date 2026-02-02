@extends('layouts.admin')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-12">
            <h1>Lesson Plan History</h1>
            <p class="mb-4">View your lesson plan history</p>
            
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Your Lesson Plan History</h5>
                        <a href="{{ route('teacher.lesson-plans.index') }}" class="btn btn-secondary">
                            <i class="bi bi-arrow-left"></i> Back to Current
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    @if($lessonPlans->isEmpty())
                        <div class="text-center py-5">
                            <i class="bi bi-journal-bookmark display-4 text-muted"></i>
                            <p class="mt-3 text-muted">You haven't created any lesson plans yet. Create your first lesson plan to get started.</p>
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Class</th>
                                        <th>Subject</th>
                                        <th>Date</th>
                                        <th>Topic</th>
                                        <th>Type</th>
                                        <th>Books Required</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($lessonPlans as $plan)
                                        <tr>
                                            <td>{{ $plan->class->name }} - {{ $plan->section->name }}</td>
                                            <td>{{ $plan->subject->name }}</td>
                                            <td>{{ $plan->date->format('M d, Y') }}</td>
                                            <td>{{ $plan->topic }}</td>
                                            <td>
                                                <span class="badge bg-{{ $plan->plan_type === 'daily' ? 'primary' : ($plan->plan_type === 'weekly' ? 'success' : 'warning') }}">
                                                    {{ ucfirst($plan->plan_type) }}
                                                </span>
                                            </td>
                                            <td>{{ Str::limit($plan->books_notebooks_required, 30) }}</td>
                                            <td>
                                                <div class="btn-group btn-group-sm" role="group">
                                                    <a href="{{ route('teacher.lesson-plans.show', $plan) }}" 
                                                       class="btn btn-outline-info" 
                                                       title="View Details">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                    <a href="{{ route('teacher.lesson-plans.edit', $plan) }}" 
                                                       class="btn btn-outline-primary" 
                                                       title="Edit">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                    <a href="{{ route('teacher.lesson-plans.destroy', $plan) }}" 
                                                       class="btn btn-outline-danger" 
                                                       title="Delete"
                                                       onclick="event.preventDefault(); if(confirm('Are you sure you want to delete this lesson plan?')) document.getElementById('delete-form-{{ $plan->id }}').submit();">
                                                        <i class="bi bi-trash"></i>
                                                    </a>
                                                    <form id="delete-form-{{ $plan->id }}" 
                                                          action="{{ route('teacher.lesson-plans.destroy', $plan) }}" 
                                                          method="POST" 
                                                          style="display: none;">
                                                        @csrf
                                                        @method('DELETE')
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="mt-3">
                            {{ $lessonPlans->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

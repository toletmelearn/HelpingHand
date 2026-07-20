@extends('layouts.parent')

@section('title', 'My Child\'s Lesson Plans')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">My Child's Lesson Plans</h4>
                </div>
                <div class="card-body">
                    @forelse($lessonPlans as $plan)
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Subject</th>
                                        <th>Title</th>
                                        <th>Type</th>
                                        <th>Duration</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>{{ $plan->date }}</td>
                                        <td>{{ $plan->subject->name ?? 'N/A' }}</td>
                                        <td>{{ Str::limit($plan->title, 30) }}</td>
                                        <td>
                                            <span class="badge bg-{{ $plan->plan_type === 'daily' ? 'primary' : ($plan->plan_type === 'weekly' ? 'info' : 'secondary') }}">
                                                {{ ucfirst($plan->plan_type) }}
                                            </span>
                                        </td>
                                        <td>{{ $plan->duration }}</td>
                                        <td>
                                            <a href="{{ route('parent.lesson-plans.show', $plan) }}" class="btn btn-sm btn-info">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    @empty
                        <div class="text-center">
                            <i class="fas fa-book-open fa-3x text-muted mb-3"></i>
                            <h5>No lesson plans available</h5>
                            <p class="text-muted">Your child's teacher hasn't shared any lesson plans yet.</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
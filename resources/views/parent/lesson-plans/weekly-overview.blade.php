@extends('layouts.admin')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-12">
            <h1>Weekly Lesson Overview</h1>
            <p class="mb-4">Subject-wise breakdown of this week's lesson plans</p>
            
            @if($weeklyPlans->isEmpty())
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="bi bi-journal-text display-4 text-muted"></i>
                        <p class="mt-3 text-muted">No lesson plans found for this week.</p>
                    </div>
                </div>
            @else
                @foreach($weeklyPlans as $subject => $plans)
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">{{ $subject }}</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Topic</th>
                                            <th>Books Required</th>
                                            <th>Homework/Classwork</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($plans as $plan)
                                            <tr>
                                                <td>{{ $plan->date->format('M d, Y') }}</td>
                                                <td>{{ $plan->topic }}</td>
                                                <td>{{ Str::limit($plan->books_notebooks_required, 50) }}</td>
                                                <td>{{ Str::limit($plan->homework_classwork, 50) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                @endforeach
            @endif
            
            <div class="mt-4">
                <a href="{{ route('parent.lesson-plans.index') }}" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Back to Lesson Plans
                </a>
            </div>
        </div>
    </div>
</div>
@endsection

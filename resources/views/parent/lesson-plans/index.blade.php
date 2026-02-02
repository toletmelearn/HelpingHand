@extends('layouts.admin')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-12">
            <h1>Lesson Plan</h1>
            <p class="mb-4">View your children's lesson plans</p>
            
            <div class="row">
                <div class="col-md-6 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Today's Lesson Plan</h5>
                        </div>
                        <div class="card-body">
                            @if($lessonPlans->isEmpty())
                                <p class="text-muted">No lesson plans for today.</p>
                            @else
                                @foreach($lessonPlans as $plan)
                                    @if($plan->date->isToday())
                                        <div class="mb-3 p-3 border rounded">
                                            <h6>{{ $plan->subject->name }}</h6>
                                            <p class="mb-1"><strong>Topic:</strong> {{ $plan->topic }}</p>
                                            <p class="mb-1"><strong>Class:</strong> {{ $plan->class->name }} - {{ $plan->section->name }}</p>
                                            <p class="mb-0"><strong>Books Required:</strong> {{ $plan->books_notebooks_required }}</p>
                                        </div>
                                    @endif
                                @endforeach
                            @endif
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Tomorrow's Required Books</h5>
                        </div>
                        <div class="card-body">
                            @if($lessonPlans->isEmpty())
                                <p class="text-muted">No lesson plans for tomorrow.</p>
                            @else
                                @foreach($lessonPlans as $plan)
                                    @if($plan->date->isTomorrow())
                                        <div class="mb-3 p-3 border rounded">
                                            <h6>{{ $plan->subject->name }}</h6>
                                            <p class="mb-1"><strong>Topic:</strong> {{ $plan->topic }}</p>
                                            <p class="mb-0"><strong>Books Required:</strong> {{ $plan->books_notebooks_required }}</p>
                                        </div>
                                    @endif
                                @endforeach
                            @endif
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Weekly Lesson Overview</h5>
                        <a href="{{ route('parent.lesson-plans.weekly-overview') }}" class="btn btn-outline-primary btn-sm">
                            View Weekly Overview
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    @if($lessonPlans->isEmpty())
                        <p class="text-muted">No lesson plans found for this week.</p>
                    @else
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Subject</th>
                                        <th>Topic</th>
                                        <th>Books Required</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($lessonPlans->take(10) as $plan)
                                        <tr>
                                            <td>{{ $plan->date->format('M d, Y') }}</td>
                                            <td>{{ $plan->subject->name }}</td>
                                            <td>{{ $plan->topic }}</td>
                                            <td>{{ Str::limit($plan->books_notebooks_required, 50) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

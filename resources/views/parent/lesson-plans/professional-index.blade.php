@extends('layouts.parent')

@section('title', 'Lesson Plans')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">
                        <i class="fas fa-book-open"></i> Lesson Plans for Your Child
                    </h4>
                    <p class="mb-0">View lesson plans for your child's class</p>
                </div>
                
                <div class="card-body">
                    @if(count($plans) > 0)
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="thead-light">
                                    <tr>
                                        <th>Date</th>
                                        <th>Title</th>
                                        <th>Subject</th>
                                        <th>Teacher</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($plans as $plan)
                                    <tr>
                                        <td>{{ $plan->date->format('d M Y') }}</td>
                                        <td>
                                            <strong>{{ $plan->title }}</strong>
                                            @if($plan->topic)
                                                <br><small class="text-muted">{{ Str::limit($plan->topic, 50) }}</small>
                                            @endif
                                        </td>
                                        <td>{{ $plan->subject->name ?? 'N/A' }}</td>
                                        <td>{{ $plan->teacher->name ?? 'N/A' }}</td>
                                        <td>
                                            <a href="{{ route('parent.professional-lesson-plans.show', $plan) }}" 
                                               class="btn btn-sm btn-primary">
                                                <i class="fas fa-eye"></i> View Details
                                            </a>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="mt-4">
                            {{ $plans->links() }}
                        </div>
                    @else
                        <div class="text-center py-5">
                            <i class="fas fa-book-open display-4 text-muted"></i>
                            <h4 class="mt-3 text-muted">No Lesson Plans Available</h4>
                            <p class="text-muted">There are currently no lesson plans available for your child's class.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
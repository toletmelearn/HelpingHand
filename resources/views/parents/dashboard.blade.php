@extends('layouts.admin')

@section('title', 'Parent Dashboard')

@section('content')
<div class="container">
    <div class="row mb-4">
        <div class="col-12">
            <h2>Parent Dashboard</h2>
            <p>Welcome, {{ auth()->user()->name }}! Here's an overview of your children's information.</p>
        </div>
    </div>

    <div class="row">
        @forelse($children as $child)
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="card-title">{{ $child->name }}</h5>
                        <p class="card-text">
                            <strong>Class:</strong> {{ $child->class }}<br>
                            <strong>Roll Number:</strong> {{ $child->roll_number }}<br>
                            <strong>Attendance:</strong> 
                            <span class="badge bg-{{ $dashboardData[$child->id]['attendance_percentage'] >= 75 ? 'success' : 'warning' }}">
                                {{ $dashboardData[$child->id]['attendance_percentage'] }}%
                            </span>
                        </p>
                        
                        @if($dashboardData[$child->id]['latest_result'])
                            <p class="card-text">
                                <strong>Latest Result:</strong> {{ $dashboardData[$child->id]['latest_result']->marks_obtained }}/{{ $dashboardData[$child->id]['latest_result']->total_marks }}
                            </p>
                        @endif
                        
                        @if($dashboardData[$child->id]['pending_fees'])
                            <p class="card-text text-danger">
                                <strong>Pending Fees:</strong> â‚¹{{ $dashboardData[$child->id]['pending_fees'] }}
                            </p>
                        @endif
                    </div>
                    <div class="card-footer">
                        <a href="{{ route('parents.child.details', $child->id) }}" class="btn btn-primary btn-sm">View Details</a>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12">
                <div class="alert alert-info">
                    <h4>No Children Found</h4>
                    <p>You don't have any children registered in the system yet.</p>
                </div>
            </div>
        @endforelse
    </div>
</div>
@endsection

@extends('layouts.public')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-12">
            <h1>Dashboard</h1>
            <p>Welcome to the HelpingHand School Management System, {{ Auth::user()->name }}!</p>
        </div>
    </div>
    
    @if(isset($dashboardData))
    <div class="row mt-4">
        <div class="col-md-2">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h5 class="card-title">Students</h5>
                    <p class="card-text">{{ $dashboardData['students'] }}</p>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h5 class="card-title">Teachers</h5>
                    <p class="card-text">{{ $dashboardData['teachers'] }}</p>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h5 class="card-title">Attendance</h5>
                    <p class="card-text">{{ $dashboardData['attendance'] }}</p>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <h5 class="card-title">Bell Timing</h5>
                    <p class="card-text">{{ $dashboardData['bell_timing'] }}</p>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card bg-danger text-white">
                <div class="card-body">
                    <h5 class="card-title">Exam Papers</h5>
                    <p class="card-text">{{ $dashboardData['exam_papers'] }}</p>
                </div>
            </div>
        </div>
    </div>
    @endif
    
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5>Quick Actions</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <a href="{{ route('students.index') }}" class="btn btn-primary w-100">Manage Students</a>
                        </div>
                        <div class="col-md-3">
                            <a href="{{ route('teachers.index') }}" class="btn btn-secondary w-100">Manage Teachers</a>
                        </div>
                        <div class="col-md-3">
                            <a href="{{ route('attendance.index') }}" class="btn btn-success w-100">Attendance</a>
                        </div>
                        <div class="col-md-3">
                            <a href="{{ route('bell-timing.index') }}" class="btn btn-info w-100">Bell Timing</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

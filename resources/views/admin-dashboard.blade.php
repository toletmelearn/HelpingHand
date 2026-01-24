@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-12">
            <h1>Admin Dashboard</h1>
            <p>Welcome to the Admin Dashboard, {{ Auth::user()->name }}!</p>
        </div>
    </div>
    
    <!-- System Overview Stats -->
    <div class="row mt-4">
        <div class="col-md-2">
            <div class="card bg-primary text-white">
                <div class="card-body text-center">
                    <h5 class="card-title">Students</h5>
                    <p class="card-text display-4">{{ \App\Models\Student::count() }}</p>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card bg-success text-white">
                <div class="card-body text-center">
                    <h5 class="card-title">Teachers</h5>
                    <p class="card-text display-4">{{ \App\Models\Teacher::count() }}</p>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card bg-info text-white">
                <div class="card-body text-center">
                    <h5 class="card-title">Attendance</h5>
                    <p class="card-text display-4">{{ \App\Models\Attendance::count() }}</p>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card bg-warning text-dark">
                <div class="card-body text-center">
                    <h5 class="card-title">Bell Timing</h5>
                    <p class="card-text display-4">{{ \App\Models\BellTiming::count() }}</p>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card bg-danger text-white">
                <div class="card-body text-center">
                    <h5 class="card-title">Exam Papers</h5>
                    <p class="card-text display-4">{{ \App\Models\ExamPaper::count() }}</p>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card bg-secondary text-white">
                <div class="card-body text-center">
                    <h5 class="card-title">Users</h5>
                    <p class="card-text display-4">{{ \App\Models\User::count() }}</p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5>Quick Actions</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <a href="{{ route('students.index') }}" class="btn btn-primary w-100">Manage Students</a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="{{ route('teachers.index') }}" class="btn btn-secondary w-100">Manage Teachers</a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="{{ route('attendance.index') }}" class="btn btn-success w-100">Attendance</a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="{{ route('bell-timing.index') }}" class="btn btn-info w-100">Bell Timing</a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="{{ route('exam-papers.index') }}" class="btn btn-warning w-100">Exam Papers</a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="{{ route('users.index') }}" class="btn btn-dark w-100">Manage Users</a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="{{ route('admin.exams.index') }}" class="btn btn-primary w-100">Manage Exams</a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="{{ route('admin.results.index') }}" class="btn btn-success w-100">Manage Results</a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="{{ route('admin.fees.index') }}" class="btn btn-warning w-100">Manage Fees</a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="{{ route('admin.fee-structures.index') }}" class="btn btn-info w-100">Fee Structures</a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="{{ route('profile.two-factor-authentication') }}" class="btn btn-outline-secondary w-100">Settings</a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="{{ route('admin.role-permissions.index') }}" class="btn btn-dark w-100">Manage Permissions</a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="{{ route('admin.admit-card-formats.index') }}" class="btn btn-info w-100">Admit Card Formats</a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="{{ route('admin.admit-cards.index') }}" class="btn btn-warning w-100">Manage Admit Cards</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Recent Activity -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5>Recent Activity</h5>
                </div>
                <div class="card-body">
                    <p>No recent activity to display.</p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
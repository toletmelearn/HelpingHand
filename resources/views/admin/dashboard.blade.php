@extends('layouts.admin')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <h1 class="mb-4">
                <i class="bi bi-speedometer2"></i> Admin Dashboard
            </h1>
            
            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-3 mb-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <h5 class="card-title">Total Students</h5>
                            <h2>{{ $stats['total_students'] ?? 0 }}</h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <h5 class="card-title">Total Teachers</h5>
                            <h2>{{ $stats['total_teachers'] ?? 0 }}</h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card bg-info text-white">
                        <div class="card-body">
                            <h5 class="card-title">Today's Attendance</h5>
                            <h2>{{ $stats['today_attendance'] ?? 0 }}</h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card bg-warning text-white">
                        <div class="card-body">
                            <h5 class="card-title">Pending Fees</h5>
                            <h2>{{ $stats['pending_fees'] ?? 0 }}</h2>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Quick Access Links -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Quick Access</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3 mb-3">
                                    <a href="{{ route('admin.students.index') }}" class="btn btn-outline-primary w-100">
                                        <i class="bi bi-people"></i><br>
                                        Student Management
                                    </a>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <a href="{{ route('admin.teachers.index') }}" class="btn btn-outline-success w-100">
                                        <i class="bi bi-person-badge"></i><br>
                                        Teacher Management
                                    </a>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <a href="{{ route('admin.attendance.index') }}" class="btn btn-outline-info w-100">
                                        <i class="bi bi-calendar-check"></i><br>
                                        Attendance
                                    </a>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <a href="{{ route('admin.fees.index') }}" class="btn btn-outline-warning w-100">
                                        <i class="bi bi-currency-dollar"></i><br>
                                        Fee Management
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
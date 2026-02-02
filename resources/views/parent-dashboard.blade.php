@extends('layouts.admin')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-12">
            <h1>Parent Dashboard</h1>
            <p>Welcome to the Parent Dashboard, {{ Auth::user()->name }}!</p>
        </div>
    </div>
    
    <!-- Child Information Overview -->
    <div class="row mt-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body text-center">
                    <h5 class="card-title">My Children</h5>
                    <p class="card-text display-4">{{ Auth::user()->children->count() ?? 0 }}</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body text-center">
                    <h5 class="card-title">Attendance</h5>
                    <p class="card-text display-4">Avg %</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body text-center">
                    <h5 class="card-title">Results</h5>
                    <p class="card-text display-4">Latest</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-dark">
                <div class="card-body text-center">
                    <h5 class="card-title">Fees Due</h5>
                    <p class="card-text display-4">Amount</p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- My Children List -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5>My Children</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Class</th>
                                    <th>Roll Number</th>
                                    <th>Attendance</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Child Name</td>
                                    <td>Class 10-A</td>
                                    <td>10</td>
                                    <td>95%</td>
                                    <td>
                                        <a href="#" class="btn btn-sm btn-info">View Details</a>
                                    </td>
                                </tr>
                                <tr>
                                    <td>Child Name</td>
                                    <td>Class 8-B</td>
                                    <td>15</td>
                                    <td>88%</td>
                                    <td>
                                        <a href="#" class="btn btn-sm btn-info">View Details</a>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
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
                            <a href="{{ route('students.index') }}" class="btn btn-primary w-100">View Children</a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="{{ route('attendance.index') }}" class="btn btn-success w-100">Check Attendance</a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="{{ route('results.index') ?? '#' }}" class="btn btn-info w-100">View Results</a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="{{ route('fees.payment') ?? '#' }}" class="btn btn-warning w-100">Pay Fees</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Announcements -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5>School Announcements</h5>
                </div>
                <div class="card-body">
                    <ul class="list-group">
                        <li class="list-group-item">
                            <strong>Holiday Notice:</strong> School closed on January 26th for Republic Day
                        </li>
                        <li class="list-group-item">
                            <strong>Exam Schedule:</strong> Unit tests for Class 10 will begin next Monday
                        </li>
                        <li class="list-group-item">
                            <strong>Parent Meeting:</strong> Quarterly parent-teacher meeting scheduled for February 5th
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

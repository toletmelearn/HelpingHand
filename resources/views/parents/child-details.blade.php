@extends('layouts.app')

@section('title', 'Child Details - ' . $child->name)

@section('content')
<div class="container">
    <div class="row mb-4">
        <div class="col-12">
            <h2>{{ $child->name }}'s Details</h2>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('parents.dashboard') }}">Parent Dashboard</a></li>
                    <li class="breadcrumb-item active">{{ $child->name }}</li>
                </ol>
            </nav>
        </div>
    </div>

    <div class="row">
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5>Basic Information</h5>
                </div>
                <div class="card-body">
                    <p><strong>Name:</strong> {{ $child->name }}</p>
                    <p><strong>Father's Name:</strong> {{ $child->father_name ?? 'N/A' }}</p>
                    <p><strong>Class:</strong> {{ $child->class }}</p>
                    <p><strong>Roll Number:</strong> {{ $child->roll_number }}</p>
                    <p><strong>Phone:</strong> {{ $child->phone ?? 'N/A' }}</p>
                    <p><strong>Email:</strong> {{ $child->email ?? 'N/A' }}</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-8">
            <!-- Attendance Section -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5>Attendance</h5>
                    <span class="badge bg-primary">Overall: {{ $attendanceStats['percentage'] ?? 0 }}%</span>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="text-center">
                                <h3>{{ $attendanceStats['present_days'] ?? 0 }}</h3>
                                <p class="text-success">Present Days</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-center">
                                <h3>{{ $attendanceStats['absent_days'] ?? 0 }}</h3>
                                <p class="text-danger">Absent Days</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-center">
                                <h3>{{ $attendanceStats['total_days'] ?? 0 }}</h3>
                                <p>Total Days</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-3">
                        <h6>Recent Attendance</h6>
                        @if(count($recentAttendances) > 0)
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Status</th>
                                            <th>Subject</th>
                                            <th>Time</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($recentAttendances as $attendance)
                                        <tr>
                                            <td>{{ $attendance->date->format('d M Y') }}</td>
                                            <td>
                                                <span class="badge bg-{{ $attendance->status === 'present' ? 'success' : 'danger' }}">
                                                    {{ ucfirst($attendance->status) }}
                                                </span>
                                            </td>
                                            <td>{{ $attendance->subject ?? 'General' }}</td>
                                            <td>{{ $attendance->created_at->format('h:i A') }}</td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <p class="text-muted">No recent attendance records found.</p>
                        @endif
                    </div>
                </div>
            </div>
            
            <!-- Results Section -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5>Results</h5>
                </div>
                <div class="card-body">
                    @if(count($results) > 0)
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Exam</th>
                                        <th>Subject</th>
                                        <th>Marks</th>
                                        <th>Total</th>
                                        <th>Percentage</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($results as $result)
                                    <tr>
                                        <td>{{ $result->exam->name ?? 'N/A' }}</td>
                                        <td>{{ $result->subject }}</td>
                                        <td>{{ $result->marks_obtained }}</td>
                                        <td>{{ $result->total_marks }}</td>
                                        <td>{{ number_format(($result->marks_obtained / $result->total_marks) * 100, 2) }}%</td>
                                        <td>{{ $result->created_at->format('d M Y') }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="text-muted">No results found for this child.</p>
                    @endif
                </div>
            </div>
            
            <!-- Fees Section -->
            <div class="card">
                <div class="card-header">
                    <h5>Fees</h5>
                </div>
                <div class="card-body">
                    @if(count($fees) > 0)
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Fee Type</th>
                                        <th>Amount</th>
                                        <th>Paid</th>
                                        <th>Due</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($fees as $fee)
                                    <tr>
                                        <td>{{ $fee->fee_type }}</td>
                                        <td>₹{{ $fee->amount }}</td>
                                        <td>₹{{ $fee->paid_amount }}</td>
                                        <td>₹{{ $fee->due_amount }}</td>
                                        <td>
                                            <span class="badge bg-{{ $fee->status === 'paid' ? 'success' : ($fee->status === 'partial' ? 'warning' : 'danger') }}">
                                                {{ ucfirst($fee->status) }}
                                            </span>
                                        </td>
                                        <td>{{ $fee->created_at->format('d M Y') }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="text-muted">No fee records found for this child.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
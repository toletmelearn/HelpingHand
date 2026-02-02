@extends('layouts.admin')

@section('title', 'Academic Session Details')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4>Academic Session Details: {{ $academicSession->name }}</h4>
                    <div>
                        <a href="{{ route('academic-sessions.edit', $academicSession) }}" class="btn btn-warning btn-sm">Edit</a>
                        <a href="{{ route('academic-sessions.index') }}" class="btn btn-secondary btn-sm">Back to List</a>
                    </div>
                </div>

                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h5>Basic Information</h5>
                            <table class="table table-borderless">
                                <tr>
                                    <td><strong>ID:</strong></td>
                                    <td>{{ $academicSession->id }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Name:</strong></td>
                                    <td>{{ $academicSession->name }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Code:</strong></td>
                                    <td>{{ $academicSession->code }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Start Date:</strong></td>
                                    <td>{{ $academicSession->start_date->format('d-m-Y') }}</td>
                                </tr>
                                <tr>
                                    <td><strong>End Date:</strong></td>
                                    <td>{{ $academicSession->end_date->format('d-m-Y') }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Current Session:</strong></td>
                                    <td>
                                        @if($academicSession->is_current)
                                            <span class="badge badge-success">Yes</span>
                                        @else
                                            <span class="badge badge-secondary">No</span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>Status:</strong></td>
                                    <td>
                                        <span class="badge {{ $academicSession->is_active ? 'badge-success' : 'badge-danger' }}">
                                            {{ $academicSession->is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>Description:</strong></td>
                                    <td>{{ $academicSession->description ?: 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Created At:</strong></td>
                                    <td>{{ $academicSession->created_at->format('d-m-Y h:i A') }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Updated At:</strong></td>
                                    <td>{{ $academicSession->updated_at->format('d-m-Y h:i A') }}</td>
                                </tr>
                            </table>
                        </div>
                        
                        <div class="col-md-6">
                            <h5>Related Records</h5>
                            <div class="card">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-6">
                                            <h6>Students</h6>
                                            <p><strong>{{ $academicSession->students->count() }}</strong></p>
                                        </div>
                                        <div class="col-6">
                                            <h6>Teachers</h6>
                                            <p><strong>{{ $academicSession->teachers->count() }}</strong></p>
                                        </div>
                                        <div class="col-6">
                                            <h6>Exams</h6>
                                            <p><strong>{{ $academicSession->exams->count() }}</strong></p>
                                        </div>
                                        <div class="col-6">
                                            <h6>Fees</h6>
                                            <p><strong>{{ $academicSession->fees->count() }}</strong></p>
                                        </div>
                                        <div class="col-6">
                                            <h6>Results</h6>
                                            <p><strong>{{ $academicSession->results->count() }}</strong></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <h5 class="mt-4">Duration</h5>
                            <p>
                                From: <strong>{{ $academicSession->start_date->format('d-M-Y') }}</strong><br>
                                To: <strong>{{ $academicSession->end_date->format('d-M-Y') }}</strong><br>
                                Total Days: <strong>{{ $academicSession->start_date->diffInDays($academicSession->end_date) }}</strong>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

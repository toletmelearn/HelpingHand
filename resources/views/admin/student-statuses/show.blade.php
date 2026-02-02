@extends('layouts.admin')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-12">
            <h1 class="h3 mb-4">Student Status Record Details</h1>
            
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Student Status Record</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Student Information</h6>
                            <table class="table table-borderless">
                                <tr>
                                    <td><strong>Student Name:</strong></td>
                                    <td>{{ $studentStatus->student->name ?? 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Roll Number:</strong></td>
                                    <td>{{ $studentStatus->student->roll_number ?? 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Class:</strong></td>
                                    <td>{{ $studentStatus->student->currentClass->name ?? 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Section:</strong></td>
                                    <td>{{ $studentStatus->student->section->name ?? 'N/A' }}</td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h6>Status Information</h6>
                            <table class="table table-borderless">
                                <tr>
                                    <td><strong>Status:</strong></td>
                                    <td>
                                        <span class="badge bg-{{ 
                                            $studentStatus->status == 'passed_out' ? 'success' :
                                            $studentStatus->status == 'tc_issued' ? 'warning' :
                                            $studentStatus->status == 'left_school' ? 'danger' :
                                            $studentStatus->status == 'active' ? 'primary' : 'secondary'
                                        }}">
                                            {{ ucfirst(str_replace('_', ' ', $studentStatus->status)) }}
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>Status Date:</strong></td>
                                    <td>{{ $studentStatus->status_date->format('M d, Y') }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Reason:</strong></td>
                                    <td>{{ $studentStatus->reason ?: '-' }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Remarks:</strong></td>
                                    <td>{{ $studentStatus->remarks ?: '-' }}</td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <div class="row mt-3">
                        <div class="col-md-6">
                            <h6>Document Information</h6>
                            <table class="table table-borderless">
                                <tr>
                                    <td><strong>Document Number:</strong></td>
                                    <td>{{ $studentStatus->document_number ?: '-' }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Document Issue Date:</strong></td>
                                    <td>{{ $studentStatus->document_issue_date ? $studentStatus->document_issue_date->format('M d, Y') : '-' }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Issued By:</strong></td>
                                    <td>{{ $studentStatus->issued_by ?: '-' }}</td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h6>Record Information</h6>
                            <table class="table table-borderless">
                                <tr>
                                    <td><strong>Created:</strong></td>
                                    <td>{{ $studentStatus->created_at->format('M d, Y H:i') }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Updated:</strong></td>
                                    <td>{{ $studentStatus->updated_at->format('M d, Y H:i') }}</td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between mt-4">
                        <a href="{{ route('admin.student-statuses.index') }}" class="btn btn-secondary">Back to List</a>
                        <div>
                            <a href="{{ route('admin.student-statuses.edit', $studentStatus->id) }}" class="btn btn-warning">Edit</a>
                            <form action="{{ route('admin.student-statuses.destroy', $studentStatus->id) }}" method="POST" class="d-inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this student status record?')">Delete</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

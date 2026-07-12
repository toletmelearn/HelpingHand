@extends('layouts.admin')

@section('title', 'Student Details - ' . $student->name)

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="card-title">Student Details - {{ $student->name }}</h4>
                    <div>
                        <a href="{{ route('admin.students.index') }}" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Students
                        </a>
                        @can('update', $student)
                        <a href="{{ route('admin.students.edit', $student->id) }}" class="btn btn-warning">
                            <i class="fas fa-edit"></i> Edit
                        </a>
                        @endcan
                    </div>
                </div>
                
                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-md-3 text-center">
                            <img src="{{ $student->photo_url }}" alt="{{ $student->name }}"
                                 class="rounded-circle mb-2" width="120" height="120" style="object-fit: cover;">
                            @if(\App\Helpers\FieldPermissionHelper::canEditField('student', 'photo'))
                            <form action="{{ route('students.photo.update', $student->id) }}" method="POST"
                                  enctype="multipart/form-data" class="mt-2">
                                @csrf
                                <input type="file" name="photo" class="form-control form-control-sm mb-2"
                                       accept="image/jpeg,image/png,image/gif" required>
                                <button type="submit" class="btn btn-sm btn-primary">
                                    <i class="fas fa-camera"></i> Change Photo
                                </button>
                            </form>
                            @endif
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <th width="30%">Full Name:</th>
                                    <td>{{ $student->name }}</td>
                                </tr>
                                <tr>
                                    <th>Father Name:</th>
                                    <td>{{ $student->father_name }}</td>
                                </tr>
                                <tr>
                                    <th>Mother Name:</th>
                                    <td>{{ $student->mother_name }}</td>
                                </tr>
                                <tr>
                                    <th>Date of Birth:</th>
                                    <td>{{ $student->date_of_birth ? $student->date_of_birth->format('d M Y') : 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <th>Aadhar Number:</th>
                                    <td>{{ $student->aadhar_number }}</td>
                                </tr>
                                <tr>
                                    <th>Mobile Number:</th>
                                    <td>{{ $student->mobile }}</td>
                                </tr>
                                <tr>
                                    <th>Gender:</th>
                                    <td>{{ ucfirst($student->gender) }}</td>
                                </tr>
                            </table>
                        </div>
                        
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <th width="30%">Category:</th>
                                    <td>{{ $student->category }}</td>
                                </tr>
                                <tr>
                                    <th>Class:</th>
                                    <td>{{ $student->schoolClass->name ?? $student->class }}</td>
                                </tr>
                                <tr>
                                    <th>Section:</th>
                                    <td>{{ $student->section ?: 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <th>Roll Number:</th>
                                    <td>{{ $student->roll_number ?: 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <th>Admission Number:</th>
                                    <td>{{ $student->admission_no ?: 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <th>Religion:</th>
                                    <td>{{ $student->religion ?: 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <th>Caste:</th>
                                    <td>{{ $student->caste ?: 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <th>Blood Group:</th>
                                    <td>{{ $student->blood_group ?: 'N/A' }}</td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <div class="row mt-3">
                        <div class="col-12">
                            <h5>Address</h5>
                            <p>{{ $student->address }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
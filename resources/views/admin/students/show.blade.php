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
                                       accept="image/jpeg,image/png,image/gif,image/webp,image/bmp" required>
                                <small class="text-muted d-block mb-2">JPEG, PNG, GIF, WEBP or BMP, up to 8MB.</small>
                                <button type="submit" class="btn btn-sm btn-primary">
                                    <i class="fas fa-camera"></i> Change Photo
                                </button>
                            </form>
                            @endif
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="table-responsive">
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
                                    <th>Aadhaar Number:</th>
                                    <td>{{ $student->aadhaar_number }}</td>
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
                        </div>

                        <div class="col-md-6">
                            <div class="table-responsive">
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
                    </div>
                    
                    <div class="row mt-3">
                        <div class="col-12">
                            <h5>Address</h5>
                            <p>{{ $student->address }}</p>
                        </div>
                    </div>

                    <div class="row mt-4">
                        <div class="col-12">
                            <h5>Compliance (UDISE / APAAR)</h5>
                            @if($student->hasAadhaarNameMismatch())
                                <div class="alert alert-warning py-2">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    Name does not match name as per Aadhaar.
                                </div>
                            @endif
                            <div class="table-responsive">
                                <table class="table table-borderless">
                                    <tr>
                                        <th width="30%">UDISE PEN:</th>
                                        <td>{{ $student->udise_pen ?: 'N/A' }}</td>
                                    </tr>
                                    <tr>
                                        <th>APAAR ID:</th>
                                        <td>{{ $student->apaar_id ?: 'N/A' }}</td>
                                    </tr>
                                    <tr>
                                        <th>Name as per Aadhaar:</th>
                                        <td>{{ $student->name_as_per_aadhaar ?: 'N/A' }}</td>
                                    </tr>
                                    <tr>
                                        <th>APAAR Consent:</th>
                                        <td>
                                            @if($student->apaar_consent_given)
                                                <span class="badge bg-success">Given</span>
                                                on {{ $student->apaar_consent_date?->format('d M Y') }}
                                                by {{ $student->apaar_consent_by }}
                                            @else
                                                <span class="badge bg-secondary">Not given</span>
                                            @endif
                                        </td>
                                    </tr>
                                </table>
                            </div>
                            @can('update', $student)
                            <form action="{{ route('admin.students.apaar-consent', $student->id) }}" method="POST" class="row g-2 align-items-end">
                                @csrf
                                <div class="col-auto">
                                    <label class="form-label">Parent/Guardian Name</label>
                                    <input type="text" name="apaar_consent_by" class="form-control form-control-sm"
                                           value="{{ $student->apaar_consent_by }}" placeholder="Consent given by">
                                </div>
                                <div class="col-auto">
                                    @if($student->apaar_consent_given)
                                        <input type="hidden" name="apaar_consent_given" value="0">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Withdraw Consent</button>
                                    @else
                                        <input type="hidden" name="apaar_consent_given" value="1">
                                        <button type="submit" class="btn btn-sm btn-primary">Record Consent</button>
                                    @endif
                                </div>
                            </form>
                            @endcan
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
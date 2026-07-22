@extends('layouts.admin')

@section('title', 'Edit Student - ' . $student->name)

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="card-title">Edit Student - {{ $student->name }}</h4>
                    <a href="{{ route('admin.students.index') }}" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Students
                    </a>
                </div>
                
                <div class="card-body">
                    <form action="{{ route('admin.students.update', $student->id) }}" method="POST">
                        @csrf
                        @method('PUT')
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="name" class="form-label">Full Name *</label>
                                    <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror" 
                                           value="{{ old('name', $student->name) }}" required>
                                    @error('name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="father_name" class="form-label">Father Name *</label>
                                    <input type="text" name="father_name" id="father_name" class="form-control @error('father_name') is-invalid @enderror" 
                                           value="{{ old('father_name', $student->father_name) }}" required>
                                    @error('father_name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="mother_name" class="form-label">Mother Name *</label>
                                    <input type="text" name="mother_name" id="mother_name" class="form-control @error('mother_name') is-invalid @enderror" 
                                           value="{{ old('mother_name', $student->mother_name) }}" required>
                                    @error('mother_name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="date_of_birth" class="form-label">Date of Birth *</label>
                                    <input type="date" name="date_of_birth" id="date_of_birth" class="form-control @error('date_of_birth') is-invalid @enderror" 
                                           value="{{ old('date_of_birth', $student->date_of_birth ? \Carbon\Carbon::parse($student->date_of_birth)->format('Y-m-d') : '') }}" required>
                                    @error('date_of_birth')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="aadhaar_number" class="form-label">Aadhaar Number *</label>
                                    <input type="text" name="aadhaar_number" id="aadhaar_number" class="form-control @error('aadhaar_number') is-invalid @enderror" 
                                           value="{{ old('aadhaar_number', $student->aadhaar_number) }}" required>
                                    @error('aadhaar_number')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="mobile" class="form-label">Mobile *</label>
                                    <input type="text" name="mobile" id="mobile" class="form-control @error('mobile') is-invalid @enderror"
                                           value="{{ old('mobile', $student->mobile) }}" required>
                                    @error('mobile')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group mb-3">
                                    <label for="udise_pen" class="form-label">UDISE PEN</label>
                                    <input type="text" name="udise_pen" id="udise_pen" class="form-control @error('udise_pen') is-invalid @enderror"
                                           value="{{ old('udise_pen', $student->udise_pen) }}">
                                    @error('udise_pen')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="col-md-4">
                                <div class="form-group mb-3">
                                    <label for="apaar_id" class="form-label">APAAR ID</label>
                                    <input type="text" name="apaar_id" id="apaar_id" class="form-control @error('apaar_id') is-invalid @enderror"
                                           value="{{ old('apaar_id', $student->apaar_id) }}" maxlength="12">
                                    @error('apaar_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    <div class="form-text">Exactly 12 digits, if known.</div>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <div class="form-group mb-3">
                                    <label for="name_as_per_aadhaar" class="form-label">Name as per Aadhaar</label>
                                    <input type="text" name="name_as_per_aadhaar" id="name_as_per_aadhaar" class="form-control @error('name_as_per_aadhaar') is-invalid @enderror"
                                           value="{{ old('name_as_per_aadhaar', $student->name_as_per_aadhaar) }}">
                                    @error('name_as_per_aadhaar')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="gender" class="form-label">Gender *</label>
                                    <select name="gender" id="gender" class="form-select @error('gender') is-invalid @enderror" required>
                                        <option value="">Select Gender</option>
                                        <option value="male" {{ old('gender', $student->gender) == 'male' ? 'selected' : '' }}>Male</option>
                                        <option value="female" {{ old('gender', $student->gender) == 'female' ? 'selected' : '' }}>Female</option>
                                        <option value="other" {{ old('gender', $student->gender) == 'other' ? 'selected' : '' }}>Other</option>
                                    </select>
                                    @error('gender')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="category" class="form-label">Category *</label>
                                    <select name="category" id="category" class="form-select @error('category') is-invalid @enderror" required>
                                        <option value="">Select Category</option>
                                        <option value="General" {{ old('category', $student->category) == 'General' ? 'selected' : '' }}>General</option>
                                        <option value="OBC" {{ old('category', $student->category) == 'OBC' ? 'selected' : '' }}>OBC</option>
                                        <option value="SC" {{ old('category', $student->category) == 'SC' ? 'selected' : '' }}>SC</option>
                                        <option value="ST" {{ old('category', $student->category) == 'ST' ? 'selected' : '' }}>ST</option>
                                        <option value="Other" {{ old('category', $student->category) == 'Other' ? 'selected' : '' }}>Other</option>
                                    </select>
                                    @error('category')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="class_id" class="form-label">Class *</label>
                                    <select name="class_id" id="class_id" class="form-select @error('class_id') is-invalid @enderror" required>
                                        <option value="">Select Class</option>
                                        @foreach($classList as $class)
                                            <option value="{{ $class->id }}" {{ old('class_id', $student->canonicalClassId()) == $class->id ? 'selected' : '' }}>
                                                {{ $class->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('class_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="section_id" class="form-label">Section</label>
                                    <select name="section_id" id="section_id" class="form-select @error('section_id') is-invalid @enderror">
                                        <option value="">Select Section</option>
                                        @foreach($sections as $section)
                                            <option value="{{ $section->id }}" {{ old('section_id', $student->section_id) == $section->id ? 'selected' : '' }}>
                                                {{ $section->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('section_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="roll_number" class="form-label">Roll Number</label>
                                    <input type="number" name="roll_number" id="roll_number" class="form-control @error('roll_number') is-invalid @enderror" 
                                           value="{{ old('roll_number', $student->roll_number) }}">
                                    @error('roll_number')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="admission_no" class="form-label">Admission Number</label>
                                    <input type="text" name="admission_no" id="admission_no" class="form-control @error('admission_no') is-invalid @enderror" 
                                           value="{{ old('admission_no', $student->admission_no) }}">
                                    @error('admission_no')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="religion" class="form-label">Religion</label>
                                    <input type="text" name="religion" id="religion" class="form-control @error('religion') is-invalid @enderror" 
                                           value="{{ old('religion', $student->religion) }}">
                                    @error('religion')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="caste" class="form-label">Caste</label>
                                    <input type="text" name="caste" id="caste" class="form-control @error('caste') is-invalid @enderror" 
                                           value="{{ old('caste', $student->caste) }}">
                                    @error('caste')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="blood_group" class="form-label">Blood Group</label>
                                    <select name="blood_group" id="blood_group" class="form-select @error('blood_group') is-invalid @enderror">
                                        <option value="">Select Blood Group</option>
                                        <option value="A+" {{ old('blood_group', $student->blood_group) == 'A+' ? 'selected' : '' }}>A+</option>
                                        <option value="A-" {{ old('blood_group', $student->blood_group) == 'A-' ? 'selected' : '' }}>A-</option>
                                        <option value="B+" {{ old('blood_group', $student->blood_group) == 'B+' ? 'selected' : '' }}>B+</option>
                                        <option value="B-" {{ old('blood_group', $student->blood_group) == 'B-' ? 'selected' : '' }}>B-</option>
                                        <option value="AB+" {{ old('blood_group', $student->blood_group) == 'AB+' ? 'selected' : '' }}>AB+</option>
                                        <option value="AB-" {{ old('blood_group', $student->blood_group) == 'AB-' ? 'selected' : '' }}>AB-</option>
                                        <option value="O+" {{ old('blood_group', $student->blood_group) == 'O+' ? 'selected' : '' }}>O+</option>
                                        <option value="O-" {{ old('blood_group', $student->blood_group) == 'O-' ? 'selected' : '' }}>O-</option>
                                        <option value="unknown" {{ old('blood_group', $student->blood_group) == 'unknown' ? 'selected' : '' }}>Unknown</option>
                                    </select>
                                    @error('blood_group')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="address" class="form-label">Address *</label>
                                    <textarea name="address" id="address" class="form-control @error('address') is-invalid @enderror" 
                                              required>{{ old('address', $student->address) }}</textarea>
                                    @error('address')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group mt-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Update Student
                            </button>
                            <a href="{{ route('admin.students.index') }}" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

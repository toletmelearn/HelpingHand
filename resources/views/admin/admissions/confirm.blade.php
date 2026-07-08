@extends('layouts.admin')

@section('content')
<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">Confirm Student Admission: {{ $enquiry->candidate_name }}</h6>
                    <a href="{{ route('admin.admissions.index') }}" class="btn btn-sm btn-secondary"><i class="fas fa-arrow-left me-1"></i> Back to Pipeline</a>
                </div>
                <div class="card-body">
                    
                    @if ($errors->any())
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="alert alert-info border-0 shadow-sm mb-4">
                        <div class="row">
                            <div class="col-md-6">
                                <strong>Candidate Name:</strong> {{ $enquiry->candidate_name }}<br>
                                <strong>Parent/Guardian:</strong> {{ $enquiry->parent_name }}
                            </div>
                            <div class="col-md-6 text-md-end">
                                <strong>Phone:</strong> {{ $enquiry->phone }}<br>
                                <strong>Scores:</strong> Entrance: {{ $enquiry->entrance_score }}% | Interview: {{ $enquiry->interview_score }}%
                            </div>
                        </div>
                    </div>

                    <form action="{{ route('admin.admissions.confirm-admission', $enquiry->id) }}" method="POST">
                        @csrf
                        
                        <div class="row">
                            <!-- Class Selection -->
                            <div class="col-md-6 form-group mb-3">
                                <label class="fw-bold">Assign Class <span class="text-danger">*</span></label>
                                <select name="class_id" id="class_select" class="form-control" required>
                                    <option value="">-- Select Class --</option>
                                    @foreach($classes as $class)
                                        <option value="{{ $class->id }}" data-name="{{ $class->name }}">{{ $class->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <!-- Section Selection -->
                            <div class="col-md-6 form-group mb-3">
                                <label class="fw-bold">Assign Section <span class="text-danger">*</span></label>
                                <select name="section_id" id="section_select" class="form-control" required>
                                    <option value="">-- Select Class First --</option>
                                </select>
                                <div id="seat-info" class="form-text"></div>
                            </div>
                        </div>

                        <div class="row" id="capacity-override-row" style="display:none;">
                            <div class="col-12 form-group mb-3">
                                <div class="alert alert-warning border-0 shadow-sm mb-2" id="capacity-warning">
                                    This section is full.
                                </div>
                                @if($isAdmin)
                                    <div class="form-check">
                                        <input type="checkbox" name="override_capacity" value="1" class="form-check-input" id="override_capacity">
                                        <label class="form-check-label fw-bold" for="override_capacity">Override capacity and admit anyway (admin only)</label>
                                    </div>
                                @endif
                            </div>
                        </div>

                        <div class="row">
                            <!-- DOB -->
                            <div class="col-md-6 form-group mb-3">
                                <label class="fw-bold">Date of Birth <span class="text-danger">*</span></label>
                                <input type="date" name="date_of_birth" class="form-control" required value="{{ old('date_of_birth') }}">
                            </div>

                            <!-- Gender -->
                            <div class="col-md-6 form-group mb-3">
                                <label class="fw-bold">Gender <span class="text-danger">*</span></label>
                                <select name="gender" class="form-control" required>
                                    <option value="male" {{ old('gender') === 'male' ? 'selected' : '' }}>Male</option>
                                    <option value="female" {{ old('gender') === 'female' ? 'selected' : '' }}>Female</option>
                                    <option value="other" {{ old('gender') === 'other' ? 'selected' : '' }}>Other</option>
                                </select>
                            </div>
                        </div>

                        <div class="row">
                            <!-- Category -->
                            <div class="col-md-6 form-group mb-3">
                                <label class="fw-bold">Category <span class="text-danger">*</span></label>
                                <select name="category" class="form-control" required>
                                    <option value="General" {{ old('category') === 'General' ? 'selected' : '' }}>General</option>
                                    <option value="OBC" {{ old('category') === 'OBC' ? 'selected' : '' }}>OBC</option>
                                    <option value="SC" {{ old('category') === 'SC' ? 'selected' : '' }}>SC</option>
                                    <option value="ST" {{ old('category') === 'ST' ? 'selected' : '' }}>ST</option>
                                </select>
                            </div>

                            <!-- Roll Number -->
                            <div class="col-md-6 form-group mb-3">
                                <label class="fw-bold">Roll Number (Optional)</label>
                                <input type="number" name="roll_number" class="form-control" value="{{ old('roll_number') }}" placeholder="Leave blank to auto-assign later">
                            </div>
                        </div>

                        <!-- Mother's Name -->
                        <div class="form-group mb-3">
                            <label class="fw-bold">Mother's Name (Optional)</label>
                            <input type="text" name="mother_name" class="form-control" value="{{ old('mother_name') }}" placeholder="Leave blank if not provided">
                        </div>

                        <!-- Aadhar Number -->
                        <div class="form-group mb-3">
                            <label class="fw-bold">Aadhar Number (Optional)</label>
                            <input type="text" name="aadhar_number" class="form-control" value="{{ old('aadhar_number') }}" placeholder="12-digit UIDAI number">
                        </div>

                        <!-- Address -->
                        <div class="form-group mb-4">
                            <label class="fw-bold">Residential Address (Optional)</label>
                            <textarea name="address" class="form-control" rows="3" placeholder="Enter student's physical residence address">{{ old('address') }}</textarea>
                        </div>

                        <div class="d-flex justify-content-between align-items-center">
                            <a href="{{ route('admin.admissions.index') }}" class="btn btn-outline-secondary">
                                <i class="fas fa-times me-1"></i> Cancel
                            </a>
                            <button type="submit" class="btn btn-success px-4">
                                Complete Admission & Create Student Profile <i class="fas fa-check-circle ms-1"></i>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const classSelect = document.getElementById('class_select');
        const sectionSelect = document.getElementById('section_select');
        const seatInfo = document.getElementById('seat-info');
        const overrideRow = document.getElementById('capacity-override-row');
        const overrideCheckbox = document.getElementById('override_capacity');

        // Parse section records grouped by class name
        const sectionsData = {
            @foreach($sections as $className => $classSections)
                "{{ $className }}": [
                    @foreach($classSections as $sec)
                        {
                            id: "{{ $sec->id }}",
                            name: "{{ $sec->section }}",
                            capacity: {{ (int) $sec->capacity }},
                            filled: {{ (int) ($sectionOccupancy[$sec->id] ?? 0) }}
                        },
                    @endforeach
                ],
            @endforeach
        };

        function updateSeatInfo() {
            const selectedId = sectionSelect.value;
            let matched = null;
            Object.values(sectionsData).forEach(list => {
                list.forEach(sec => { if (String(sec.id) === String(selectedId)) matched = sec; });
            });

            if (!matched) {
                seatInfo.textContent = '';
                overrideRow.style.display = 'none';
                if (overrideCheckbox) overrideCheckbox.checked = false;
                return;
            }

            const isFull = matched.filled >= matched.capacity;
            seatInfo.textContent = `${matched.filled} / ${matched.capacity} seats filled`;
            seatInfo.classList.toggle('text-danger', isFull);
            overrideRow.style.display = isFull ? '' : 'none';
            if (!isFull && overrideCheckbox) overrideCheckbox.checked = false;
        }

        classSelect.addEventListener('change', function () {
            const selectedOption = this.options[this.selectedIndex];
            const className = selectedOption.getAttribute('data-name');

            // Clear current sections
            sectionSelect.innerHTML = '<option value="">-- Select Section --</option>';

            if (className && sectionsData[className]) {
                sectionsData[className].forEach(sec => {
                    const opt = document.createElement('option');
                    opt.value = sec.id;
                    opt.textContent = `${sec.name} (${sec.filled}/${sec.capacity})`;
                    sectionSelect.appendChild(opt);
                });
            } else if (className) {
                const opt = document.createElement('option');
                opt.value = "";
                opt.textContent = "-- No Sections Configured --";
                sectionSelect.appendChild(opt);
            } else {
                sectionSelect.innerHTML = '<option value="">-- Select Class First --</option>';
            }
            updateSeatInfo();
        });

        sectionSelect.addEventListener('change', updateSeatInfo);
    });
</script>
@endsection

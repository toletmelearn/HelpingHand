@extends('layouts.setup')

@section('title', 'Step ' . $step . ': Onboarding Wizard')

@section('content')

    <!-- Flash Messages -->
    @if(session('error'))
        <div class="alert alert-danger border-0 shadow-sm d-flex align-items-center mb-4" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <div>{{ session('error') }}</div>
        </div>
    @endif
    @if(session('success'))
        <div class="alert alert-success border-0 shadow-sm d-flex align-items-center mb-4" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>
            <div>{{ session('success') }}</div>
        </div>
    @endif

    @if ($step === 1)
        <!-- ==================== STEP 1: SCHOOL PROFILE ==================== -->
        <div>
            <h4 class="fw-bold mb-1">School Profile</h4>
            <p class="text-muted mb-4">Enter basic information about your school. This will be used on reports, fee receipts, and configurations.</p>

            <form action="{{ route('admin.setup-wizard.submit', ['step' => 1]) }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="mb-3">
                    <label for="school_name" class="form-label fw-semibold">School Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control @error('school_name') is-invalid @enderror" id="school_name" name="school_name" value="{{ old('school_name', $data['school_name']) }}" placeholder="e.g. Green Valley High School">
                    @error('school_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="row">
                    <div class="col-md-6 col-12 mb-3">
                        <label for="school_email" class="form-label fw-semibold">School Email <span class="text-danger">*</span></label>
                        <input type="email" class="form-control @error('school_email') is-invalid @enderror" id="school_email" name="school_email" value="{{ old('school_email', $data['school_email']) }}" placeholder="info@school.com">
                        @error('school_email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6 col-12 mb-3">
                        <label for="school_phone" class="form-label fw-semibold">School Phone <span class="text-danger">*</span></label>
                        <input type="text" class="form-control @error('school_phone') is-invalid @enderror" id="school_phone" name="school_phone" value="{{ old('school_phone', $data['school_phone']) }}" placeholder="+91-1234567890">
                        @error('school_phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>

                <div class="mb-3">
                    <label for="school_address" class="form-label fw-semibold">School Address <span class="text-danger">*</span></label>
                    <textarea class="form-control @error('school_address') is-invalid @enderror" id="school_address" name="school_address" rows="3" placeholder="Enter school physical address">{{ old('school_address', $data['school_address']) }}</textarea>
                    @error('school_address') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="row">
                    <div class="col-md-6 col-12 mb-4">
                        <label for="school_logo" class="form-label fw-semibold">School Logo</label>
                        <input type="file" class="form-control @error('school_logo') is-invalid @enderror" id="school_logo" name="school_logo">
                        @error('school_logo') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        @if($data['school_logo'])
                            <div class="mt-2 text-muted" style="font-size: 0.8rem;">
                                <i class="bi bi-file-earmark-image me-1"></i> Current logo: <a href="{{ asset('storage/' . $data['school_logo']) }}" target="_blank">View file</a>
                            </div>
                        @endif
                    </div>
                    <div class="col-md-6 col-12 mb-4">
                        <label for="payment_qr" class="form-label fw-semibold">Payment QR Code</label>
                        <input type="file" class="form-control @error('payment_qr') is-invalid @enderror" id="payment_qr" name="payment_qr">
                        @error('payment_qr') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        @if($data['payment_qr'])
                            <div class="mt-2 text-muted" style="font-size: 0.8rem;">
                                <i class="bi bi-qr-code me-1"></i> Current QR: <a href="{{ asset('storage/' . $data['payment_qr']) }}" target="_blank">View file</a>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary">Save & Continue <i class="bi bi-arrow-right ms-1"></i></button>
                </div>
            </form>
        </div>

    @elseif ($step === 2)
        <!-- ==================== STEP 2: ACADEMIC SESSION ==================== -->
        <div>
            <h4 class="fw-bold mb-1">Academic Session</h4>
            <p class="text-muted mb-4">Initialize the active academic session. All schedules, attendances, and billing cycles operate under this year.</p>

            <form action="{{ route('admin.setup-wizard.submit', ['step' => 2]) }}" method="POST">
                @csrf
                <div class="mb-3">
                    <label for="name" class="form-label fw-semibold">Session Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $data['name']) }}" placeholder="e.g. 2026-27">
                    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    <small class="text-muted mt-1 d-block">Format: YYYY-YY or YYYY-YYYY</small>
                </div>

                <div class="mb-3">
                    <label for="code" class="form-label fw-semibold">Session Code <span class="text-danger">*</span></label>
                    <input type="text" class="form-control @error('code') is-invalid @enderror" id="code" name="code" value="{{ old('code', $data['code']) }}" placeholder="e.g. ACAD-2026">
                    @error('code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="row mb-4">
                    <div class="col-md-6 col-12 mb-3">
                        <label for="start_date" class="form-label fw-semibold">Start Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control @error('start_date') is-invalid @enderror" id="start_date" name="start_date" value="{{ old('start_date', $data['start_date']) }}">
                        @error('start_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6 col-12 mb-3">
                        <label for="end_date" class="form-label fw-semibold">End Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control @error('end_date') is-invalid @enderror" id="end_date" name="end_date" value="{{ old('end_date', $data['end_date']) }}">
                        @error('end_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>

                <div class="d-flex justify-content-between">
                    <a href="{{ route('admin.setup-wizard', ['step' => 1]) }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Back</a>
                    <button type="submit" class="btn btn-primary">Save & Continue <i class="bi bi-arrow-right ms-1"></i></button>
                </div>
            </form>
        </div>

    @elseif ($step === 3)
        <!-- ==================== STEP 3: CLASSES & SECTIONS ==================== -->
        <div>
            <h4 class="fw-bold mb-1">Classes & Sections</h4>
            <p class="text-muted mb-4">Select the classes operating in your school, and choose their active sections. Checkboxes default to common configurations.</p>

            <form action="{{ route('admin.setup-wizard.submit', ['step' => 3]) }}" method="POST">
                @csrf
                
                <div class="mb-4" style="max-height: 400px; overflow-y: auto; padding-right: 0.5rem;">
                    @error('classes')
                        <div class="alert alert-danger border-0 p-2 py-1 mb-3" style="font-size: 0.85rem;"><i class="bi bi-exclamation-circle me-1"></i>{{ $message }}</div>
                    @enderror

                    <div class="row g-3">
                        @foreach($data['available_classes'] as $cls)
                            @php
                                $isChecked = in_array($cls['name'], old('classes', $data['configured_classes']));
                            @endphp
                            <div class="col-md-6 col-12">
                                <input type="checkbox" name="classes[]" value="{{ $cls['name'] }}" id="cls_{{ $cls['order'] }}" class="d-none class-checkbox" {{ $isChecked ? 'checked' : '' }} onchange="toggleSections('{{ $cls['order'] }}')">
                                <label for="cls_{{ $cls['order'] }}" class="w-100 d-block" style="cursor: pointer;">
                                    <div class="class-grid-card shadow-sm h-100 d-flex flex-column justify-content-between">
                                        <div class="d-flex align-items-center justify-content-between mb-2">
                                            <span class="fw-bold text-dark">{{ $cls['name'] }}</span>
                                            <div class="form-check form-switch m-0">
                                                <input class="form-check-input check-indicator" type="checkbox" style="pointer-events: none;" id="switch_{{ $cls['order'] }}" {{ $isChecked ? 'checked' : '' }}>
                                            </div>
                                        </div>
                                        
                                        <!-- Sections Checkboxes inside Card -->
                                        <div class="mt-2 section-checkbox-container" id="sections_container_{{ $cls['order'] }}" style="{{ $isChecked ? '' : 'display: none;' }}">
                                            <small class="text-muted d-block mb-1 font-semibold" style="font-size: 0.75rem;">Active Sections:</small>
                                            @foreach($data['available_sections'] as $sec)
                                                @php
                                                    $isSecChecked = in_array($sec, old('sections.' . $cls['name'], ['A', 'B']));
                                                @endphp
                                                <input type="checkbox" name="sections[{{ $cls['name'] }}][]" value="{{ $sec }}" id="sec_{{ $cls['order'] }}_{{ $sec }}" class="section-badge-input" {{ $isSecChecked ? 'checked' : '' }}>
                                                <label for="sec_{{ $cls['order'] }}_{{ $sec }}" class="section-badge-label">{{ $sec }}</label>
                                            @endforeach
                                        </div>
                                    </div>
                                </label>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="d-flex justify-content-between">
                    <a href="{{ route('admin.setup-wizard', ['step' => 2]) }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Back</a>
                    <button type="submit" class="btn btn-primary">Save & Continue <i class="bi bi-arrow-right ms-1"></i></button>
                </div>
            </form>
        </div>

    @elseif ($step === 4)
        <!-- ==================== STEP 4: SUBJECTS SETUP ==================== -->
        <div>
            <h4 class="fw-bold mb-1">Academics Curriculum</h4>
            <p class="text-muted mb-4">Initialize core academic subjects. You can map these subjects to teachers and classes inside the main dashboard panel.</p>

            <form action="{{ route('admin.setup-wizard.submit', ['step' => 4]) }}" method="POST">
                @csrf
                
                <div class="mb-4" style="max-height: 400px; overflow-y: auto; padding-right: 0.5rem;">
                    @error('subjects')
                        <div class="alert alert-danger border-0 p-2 py-1 mb-3" style="font-size: 0.85rem;"><i class="bi bi-exclamation-circle me-1"></i>{{ $message }}</div>
                    @enderror

                    <div class="row g-2">
                        @foreach($data['predefined_subjects'] as $subj)
                            @php
                                $isSubjChecked = in_array($subj, old('subjects', count($data['configured_subjects']) > 0 ? $data['configured_subjects'] : ['Mathematics', 'English', 'Science', 'Social Studies']));
                            @endphp
                            <div class="col-md-6 col-12">
                                <div class="class-grid-card shadow-sm d-flex align-items-center justify-content-between">
                                    <div class="form-check m-0">
                                        <input class="form-check-input" type="checkbox" name="subjects[]" value="{{ $subj }}" id="subj_{{ Str::slug($subj) }}" {{ $isSubjChecked ? 'checked' : '' }}>
                                        <label class="form-check-label fw-bold text-dark ms-2" for="subj_{{ Str::slug($subj) }}">
                                            {{ $subj }}
                                        </label>
                                    </div>
                                    <i class="bi bi-book text-muted"></i>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="d-flex justify-content-between">
                    <a href="{{ route('admin.setup-wizard', ['step' => 3]) }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Back</a>
                    <button type="submit" class="btn btn-primary">Save & Continue <i class="bi bi-arrow-right ms-1"></i></button>
                </div>
            </form>
        </div>

    @elseif ($step === 5)
        <!-- ==================== STEP 5: FINISH SETUP ==================== -->
        <div>
            <h4 class="fw-bold mb-1">Configuration Overview</h4>
            <p class="text-muted mb-4">Onboarding configurations are prepared. Please review your configurations before finalizing onboarding.</p>

            <div class="card border-0 shadow-sm mb-4" style="background-color: #fafbfe;">
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-6">
                            <span class="text-muted d-block" style="font-size: 0.8rem;">School Profile</span>
                            <strong class="text-dark">{{ $data['school_name'] }}</strong>
                        </div>
                        <div class="col-6">
                            <span class="text-muted d-block" style="font-size: 0.8rem;">Active Session</span>
                            <strong class="text-dark">{{ $data['session_name'] }}</strong>
                        </div>
                        <div class="col-6 mt-3">
                            <span class="text-muted d-block" style="font-size: 0.8rem;">School Classes</span>
                            <strong class="text-dark">{{ $data['class_count'] }} Classes</strong>
                        </div>
                        <div class="col-6 mt-3">
                            <span class="text-muted d-block" style="font-size: 0.8rem;">Active Sections</span>
                            <strong class="text-dark">{{ $data['section_count'] }} Sections</strong>
                        </div>
                        <div class="col-6 mt-3">
                            <span class="text-muted d-block" style="font-size: 0.8rem;">Curriculum Subjects</span>
                            <strong class="text-dark">{{ $data['subject_count'] }} Subjects</strong>
                        </div>
                        <div class="col-6 mt-3">
                            <span class="text-muted d-block" style="font-size: 0.8rem;">Status</span>
                            <strong class="text-success"><i class="bi bi-check-circle-fill me-1"></i> Ready for Teacher Import</strong>
                        </div>
                    </div>
                </div>
            </div>

            <form action="{{ route('admin.setup-wizard.complete') }}" method="POST">
                @csrf
                <div class="form-check mb-4 p-0">
                    <div class="class-grid-card shadow-sm d-flex align-items-center">
                        <input class="form-check-input ms-1 @error('confirm_setup') is-invalid @enderror" type="checkbox" name="confirm_setup" id="confirm_setup" value="1">
                        <label class="form-check-label fw-bold text-dark ms-3" for="confirm_setup">
                            I verify that the configurations are correct and I am ready to complete onboarding.
                        </label>
                    </div>
                    @error('confirm_setup') <div class="invalid-feedback d-block mt-2 ms-2">{{ $message }}</div> @enderror
                </div>

                <div class="d-flex justify-content-between">
                    <a href="{{ route('admin.setup-wizard', ['step' => 4]) }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Back</a>
                    <button type="submit" class="btn btn-success border-0 px-4 fw-bold" style="background: #1f9d55; border-radius: 10px;">Complete Setup <i class="bi bi-check-lg ms-1"></i></button>
                </div>
            </form>
        </div>
    @endif

@endsection

@section('scripts')
    @if($step === 3)
        <script>
            function toggleSections(orderId) {
                var checkbox = document.getElementById('cls_' + orderId);
                var switchInput = document.getElementById('switch_' + orderId);
                var container = document.getElementById('sections_container_' + orderId);
                
                if (checkbox.checked) {
                    switchInput.checked = true;
                    container.style.display = 'block';
                    
                    // Auto-check first two sections (A and B) as defaults if none are checked
                    var sections = container.querySelectorAll('.section-badge-input');
                    var anyChecked = false;
                    sections.forEach(function(sec) {
                        if (sec.checked) anyChecked = true;
                    });
                    
                    if (!anyChecked) {
                        if (sections[0]) sections[0].checked = true;
                        if (sections[1]) sections[1].checked = true;
                    }
                } else {
                    switchInput.checked = false;
                    container.style.display = 'none';
                }
            }
        </script>
    @endif
@endsection

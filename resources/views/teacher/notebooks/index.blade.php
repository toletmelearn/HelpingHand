@extends('layouts.teacher')

@section('content')
<div class="container-fluid px-4 py-4 text-dark">
    <div class="row mb-4 align-items-center">
        <div class="col-sm-6">
            <h1 class="h2 text-dark font-weight-bold">Notebook Correction Tracker</h1>
            <p class="text-secondary">Track notebook corrections, note student handwriting/work deficiencies, and log rechecks.</p>
        </div>
        <div class="col-sm-6 text-sm-end">
            <button class="btn btn-primary rounded-pill px-4 py-2 font-weight-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#logCheckModal">
                Log Notebook Correction
            </button>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="card shadow-sm border-0 rounded-lg">
        <div class="card-header bg-white border-0 pt-4 px-4">
            <h5 class="card-title text-dark font-weight-bold mb-0">Correction Log</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light text-secondary font-weight-bold text-uppercase" style="font-size: 0.8rem;">
                        <tr>
                            <th class="ps-4">Student</th>
                            <th>Subject</th>
                            <th>Check Date</th>
                            <th>Deficiencies</th>
                            <th>Recheck Date</th>
                            <th class="pe-4">Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($checks as $check)
                            <tr>
                                <td class="ps-4 font-weight-bold text-dark">{{ $check->student->name }}</td>
                                <td>{{ $check->subject->name }}</td>
                                <td>{{ $check->check_date->format('M d, Y') }}</td>
                                <td>
                                    @if($check->deficiencies)
                                        <span class="badge bg-danger text-capitalize">{{ str_replace('_', ' ', $check->deficiencies) }}</span>
                                    @else
                                        <span class="badge bg-success">Complete</span>
                                    @endif
                                </td>
                                <td>{{ $check->recheck_date ? $check->recheck_date->format('M d, Y') : '-' }}</td>
                                <td class="pe-4 text-secondary">{{ $check->remarks ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-5 text-secondary">No notebook corrections logged yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Log Check Modal -->
<div class="modal fade" id="logCheckModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog text-dark">
        <div class="modal-content border-0 shadow">
            <form action="{{ route('teacher.notebooks.store') }}" method="POST">
                @csrf
                <div class="modal-header bg-primary text-white border-0 py-3">
                    <h5 class="modal-title font-weight-bold">Log Notebook Check</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label text-secondary">Student</label>
                        <select class="form-select text-dark" name="student_id" required>
                            <option value="" disabled selected>Select Student...</option>
                            @foreach($students as $student)
                                <option value="{{ $student->id }}">{{ $student->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-secondary">Subject</label>
                        <select class="form-select text-dark" name="subject_id" required>
                            <option value="" disabled selected>Select Subject...</option>
                            @foreach($subjects as $subject)
                                <option value="{{ $subject->id }}">{{ $subject->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-secondary">Check Date</label>
                        <input type="date" class="form-control text-dark" name="check_date" required value="{{ date('Y-m-d') }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-secondary">Deficiencies Found</label>
                        <select class="form-select text-dark" name="deficiencies">
                            <option value="" selected>None (Complete / Excellent)</option>
                            <option value="incomplete_work">Incomplete Homework / Classwork</option>
                            <option value="poor_handwriting">Poor Handwriting</option>
                            <option value="missing_index">Missing Index Page</option>
                            <option value="dirty_book">Uncovered / Torn Notebook</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-secondary">Follow-Up Recheck Date (Optional)</label>
                        <input type="date" class="form-control text-dark" name="recheck_date">
                    </div>
                    <div class="mb-0">
                        <label class="form-label text-secondary">Teacher Remarks</label>
                        <textarea class="form-control text-dark" name="remarks" rows="3" placeholder="Provide notes on what corrections are needed..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Log</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

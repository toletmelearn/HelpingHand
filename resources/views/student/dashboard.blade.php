@extends('layouts.student')

@section('title', 'Student Dashboard - HelpingHand School ERP')

@section('content')
<div class="container-fluid px-4 py-4">
    <div class="row mb-4">
        <div class="col-md-12">
            <h1 class="h2 text-dark font-weight-bold">
                <i class="fas fa-tachometer-alt"></i> Welcome, {{ $student->name ?? 'Student' }}
            </h1>
            <p class="text-secondary">Class {{ $stats['my_class'] ?? 'N/A' }} &middot; Section {{ $stats['my_section'] ?? 'N/A' }}</p>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-3 col-sm-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <div class="text-secondary text-uppercase" style="font-size: 0.75rem;">Attendance Rate</div>
                    <div class="h3 mb-0 text-dark">{{ number_format($stats['attendance_rate'] ?? 0, 1) }}%</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <div class="text-secondary text-uppercase" style="font-size: 0.75rem;">Pending Homework</div>
                    <div class="h3 mb-0 text-dark">{{ $stats['pending_homework'] ?? 0 }}</div>
                    <a href="{{ route('student.homework.index') }}" class="small">View homework</a>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <div class="text-secondary text-uppercase" style="font-size: 0.75rem;">My Results</div>
                    <div class="h3 mb-0 text-dark">{{ $stats['my_results'] ?? 0 }}</div>
                    <a href="{{ route('student.results.index') }}" class="small">View results</a>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <div class="text-secondary text-uppercase" style="font-size: 0.75rem;">Fee Status</div>
                    @php $feeStatus = $stats['fee_status'] ?? ['status' => 'clear', 'pending' => 0]; @endphp
                    <div class="h3 mb-0 {{ $feeStatus['status'] === 'pending' ? 'text-danger' : 'text-success' }}">
                        {{ $feeStatus['status'] === 'pending' ? 'Pending' : 'Clear' }}
                    </div>
                    @if(($feeStatus['pending'] ?? 0) > 0)
                        <div class="small text-secondary">₹{{ number_format($feeStatus['pending'], 2) }} due</div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0 rounded-lg">
        <div class="card-header bg-white">
            <h5 class="mb-0">Upcoming Exams</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light text-secondary font-weight-bold text-uppercase" style="font-size: 0.8rem;">
                        <tr>
                            <th class="ps-4 py-3">Exam</th>
                            <th class="py-3">Subject</th>
                            <th class="py-3">Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse(($stats['upcoming_exams'] ?? []) as $exam)
                            <tr>
                                <td class="ps-4">{{ $exam->name }}</td>
                                <td>{{ $exam->subject }}</td>
                                <td>{{ optional($exam->exam_date)->format('d M Y') ?? $exam->exam_date }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="text-center text-secondary py-4">No upcoming exams scheduled.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

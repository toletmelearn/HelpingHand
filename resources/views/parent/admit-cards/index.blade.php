@extends('layouts.parent')

@section('title', 'Admit Cards')

@section('content')
<div class="container-fluid">
    <div class="row mb-3">
        <div class="col-12">
            <h2><i class="fas fa-id-card"></i> Admit Cards</h2>
            <p class="text-muted">Your child's published admit cards.</p>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="card">
        <div class="card-body">
            @if(count($admitCards) > 0)
                <div class="table-responsive">
                    <table class="table table-striped table-bordered">
                        <thead>
                            <tr><th>Exam</th><th>Session</th><th>Exam Date</th><th>Status</th><th>Actions</th></tr>
                        </thead>
                        <tbody>
                            @foreach($admitCards as $admitCard)
                            <tr>
                                <td>{{ $admitCard->exam->name ?? 'N/A' }}</td>
                                <td>{{ $admitCard->academic_session }}</td>
                                <td>{{ $admitCard->exam->exam_date ?? 'N/A' }}</td>
                                <td><span class="badge bg-{{ $admitCard->status == 'published' ? 'success' : 'info' }}">{{ ucfirst($admitCard->status) }}</span></td>
                                <td>
                                    <a href="{{ route('parent.admit-cards.show', $admitCard) }}" class="btn btn-sm btn-info">View</a>
                                    <a href="{{ route('parent.admit-cards.download-pdf', $admitCard) }}" class="btn btn-sm btn-primary">Download PDF</a>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="text-center py-5">
                    <h5>No admit cards available yet.</h5>
                    <p class="text-muted">They will appear here once published by the administration.</p>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection

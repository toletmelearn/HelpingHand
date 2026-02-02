@extends('layouts.admin')

@section('title', 'My Admit Cards')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h4>My Admit Cards</h4>
                </div>
                <div class="card-body">
                    @if(session('success'))
                        <div class="alert alert-success">
                            {{ session('success') }}
                        </div>
                    @endif

                    @if(count($admitCards) > 0)
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered">
                                <thead>
                                    <tr>
                                        <th>Exam</th>
                                        <th>Class</th>
                                        <th>Session</th>
                                        <th>Exam Date</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($admitCards as $admitCard)
                                    <tr>
                                        <td>{{ $admitCard->exam->name ?? 'N/A' }}</td>
                                        <td>{{ $admitCard->student->class_name ?? 'N/A' }}</td>
                                        <td>{{ $admitCard->academic_session }}</td>
                                        <td>{{ $admitCard->exam->exam_date ?? 'N/A' }}</td>
                                        <td>
                                            <span class="badge bg-{{ $admitCard->status == 'published' ? 'success' : 'info' }}">
                                                {{ ucfirst($admitCard->status) }}
                                            </span>
                                        </td>
                                        <td>
                                            <a href="{{ route('student.admit-cards.show', $admitCard) }}" class="btn btn-sm btn-info">View</a>
                                            <a href="{{ route('student.admit-cards.download-pdf', $admitCard) }}" class="btn btn-sm btn-primary">Download PDF</a>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-5">
                            <h5>No admit cards available yet.</h5>
                            <p>Your admit cards will be available here once they are published by the administration.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

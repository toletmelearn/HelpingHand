@extends('layouts.admin')

@section('title', 'Student Promotion History')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="card-title">Promotion History: {{ $student->name }}</h4>
                    <a href="{{ route('admin.student-promotions.index') }}" class="btn btn-secondary">
                        Back to Promotions
                    </a>
                </div>
                <div class="card-body">
                    @if(session('success'))
                        <div class="alert alert-success">
                            {{ session('success') }}
                        </div>
                    @endif

                    <div class="table-responsive">
                        <table class="table table-striped table-bordered">
                            <thead>
                                <tr>
                                    <th>Academic Session</th>
                                    <th>From Class</th>
                                    <th>To Class</th>
                                    <th>Promoted By</th>
                                    <th>Promoted At</th>
                                    <th>Remarks</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($promotions as $log)
                                    <tr>
                                        <td>{{ optional($log->academicSession)->name ?? 'N/A' }}</td>
                                        <td>{{ $log->from_class }}</td>
                                        <td>{{ $log->to_class }}</td>
                                        <td>{{ optional($log->promotedBy)->name ?? 'System' }}</td>
                                        <td>{{ optional($log->promoted_at)->format('Y-m-d H:i') }}</td>
                                        <td>{{ $log->remarks ?? '-' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center">No promotion history found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

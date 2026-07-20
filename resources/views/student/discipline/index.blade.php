@extends('layouts.student')

@section('content')
<div class="container-fluid px-4 py-4 text-dark">
    <div class="row mb-4">
        <div class="col-md-8">
            <h1 class="h2 text-dark font-weight-bold">Disciplinary Board</h1>
            <p class="text-secondary">Track behavioral evaluations, demerit score summaries, and action logs.</p>
        </div>
        <div class="col-md-4 text-end">
            <div class="p-3 bg-light rounded shadow-sm d-inline-block text-start">
                <span class="text-secondary d-block" style="font-size: 0.8rem;">TOTAL DEMERIT POINTS</span>
                <strong class="text-danger" style="font-size: 1.8rem; font-weight: 800;">{{ $totalDemerits }}</strong>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0 rounded-lg">
        <div class="card-header bg-white border-0 pt-4 px-4">
            <h5 class="card-title text-dark font-weight-bold mb-0">Incident Records & Notices</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light text-secondary font-weight-bold text-uppercase" style="font-size: 0.8rem;">
                        <tr>
                            <th class="ps-4">Incident Date</th>
                            <th>Incident Title</th>
                            <th>Description</th>
                            <th class="text-center">Demerits</th>
                            <th>Action Taken</th>
                            <th class="pe-4">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($incidents as $incident)
                            <tr>
                                <td class="ps-4 text-dark font-weight-bold">{{ $incident->incident_date->format('M d, Y') }}</td>
                                <td class="text-dark font-weight-bold">{{ $incident->title }}</td>
                                <td style="max-width: 250px;" class="text-truncate" title="{{ $incident->description }}">{{ $incident->description }}</td>
                                <td class="text-center font-weight-bold text-danger">{{ $incident->demerit_points }}</td>
                                <td>
                                    @foreach($incident->actions as $action)
                                        <span class="badge bg-secondary mb-1">{{ str_replace('_', ' ', $action->action_type) }}</span>
                                    @endforeach
                                </td>
                                <td class="pe-4">
                                    @if($incident->status == 'investigating')
                                        <span class="badge bg-warning text-dark">Investigating</span>
                                    @elseif($incident->status == 'action_taken')
                                        <span class="badge bg-danger">Action Taken</span>
                                    @else
                                        <span class="badge bg-success">Resolved</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-5">
                                    <div class="text-success mb-3">
                                        <i class="bi bi-shield-check" style="font-size: 3rem;"></i>
                                    </div>
                                    <h5 class="text-dark font-weight-bold">Clean Disciplinary Record</h5>
                                    <p class="text-secondary mb-0">Excellent! No behavioral incidents have been reported for this profile.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

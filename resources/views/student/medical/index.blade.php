@extends('layouts.student')

@section('content')
<div class="container-fluid px-4 py-4 text-dark">
    <div class="row mb-4">
        <div class="col-md-12">
            <h1 class="h2 text-dark font-weight-bold">My Health Record</h1>
            <p class="text-secondary">View past checkups, clinical measurements, allergies, and vaccination logs.</p>
        </div>
    </div>

    <div class="row">
        <!-- Medical Profile Info -->
        <div class="col-lg-4 mb-4">
            <div class="card shadow-sm border-0 rounded-lg h-100">
                <div class="card-header bg-white border-0 pt-4 px-4">
                    <h5 class="card-title text-dark font-weight-bold mb-0">Biometrics Summary</h5>
                </div>
                <div class="card-body px-4 pb-4">
                    @if($record)
                        <div class="mb-3">
                            <span class="text-secondary d-block mb-1">Blood Group</span>
                            <span class="badge bg-danger px-3 py-2" style="font-size: 1rem;">{{ $record->blood_group ?? 'Not Set' }}</span>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <span class="text-secondary d-block mb-1">Height (cm)</span>
                                <strong class="text-dark">{{ $record->height_cm ?? '-' }} cm</strong>
                            </div>
                            <div class="col-6">
                                <span class="text-secondary d-block mb-1">Weight (kg)</span>
                                <strong class="text-dark">{{ $record->weight_kg ?? '-' }} kg</strong>
                            </div>
                        </div>
                        <div class="mb-3">
                            <span class="text-secondary d-block mb-1">Known Allergies</span>
                            <div class="p-2 bg-light rounded text-danger" style="font-size: 0.9rem;">
                                {{ $record->allergies ?? 'No known allergies.' }}
                            </div>
                        </div>
                        <div class="mb-3">
                            <span class="text-secondary d-block mb-1">Conditions</span>
                            <div class="p-2 bg-light rounded text-dark" style="font-size: 0.9rem;">
                                {{ $record->medical_conditions ?? 'No medical condition logs.' }}
                            </div>
                        </div>
                        <div class="mb-0">
                            <span class="text-secondary d-block mb-1">Emergency Contact</span>
                            <strong class="text-dark">{{ $record->emergency_contact_name ?? '-' }}</strong>
                            <small class="text-secondary d-block">Phone: {{ $record->emergency_contact_phone ?? '-' }}</small>
                        </div>
                    @else
                        <div class="text-center py-4 text-secondary">
                            No biometric medical records generated yet.
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Checkups Log -->
        <div class="col-lg-8 mb-4">
            <div class="card shadow-sm border-0 rounded-lg h-100">
                <div class="card-header bg-white border-0 pt-4 px-4">
                    <h5 class="card-title text-dark font-weight-bold mb-0">Checkup History & Vaccinations</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light text-secondary font-weight-bold text-uppercase" style="font-size: 0.8rem;">
                                <tr>
                                    <th class="ps-4">Date</th>
                                    <th>Doctor Name</th>
                                    <th>Diagnosis & Treatment</th>
                                    <th class="pe-4">Vaccinations</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($checkups as $checkup)
                                    <tr>
                                        <td class="ps-4 text-dark font-weight-bold">{{ $checkup->checkup_date->format('M d, Y') }}</td>
                                        <td class="text-dark">{{ $checkup->doctor_name }}</td>
                                        <td>
                                            <div class="text-dark">{{ $checkup->diagnosis ?? 'Routine checkup' }}</div>
                                            <small class="text-secondary">Treatment: {{ $checkup->treatment ?? 'None' }}</small>
                                        </td>
                                        <td class="pe-4 text-secondary">{{ $checkup->vaccination_logs ?? '-' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center py-5 text-secondary">No clinic logs recorded yet.</td>
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

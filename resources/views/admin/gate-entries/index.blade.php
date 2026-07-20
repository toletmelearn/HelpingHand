@extends('layouts.admin')

@section('content')
<div class="container-fluid py-4">
    <div class="row">
        <div class="col-md-12 mb-4">
            <h1 class="h3 mb-2 text-gray-800">Security Gate Entry Logbook</h1>
            <p class="mb-4">Log and check visitors in and out of the school campus for safety auditing.</p>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="row">
        <!-- New Visitor Form -->
        <div class="col-md-4 mb-4">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Log New Visitor Check-In</h6>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.gate-entries.store') }}" method="POST">
                        @csrf
                        <div class="form-group mb-3">
                            <label>Visitor Name</label>
                            <input type="text" name="visitor_name" class="form-control" placeholder="e.g. Robert Smith" required>
                        </div>
                        <div class="form-group mb-3">
                            <label>Purpose of Visit</label>
                            <textarea name="purpose" class="form-control" rows="3" placeholder="e.g. Parent teacher conference, vendor dropoff" required></textarea>
                        </div>
                        <div class="form-group mb-3">
                            <label>Vehicle Number (Optional)</label>
                            <input type="text" name="vehicle_no" class="form-control" placeholder="e.g. MH-12-XX-1234">
                        </div>
                        <div class="form-group mb-3">
                            <label>Select Host Employee (Optional)</label>
                            <select name="host_user_id" class="form-control">
                                <option value="">-- No Host / General Visit --</option>
                                @foreach($hosts as $host)
                                    <option value="{{ $host->id }}">{{ $host->name }} ({{ $host->email }})</option>
                                @endforeach
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Check In Visitor</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Log Listing -->
        <div class="col-md-8 mb-4">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Active Gate Log Entries</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <th>Visitor Name</th>
                                    <th>Purpose</th>
                                    <th>Vehicle No</th>
                                    <th>Host</th>
                                    <th>Check-In</th>
                                    <th>Check-Out</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($entries as $entry)
                                    <tr>
                                        <td><strong>{{ $entry->visitor_name }}</strong></td>
                                        <td>{{ $entry->purpose }}</td>
                                        <td>{{ $entry->vehicle_no ?? 'None' }}</td>
                                        <td>{{ $entry->host ? $entry->host->name : 'General' }}</td>
                                        <td>{{ $entry->check_in }}</td>
                                        <td>
                                            @if($entry->check_out)
                                                {{ $entry->check_out }}
                                            @else
                                                <span class="badge bg-warning">In Campus</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if(!$entry->check_out)
                                                <form action="{{ route('admin.gate-entries.checkout', $entry->id) }}" method="POST">
                                                    @csrf
                                                    <button type="submit" class="btn btn-danger btn-sm">Check Out</button>
                                                </form>
                                            @else
                                                <span class="text-muted">Checked Out</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center">No visitor records logged today.</td>
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

@extends('layouts.admin')

@section('title', 'Inventory Audit Logs')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4>Inventory Audit Logs</h4>
                    <a href="{{ route('admin.inventory.index') }}" class="btn btn-secondary">Back to Inventory</a>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <form method="GET" action="{{ route('inventory.audit-logs') }}">
                                <div class="row">
                                    <div class="col-md-3">
                                        <input type="text" class="form-control" name="search" placeholder="Search logs..." 
                                               value="{{ request('search') }}">
                                    </div>
                                    <div class="col-md-2">
                                        <select class="form-control" name="user_id">
                                            <option value="">All Users</option>
                                            @foreach($users as $user)
                                                <option value="{{ $user->id }}" {{ request('user_id') == $user->id ? 'selected' : '' }}>
                                                    {{ $user->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <select class="form-control" name="asset_id">
                                            <option value="">All Assets</option>
                                            @foreach($assets as $asset)
                                                <option value="{{ $asset->id }}" {{ request('asset_id') == $asset->id ? 'selected' : '' }}>
                                                    {{ $asset->asset_code }} - {{ $asset->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <select class="form-control" name="event">
                                            <option value="">All Events</option>
                                            <option value="created" {{ request('event') == 'created' ? 'selected' : '' }}>Created</option>
                                            <option value="updated" {{ request('event') == 'updated' ? 'selected' : '' }}>Updated</option>
                                            <option value="deleted" {{ request('event') == 'deleted' ? 'selected' : '' }}>Deleted</option>
                                            <option value="issued" {{ request('event') == 'issued' ? 'selected' : '' }}>Issued</option>
                                            <option value="returned" {{ request('event') == 'returned' ? 'selected' : '' }}>Returned</option>
                                            <option value="disposed" {{ request('event') == 'disposed' ? 'selected' : '' }}>Disposed</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <input type="date" class="form-control" name="date_from" 
                                               value="{{ request('date_from') }}" placeholder="From Date">
                                        <input type="date" class="form-control mt-1" name="date_to" 
                                               value="{{ request('date_to') }}" placeholder="To Date">
                                    </div>
                                </div>
                                <div class="row mt-2">
                                    <div class="col-md-12">
                                        <button type="submit" class="btn btn-primary">Filter</button>
                                        <a href="{{ route('inventory.audit-logs') }}" class="btn btn-secondary">Reset</a>
                                        <a href="{{ route('inventory.audit-logs.export') }}" class="btn btn-success">Export Logs</a>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>User</th>
                                    <th>Asset</th>
                                    <th>Event</th>
                                    <th>Description</th>
                                    <th>Properties</th>
                                    <th>IP Address</th>
                                    <th>Date & Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($logs as $log)
                                <tr>
                                    <td>{{ $log->id }}</td>
                                    <td>{{ $log->user ? $log->user->name : 'N/A' }}</td>
                                    <td>
                                        @if($log->subject && isset($log->subject->asset_code))
                                            {{ $log->subject->asset_code }} - {{ $log->subject->name }}
                                        @else
                                            N/A
                                        @endif
                                    </td>
                                    <td>
                                        @if($log->event == 'created')
                                            <span class="badge badge-success">Created</span>
                                        @elseif($log->event == 'updated')
                                            <span class="badge badge-primary">Updated</span>
                                        @elseif($log->event == 'deleted')
                                            <span class="badge badge-danger">Deleted</span>
                                        @elseif($log->event == 'issued')
                                            <span class="badge badge-warning">Issued</span>
                                        @elseif($log->event == 'returned')
                                            <span class="badge badge-info">Returned</span>
                                        @elseif($log->event == 'disposed')
                                            <span class="badge badge-dark">Disposed</span>
                                        @else
                                            <span class="badge badge-secondary">{{ ucfirst($log->event) }}</span>
                                        @endif
                                    </td>
                                    <td>{{ Str::limit($log->description, 50) }}</td>
                                    <td>
                                        @if(!empty($log->properties))
                                            <button class="btn btn-sm btn-outline-primary" 
                                                    data-toggle="modal" 
                                                    data-target="#propertiesModal{{ $log->id }}">
                                                View Properties
                                            </button>
                                            
                                            <!-- Properties Modal -->
                                            <div class="modal fade" id="propertiesModal{{ $log->id }}" tabindex="-1" role="dialog">
                                                <div class="modal-dialog modal-lg" role="document">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Log Properties</h5>
                                                            <button type="button" class="close" data-dismiss="modal">
                                                                <span>&times;</span>
                                                            </button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <pre>{{ json_encode($log->properties, JSON_PRETTY_PRINT) }}</pre>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        @else
                                            N/A
                                        @endif
                                    </td>
                                    <td>{{ $log->ip_address }}</td>
                                    <td>{{ $log->created_at->format('M d, Y h:i A') }}</td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="8" class="text-center">No audit logs found</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    {{ $logs->links() }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
@extends('layouts.app')

@section('title', 'Live Bell Monitor')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">Live Bell Monitor</h4>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card bg-light mb-3">
                                <div class="card-body text-center">
                                    <h5>Current Time</h5>
                                    <h3 id="currentTime">{{ $now->format('h:i:s A') }}</h3>
                                    <p>{{ $now->format('l, F j, Y') }}</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card bg-info text-white mb-3">
                                <div class="card-body text-center">
                                    <h5>Next Bell in</h5>
                                    <h3 id="nextBellTimer">--:--:--</h3>
                                    <p id="nextBellDetails">Calculating...</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h5>Active Schedule</h5>
                                </div>
                                <div class="card-body">
                                    @if($currentSchedule && $currentSchedule->getData())
                                        @php
                                            $scheduleData = $currentSchedule->getData();
                                            $schedule = $scheduleData['schedule'];
                                        @endphp
                                        
                                        <div class="alert alert-{{ $scheduleData['type'] == 'override' ? 'danger' : ($scheduleData['type'] == 'custom_override' ? 'warning' : 'primary') }} mb-3">
                                            <h5>
                                                <i class="bi bi-bell"></i> 
                                                {{ $schedule['name'] ?? 'Current Schedule' }}
                                                @if($scheduleData['type'] == 'override' || $scheduleData['type'] == 'custom_override')
                                                    <span class="badge bg-danger ms-2">OVERRIDE ACTIVE</span>
                                                @endif
                                            </h5>
                                            <p>
                                                <strong>Type:</strong> 
                                                {{ $scheduleData['type'] == 'override' ? 'Special Day Override' : ($scheduleData['type'] == 'custom_override' ? 'Custom Override' : ucfirst($schedule['season_type'] ?? 'N/A')) }}
                                                @if(isset($scheduleData['override_type']))
                                                    ({{ $scheduleData['override_type'] }})
                                                @endif
                                            </p>
                                        </div>
                                        
                                        <div class="table-responsive">
                                            <table class="table table-striped">
                                                <thead>
                                                    <tr>
                                                        <th>Period #</th>
                                                        <th>Period Name</th>
                                                        <th>Start Time</th>
                                                        <th>End Time</th>
                                                        <th>Type</th>
                                                        <th>Status</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @php
                                                        $periods = $schedule['periods'] ?? [];
                                                        $currentTime = now();
                                                        $currentTimeString = $currentTime->format('H:i');
                                                    @endphp
                                                    @foreach($periods as $period)
                                                        @php
                                                            $startTime = $period['start_time'];
                                                            $endTime = $period['end_time'];
                                                            $isCurrent = ($currentTimeString >= $startTime && $currentTimeString < $endTime);
                                                            $isPassed = $currentTimeString >= $endTime;
                                                        @endphp
                                                        <tr class="{{ $isCurrent ? 'table-primary' : '' }}">
                                                            <td>{{ $period['period_number'] ?? 'N/A' }}</td>
                                                            <td>{{ $period['period_name'] ?? 'N/A' }}</td>
                                                            <td>{{ $period['start_time'] ?? 'N/A' }}</td>
                                                            <td>{{ $period['end_time'] ?? 'N/A' }}</td>
                                                            <td>
                                                                <span class="badge bg-{{ $period['type'] == 'teaching_period' ? 'primary' : ($period['type'] == 'short_break' ? 'warning' : ($period['type'] == 'lunch_break' ? 'info' : 'secondary')) }}">
                                                                    {{ ucfirst(str_replace('_', ' ', $period['type'] ?? 'N/A')) }}
                                                                </span>
                                                            </td>
                                                            <td>
                                                                @if($isCurrent)
                                                                    <span class="badge bg-primary">CURRENT</span>
                                                                @elseif($isPassed)
                                                                    <span class="badge bg-secondary">ENDED</span>
                                                                @else
                                                                    <span class="badge bg-success">UPCOMING</span>
                                                                @endif
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    @else
                                        <div class="alert alert-warning">
                                            <h5><i class="bi bi-exclamation-triangle"></i> No Active Schedule</h5>
                                            <p>No active bell schedule found for today. Please check seasonal schedules or special day overrides.</p>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Update current time every second
function updateTime() {
    const now = new Date();
    document.getElementById('currentTime').textContent = now.toLocaleTimeString();
}

// Update time immediately and then every second
updateTime();
setInterval(updateTime, 1000);

// Function to calculate next bell
function calculateNextBell() {
    // This would typically be implemented with AJAX to get real-time schedule data
    // For now, we'll simulate it
    document.getElementById('nextBellTimer').textContent = '--:--:--';
    document.getElementById('nextBellDetails').textContent = 'Live monitoring active';
}

// Initial calculation
calculateNextBell();
// Recalculate every minute
setInterval(calculateNextBell, 60000);
</script>
@endsection
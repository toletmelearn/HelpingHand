@extends('layouts.app')

@section('title', 'Daily Bell Schedule')

@section('content')
<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-clock"></i> Today's Bell Schedule - {{ $day }}</h2>
        <div>
            <a href="{{ route('bell-timing.index') }}" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Back to Bell Times
            </a>
            <a href="{{ route('bell-timing.weekly') }}" class="btn btn-info ms-2">
                <i class="bi bi-calendar-week"></i> Weekly View
            </a>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="bi bi-funnel"></i> Filter Schedule</h5>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('bell-timing.daily') }}">
                <div class="row">
                    <div class="col-md-4">
                        <label for="class_section" class="form-label">Class/Section</label>
                        <select class="form-select" id="class_section" name="class_section">
                            <option value="">All Classes</option>
                            @foreach($classSections as $section)
                                <option value="{{ $section }}" {{ request('class_section') == $section ? 'selected' : '' }}>
                                    {{ $section }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-filter"></i> Apply Filter
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Daily Schedule -->
    <div class="card">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="bi bi-clock-history"></i> Today's Bell Schedule</h5>
        </div>
        <div class="card-body">
            @if($schedule->isNotEmpty())
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead class="table-dark">
                            <tr>
                                <th>Period</th>
                                <th>Start Time</th>
                                <th>End Time</th>
                                <th>Duration</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($schedule->sortBy('order_index') as $item)
                                <tr id="period-{{ $item->id }}">
                                    <td>
                                        <strong>{{ $item->period_name }}</strong>
                                        @if($item->custom_label)
                                            <br><small class="text-muted">{{ $item->custom_label }}</small>
                                        @endif
                                    </td>
                                    <td>{{ $item->start_time->format('h:i A') }}</td>
                                    <td>{{ $item->end_time->format('h:i A') }}</td>
                                    <td>{{ $item->duration_formatted }}</td>
                                    <td>
                                        @if($item->is_break)
                                            <span class="badge bg-warning">Break</span>
                                        @else
                                            <span class="badge bg-success">Class</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($item->is_active)
                                            <span class="badge bg-success">Active</span>
                                        @else
                                            <span class="badge bg-secondary">Inactive</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <a href="{{ route('bell-timing.show', $item) }}" 
                                               class="btn btn-outline-info" title="View Details">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <a href="{{ route('bell-timing.edit', $item) }}" 
                                               class="btn btn-outline-warning" title="Edit">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> No bell schedules found for today.
                    <a href="{{ route('bell-timing.create') }}" class="alert-link">Create a new schedule</a>.
                </div>
            @endif
        </div>
    </div>

    <!-- Current Time and Next Period -->
    <div class="row mt-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="bi bi-clock"></i> Current Time</h5>
                </div>
                <div class="card-body text-center">
                    <h3 id="currentTime">{{ now()->format('h:i:s A') }}</h3>
                    <p>{{ now()->format('l, F j, Y') }}</p>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="bi bi-alarm"></i> Next Period</h5>
                </div>
                <div class="card-body text-center">
                    <h4 id="nextPeriod">Loading...</h4>
                    <p id="nextPeriodTime"></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Alarm System Controls -->
    <div class="card mt-4">
        <div class="card-header bg-warning text-dark">
            <h5 class="mb-0"><i class="bi bi-bell"></i> Alarm System</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="alarmEnabled" checked>
                        <label class="form-check-label" for="alarmEnabled">Enable Alarm System</label>
                    </div>
                </div>
                <div class="col-md-6">
                    <label for="alarmVolume" class="form-label">Alarm Volume</label>
                    <input type="range" class="form-range" id="alarmVolume" min="0" max="1" step="0.1" value="0.5">
                </div>
            </div>
            <div class="mt-3">
                <button id="testAlarm" class="btn btn-outline-primary">
                    <i class="bi bi-volume-up"></i> Test Alarm Sound
                </button>
                <button id="syncTime" class="btn btn-outline-info ms-2">
                    <i class="bi bi-arrow-repeat"></i> Sync Time
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    // Alarm system functionality
    document.addEventListener('DOMContentLoaded', function() {
        let alarmEnabled = true;
        let alarmVolume = 0.5;
        let audioContext = null;
        
        // Update current time every second
        function updateTime() {
            const now = new Date();
            document.getElementById('currentTime').textContent = 
                now.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', second: '2-digit', hour12: true });
        }
        
        // Initial update
        updateTime();
        
        // Update time every second
        setInterval(updateTime, 1000);
        
        // Get today's schedule from the table
        function getSchedule() {
            const rows = document.querySelectorAll('tbody tr');
            const schedule = [];
            
            rows.forEach(row => {
                const cells = row.querySelectorAll('td');
                if (cells.length >= 4) {
                    const periodName = cells[0].textContent.trim().split('\n')[0];
                    const startTime = cells[1].textContent.trim();
                    const endTime = cells[2].textContent.trim();
                    
                    schedule.push({
                        period: periodName,
                        start: startTime,
                        end: endTime,
                        rowId: row.id
                    });
                }
            });
            
            return schedule.sort((a, b) => {
                return a.start.localeCompare(b.start);
            });
        }
        
        // Check if current time matches any period start/end time
        function checkPeriodChange() {
            if (!alarmEnabled) return;
            
            const now = new Date();
            const currentTimeStr = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: false }).slice(0, 5);
            
            const schedule = getSchedule();
            let nextPeriod = null;
            
            for (let i = 0; i < schedule.length; i++) {
                const period = schedule[i];
                
                // Highlight current period
                if (currentTimeStr >= period.start.slice(0, 5) && currentTimeStr <= period.end.slice(0, 5)) {
                    document.getElementById(period.rowId).classList.add('table-primary');
                } else {
                    document.getElementById(period.rowId).classList.remove('table-primary');
                }
                
                // Check if it's time for next period start
                if (period.start.slice(0, 5) === currentTimeStr) {
                    // Play alarm sound
                    playAlarmSound();
                    // Add visual indicator
                    document.getElementById(period.rowId).classList.add('table-success');
                    setTimeout(() => {
                        document.getElementById(period.rowId).classList.remove('table-success');
                    }, 2000);
                }
                
                // Find next period
                if (nextPeriod === null && period.start.slice(0, 5) > currentTimeStr) {
                    nextPeriod = period;
                }
            }
            
            // Update next period display
            if (nextPeriod) {
                document.getElementById('nextPeriod').textContent = nextPeriod.period;
                document.getElementById('nextPeriodTime').textContent = `Starts at ${nextPeriod.start}`;
            } else {
                document.getElementById('nextPeriod').textContent = 'No more periods today';
                document.getElementById('nextPeriodTime').textContent = '';
            }
        }
        
        // Play alarm sound
        function playAlarmSound() {
            try {
                if (!audioContext) {
                    audioContext = new (window.AudioContext || window.webkitAudioContext)();
                }
                
                // Create oscillator for beep sound
                const oscillator = audioContext.createOscillator();
                const gainNode = audioContext.createGain();
                
                oscillator.connect(gainNode);
                gainNode.connect(audioContext.destination);
                
                oscillator.type = 'sine';
                oscillator.frequency.setValueAtTime(800, audioContext.currentTime);
                gainNode.gain.setValueAtTime(alarmVolume, audioContext.currentTime);
                
                oscillator.start();
                oscillator.stop(audioContext.currentTime + 0.5); // 0.5 second beep
                
            } catch (e) {
                console.log('Audio context error:', e);
                // Fallback: use system alert if audio fails
                // alert('Bell time!');
            }
        }
        
        // Test alarm button
        document.getElementById('testAlarm').addEventListener('click', function() {
            playAlarmSound();
        });
        
        // Alarm enabled toggle
        document.getElementById('alarmEnabled').addEventListener('change', function() {
            alarmEnabled = this.checked;
        });
        
        // Volume control
        document.getElementById('alarmVolume').addEventListener('input', function() {
            alarmVolume = parseFloat(this.value);
        });
        
        // Sync time button
        document.getElementById('syncTime').addEventListener('click', function() {
            updateTime();
        });
        
        // Check for period changes every minute
        checkPeriodChange();
        setInterval(checkPeriodChange, 60000); // Check every minute
    });
</script>
@endsection
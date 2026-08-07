@extends('layouts.admin')

@section('title', 'Teacher Availability - ' . $teacher->name)

@section('content')
<style>
    .avail-grid {
        display: grid;
        grid-template-columns: 140px repeat({{ count($days) }}, 1fr);
        gap: 8px;
    }
    .avail-header {
        background: #4e73df;
        color: white;
        text-align: center;
        padding: 10px;
        font-weight: bold;
        border-radius: 6px;
    }
    .avail-cell {
        min-height: 60px;
        border-radius: 6px;
        border: 1px solid #d1d3e2;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        user-select: none;
        font-weight: 600;
        background: #eafaf1;
        color: #1cc88a;
    }
    .avail-cell.blocked {
        background: #fbeaea;
        color: #e74a3b;
        border-color: #e74a3b;
    }
    .avail-cell.na {
        background: #f8f9fc;
        color: #b7b9cc;
        cursor: default;
    }
</style>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="mb-0"><i class="fas fa-calendar-check"></i> Availability &mdash; {{ $teacher->name }}</h2>
                    <p class="text-muted mb-0">Click a period to toggle it blocked (red) / available (green). Save once when done.</p>
                </div>
                <a href="{{ route('teacher-availability.index') }}" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-circle"></i> {{ $errors->first() }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="card shadow">
        <div class="card-body">
            @if(empty($periods))
                <p class="text-muted text-center py-4 mb-0">No active school-wide periods are configured yet.</p>
            @else
                <form id="availability-form" action="{{ route('teacher-availability.update', $teacher) }}" method="POST">
                    @csrf
                    <div id="blocked-inputs"></div>

                    <div class="avail-grid mb-3">
                        <div class="avail-header">Period</div>
                        @foreach($days as $day)
                            <div class="avail-header">{{ $day }}</div>
                        @endforeach

                        @foreach($periods as $periodName)
                            <div class="avail-cell na" style="cursor:default; background:#eaecf4; color:#5a5c69;">{{ $periodName }}</div>
                            @foreach($days as $day)
                                @php $bellTimingId = $grid[$day][$periodName] ?? null; @endphp
                                @if($bellTimingId)
                                    <div class="avail-cell {{ in_array($bellTimingId, $blockedIds) ? 'blocked' : '' }}"
                                         data-bell-timing-id="{{ $bellTimingId }}"
                                         onclick="toggleCell(this)">
                                        {{ in_array($bellTimingId, $blockedIds) ? 'Blocked' : 'Free' }}
                                    </div>
                                @else
                                    <div class="avail-cell na">N/A</div>
                                @endif
                            @endforeach
                        @endforeach
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Availability
                    </button>
                </form>
            @endif
        </div>
    </div>
</div>

<script>
    function toggleCell(el) {
        el.classList.toggle('blocked');
        el.textContent = el.classList.contains('blocked') ? 'Blocked' : 'Free';
    }

    document.getElementById('availability-form')?.addEventListener('submit', function () {
        const container = document.getElementById('blocked-inputs');
        container.innerHTML = '';
        document.querySelectorAll('.avail-cell.blocked[data-bell-timing-id]').forEach(function (cell) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'blocked[]';
            input.value = cell.dataset.bellTimingId;
            container.appendChild(input);
        });
    });
</script>
@endsection

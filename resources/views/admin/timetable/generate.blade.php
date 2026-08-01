@extends('layouts.admin')

@section('title', 'Generate Timetable (Beta)')

@section('content')
<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Generate Timetable (Beta)</h1>
        <a href="{{ route('timetable.index') }}" class="btn btn-outline-secondary shadow-sm">
            <i class="fas fa-arrow-left"></i> Back to Timetable
        </a>
    </div>

    <div class="alert alert-info">
        <i class="fas fa-info-circle"></i>
        Generating creates a <strong>DRAFT</strong> proposal for every class you select below. It does
        <strong>NOT</strong> change the live, published timetable for any of them -- nothing goes live until you
        review the draft and Publish it.
    </div>

    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Timetable style</h6>
        </div>
        <div class="card-body">
            <div class="form-check">
                <input type="radio" class="form-check-input" name="style" id="styleRotating" value="rotating" checked>
                <label class="form-check-label" for="styleRotating">
                    <strong>Rotating</strong> -- each subject's periods are spread across different days of the week (the default).
                </label>
            </div>
            <div class="form-check mt-2">
                <input type="radio" class="form-check-input" name="style" id="styleFixedDaily" value="fixed_daily">
                <label class="form-check-label" for="styleFixedDaily">
                    <strong>Fixed daily</strong> -- one day's pattern is repeated identically every day (e.g. Maths is always period 3, every day). A subject whose periods/week doesn't divide evenly across the running days is reported instead of guessed at.
                </label>
            </div>
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">Select classes</h6>
            <div class="form-check">
                <input type="checkbox" class="form-check-input" id="selectAllClasses">
                <label class="form-check-label" for="selectAllClasses">Select all active classes</label>
            </div>
        </div>
        <div class="card-body">
            @if($classes->isEmpty())
                <p class="text-muted mb-0">No active classes found.</p>
            @else
                <div class="row">
                    @foreach($classes as $class)
                        <div class="col-md-3 col-sm-4 col-6 mb-2">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input class-checkbox" id="class_{{ $class->id }}" value="{{ $class->id }}">
                                <label class="form-check-label" for="class_{{ $class->id }}">{{ $class->name }}</label>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
        <div class="card-footer">
            <button type="button" id="generateSelectedBtn" class="btn btn-warning" {{ $classes->isEmpty() ? 'disabled' : '' }}>
                <i class="fas fa-magic"></i> Generate Draft for Selected Classes
            </button>
            <span id="generateSelectedStatus" class="ml-3 text-muted"></span>
        </div>
    </div>
</div>

<script>
    document.getElementById('selectAllClasses')?.addEventListener('change', function () {
        document.querySelectorAll('.class-checkbox').forEach(cb => { cb.checked = this.checked; });
    });

    document.getElementById('generateSelectedBtn')?.addEventListener('click', function () {
        const ids = Array.from(document.querySelectorAll('.class-checkbox:checked')).map(cb => cb.value);
        const style = document.querySelector('input[name="style"]:checked')?.value || 'rotating';

        if (ids.length === 0) {
            alert('Select at least one class first.');
            return;
        }

        if (!confirm(`Generate (Beta) will create a DRAFT timetable proposal for ${ids.length} class(es), ${style === 'fixed_daily' ? 'fixed-daily' : 'rotating'} style. It does NOT change the live, published timetable -- nothing goes live until you review and Publish it. Continue?`)) {
            return;
        }

        const btn = this;
        const statusSpan = document.getElementById('generateSelectedStatus');
        btn.disabled = true;
        btn.innerText = 'Generating...';

        fetch(@json(route('timetable.generate')), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}',
            },
            body: JSON.stringify({ school_class_ids: ids, style: style }),
        })
        .then(res => res.json())
        .then(data => pollGenerationStatus(data.status_url, data.review_url, btn, statusSpan))
        .catch(() => {
            btn.disabled = false;
            btn.innerText = 'Generate Draft for Selected Classes';
            alert('Could not start generation. Please try again.');
        });
    });

    function pollGenerationStatus(statusUrl, reviewUrl, btn, statusSpan) {
        const poll = setInterval(() => {
            fetch(statusUrl)
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'completed') {
                        clearInterval(poll);
                        window.location = reviewUrl;
                    } else if (data.status === 'failed') {
                        clearInterval(poll);
                        btn.disabled = false;
                        btn.innerText = 'Generate Draft for Selected Classes';
                        alert('Generation failed: ' + (data.error || 'unknown error'));
                    } else if (statusSpan) {
                        statusSpan.innerText = `Status: ${data.status}...`;
                    }
                })
                .catch(() => clearInterval(poll));
        }, 2000);
    }
</script>
@endsection

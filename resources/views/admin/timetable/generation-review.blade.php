@extends('layouts.admin')

@section('title', 'Generation Review')

@section('content')
<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Generation #{{ $generation->id }} Review</h1>
        <a href="{{ route('timetable.generate.form') }}" class="btn btn-outline-secondary shadow-sm">
            <i class="fas fa-arrow-left"></i> Back to Generate
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    @if(in_array($generation->status, [\App\Models\TimetableGeneration::STATUS_QUEUED, \App\Models\TimetableGeneration::STATUS_RUNNING]))
        <div class="alert alert-warning">
            <i class="fas fa-spinner fa-spin"></i> Still {{ $generation->status }} -- refresh this page in a moment.
        </div>
    @elseif($generation->status === \App\Models\TimetableGeneration::STATUS_FAILED)
        <div class="alert alert-danger">Generation failed: {{ $generation->error }}</div>
    @else
        <div class="card shadow mb-4">
            <div class="card-body">
                <h6 class="font-weight-bold text-dark">
                    {{ $generation->placed_count }} placed, {{ $generation->unplaced_count }} unplaced
                    @if($generation->placement_percent !== null)
                        ({{ $generation->placement_percent }}%)
                    @endif
                    -- status: <span class="text-capitalize">{{ $generation->status }}</span>
                </h6>

                @if($unplacedSentences->isNotEmpty())
                    <div class="alert alert-warning mt-3">
                        <strong>Could not place everything:</strong>
                        <ul class="mb-0">
                            @foreach($unplacedSentences as $sentence)
                                <li>{{ $sentence }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if($generation->status === \App\Models\TimetableGeneration::STATUS_COMPLETED)
                    @can('publish', \App\Models\TimetableSlot::class)
                    <form action="{{ route('timetable.generation.publish', $generation) }}" method="POST" class="d-inline" onsubmit="return confirm('Publish this draft for all {{ $perClass->count() }} class(es)? This will archive the current live timetable for each and make the draft live.');">
                        @csrf
                        <button type="submit" class="btn btn-success"><i class="fas fa-check"></i> Publish All</button>
                    </form>
                    <form action="{{ route('timetable.generation.discard', $generation) }}" method="POST" class="d-inline" onsubmit="return confirm('Discard this draft for all {{ $perClass->count() }} class(es)? The live timetable will be untouched.');">
                        @csrf
                        <button type="submit" class="btn btn-outline-danger"><i class="fas fa-trash"></i> Discard All</button>
                    </form>
                    @endcan
                @endif
            </div>
        </div>

        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Per-class breakdown</h6>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Class</th>
                            <th>Placed</th>
                            <th>Unplaced</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($perClass as $row)
                            <tr class="{{ $row['unplaced'] > 0 ? 'table-warning' : '' }}">
                                <td>{{ $row['class_name'] }}</td>
                                <td>{{ $row['placed'] }}</td>
                                <td>{{ $row['unplaced'] }}</td>
                                <td>
                                    <a href="{{ route('timetable.index', ['school_class_id' => $row['class_id'], 'status' => 'draft']) }}" class="btn btn-sm btn-outline-primary">
                                        View draft grid
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
@endsection

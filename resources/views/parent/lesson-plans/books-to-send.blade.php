@extends('layouts.admin')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-12">
            <h1>Books to Send Tomorrow</h1>
            <p class="mb-4">List of books and notebooks your children need to bring to school tomorrow</p>
            
            @if($booksToSend->isEmpty())
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="bi bi-book display-4 text-muted"></i>
                        <p class="mt-3 text-muted">No books or notebooks required for tomorrow.</p>
                    </div>
                </div>
            @else
                <div class="row">
                    @foreach($booksToSend as $item)
                        <div class="col-md-6 mb-4">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0">{{ $item['child']->name }}</h5>
                                    <small class="text-muted">{{ $item['child']->class->name }} - {{ $item['child']->section->name }}</small>
                                </div>
                                <div class="card-body">
                                    <h6>{{ $item['subject'] }}</h6>
                                    <p class="mb-0"><strong>Required Items:</strong></p>
                                    <p class="mb-0">{{ $item['books'] }}</p>
                                    <small class="text-muted">Date: {{ $item['date']->format('M d, Y') }}</small>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
            
            <div class="mt-4">
                <a href="{{ route('parent.lesson-plans.index') }}" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Back to Lesson Plans
                </a>
            </div>
        </div>
    </div>
</div>
@endsection

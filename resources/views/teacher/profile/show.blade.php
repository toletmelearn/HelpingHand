@extends('layouts.teacher')

@section('content')
<div class="container">
    <h3>Teacher Profile</h3>
    @if($teacher)
    <img src="{{ $teacher->photo_url }}" alt="{{ $teacher->name }}"
         class="rounded-circle mb-3" width="100" height="100" style="object-fit: cover;">
    @endif
    <p>Name: {{ $teacher->name ?? 'Not Found in teachers table' }}</p>
    <p>Email: {{ $teacher->email ?? $login->email ?? 'Not Available' }}</p>
</div>
@endsection
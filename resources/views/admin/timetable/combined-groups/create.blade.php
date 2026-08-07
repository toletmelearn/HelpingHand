@extends('layouts.admin')

@section('title', 'New Combined Class Group - Admin Dashboard')

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="mb-0"><i class="fas fa-object-group"></i> New Combined Class Group</h2>
                    <p class="text-muted mb-0">For subjects taught to several classes together in one period (e.g. a combined Sanskrit/Hindi group).</p>
                </div>
                <a href="{{ route('combined-class-groups.index') }}" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>
    </div>

    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-circle"></i> Please fix the following errors:
            <ul class="mb-0 mt-2">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="card shadow">
        <div class="card-body">
            <form action="{{ route('combined-class-groups.store') }}" method="POST">
                @csrf

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="name" class="form-label fw-bold">Group Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="name" class="form-control" value="{{ old('name') }}" placeholder="e.g. Sanskrit Combined 7A/7B" required>
                    </div>
                    <div class="col-md-3">
                        <label for="subject_id" class="form-label fw-bold">Subject <span class="text-danger">*</span></label>
                        <select name="subject_id" id="subject_id" class="form-select" required>
                            <option value="">-- Choose --</option>
                            @foreach($subjects as $subject)
                                <option value="{{ $subject->id }}" {{ old('subject_id') == $subject->id ? 'selected' : '' }}>{{ $subject->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="academic_session_id" class="form-label fw-bold">Academic Session <span class="text-danger">*</span></label>
                        <select name="academic_session_id" id="academic_session_id" class="form-select" required>
                            <option value="">-- Choose --</option>
                            @foreach($sessions as $session)
                                <option value="{{ $session->id }}" {{ old('academic_session_id') == $session->id ? 'selected' : '' }}>{{ $session->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <hr class="my-4">

                <h5 class="mb-3">Member Classes <small class="text-muted">(at least 2)</small></h5>
                <div id="member-rows"></div>
                <button type="button" class="btn btn-outline-primary btn-sm mb-4" onclick="addMemberRow()">
                    <i class="fas fa-plus"></i> Add Class
                </button>

                <div class="text-center">
                    <button type="submit" class="btn btn-primary btn-lg px-5">
                        <i class="fas fa-save"></i> Create Group
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<template id="member-row-template">
    <div class="row mb-2 member-row align-items-end">
        <div class="col-md-6">
            <select name="members[__i__][school_class_id]" class="form-select" required>
                <option value="">-- Choose Class --</option>
                @foreach($classes as $class)
                    <option value="{{ $class->id }}">{{ $class->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4">
            <select name="members[__i__][section_id]" class="form-select">
                <option value="">-- Whole Class --</option>
                @foreach($sections as $section)
                    <option value="{{ $section->id }}">{{ $section->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <button type="button" class="btn btn-outline-danger btn-sm w-100" onclick="this.closest('.member-row').remove()">
                <i class="fas fa-trash"></i>
            </button>
        </div>
    </div>
</template>

<script>
    let memberRowIndex = 0;

    function addMemberRow() {
        const template = document.getElementById('member-row-template').innerHTML.replaceAll('__i__', memberRowIndex);
        const wrapper = document.createElement('div');
        wrapper.innerHTML = template;
        document.getElementById('member-rows').appendChild(wrapper.firstElementChild);
        memberRowIndex++;
    }

    // Start with 2 rows since a combined group needs at least 2 members.
    addMemberRow();
    addMemberRow();
</script>
@endsection

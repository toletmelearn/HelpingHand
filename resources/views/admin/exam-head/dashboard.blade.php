@extends('layouts.admin')

@section('title', 'Exam Head Dashboard')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">
                        <i class="fas fa-tachometer-alt"></i> Exam Head Dashboard
                    </h4>
                    <p class="mb-0">Control and monitor examination-related activities</p>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="card bg-info text-white">
                                <div class="card-body">
                                    <h5>Result Monitor</h5>
                                    <a href="{{ route('admin.result.monitor') }}" class="btn btn-light mt-2">View</a>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-success text-white">
                                <div class="card-body">
                                    <h5>Class Results</h5>
                                    <a href="{{ route('admin.class.results') }}" class="btn btn-light mt-2">View</a>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-warning text-white">
                                <div class="card-body">
                                    <h5>Edit Marks</h5>
                                    <a href="#" class="btn btn-light mt-2 disabled">Coming Soon</a>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-secondary text-white">
                                <div class="card-body">
                                    <h5>Approve Results</h5>
                                    <a href="#" class="btn btn-light mt-2 disabled">Coming Soon</a>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <h5><i class="fas fa-info-circle"></i> Permissions</h5>
                        <p>As an Exam Head, you have the following permissions:</p>
                        <ul>
                            <li>View result monitoring dashboard</li>
                            <li>View class-wise results</li>
                            <li>Edit marks (coming soon)</li>
                            <li>Approve submitted results (coming soon)</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
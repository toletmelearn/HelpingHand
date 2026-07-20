@extends('layouts.admin')

@section('title', 'Exam Head Dashboard')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">
                        <i class="fas fa-chalkboard-teacher"></i> Exam Head Dashboard
                    </h4>
                    <p class="mb-0">Manage examination processes and results</p>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 col-sm-6 mb-4">
                            <div class="card bg-info text-white h-100">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h5 class="card-title">Result Monitor</h5>
                                            <p class="card-text">Track marks uploading status</p>
                                        </div>
                                        <div class="align-self-center">
                                            <i class="fas fa-chart-bar fa-2x"></i>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-footer d-grid">
                                    <a href="{{ route('exam-head.result-monitor') }}" class="btn btn-light text-info">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-3 col-sm-6 mb-4">
                            <div class="card bg-success text-white h-100">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h5 class="card-title">Class Results</h5>
                                            <p class="card-text">View student-wise marks</p>
                                        </div>
                                        <div class="align-self-center">
                                            <i class="fas fa-table fa-2x"></i>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-footer d-grid">
                                    <a href="{{ route('exam-head.class-results') }}" class="btn btn-light text-success">
                                        <i class="fas fa-search"></i> View
                                    </a>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-3 col-sm-6 mb-4">
                            <div class="card bg-warning text-white h-100">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h5 class="card-title">Edit Marks</h5>
                                            <p class="card-text">Modify student marks</p>
                                        </div>
                                        <div class="align-self-center">
                                            <i class="fas fa-edit fa-2x"></i>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-footer d-grid">
                                    <a href="{{ route('admin.results.index') }}" class="btn btn-light text-warning">
                                        <i class="fas fa-edit"></i> Manage
                                    </a>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-3 col-sm-6 mb-4">
                            <div class="card bg-primary text-white h-100">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h5 class="card-title">Approve Results</h5>
                                            <p class="card-text">Review and approve</p>
                                        </div>
                                        <div class="align-self-center">
                                            <i class="fas fa-check-circle fa-2x"></i>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-footer d-grid">
                                    <a href="{{ route('admin.results.index') }}" class="btn btn-light text-primary">
                                        <i class="fas fa-check"></i> Review
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mt-4">
                        <div class="col-12">
                            <div class="alert alert-info">
                                <h5><i class="fas fa-info-circle"></i> Welcome, Exam Head!</h5>
                                <p>You have special permissions to monitor and manage examination results. Use the options above to access various examination management features.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
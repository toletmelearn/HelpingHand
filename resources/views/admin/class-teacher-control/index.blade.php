@extends('layouts.admin')

@section('title', 'Class Teacher Control - Admin Dashboard')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">
                        <i class="fas fa-chalkboard-teacher"></i> Class Teacher Control
                    </h4>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="card bg-primary text-white text-center">
                                <div class="card-body">
                                    <i class="fas fa-users fa-2x mb-2"></i>
                                    <h5><a href="{{ route('admin.class-teacher-control.assigned-classes') }}" class="text-white">Assigned Classes</a></h5>
                                    <p class="mb-0">Manage class teacher assignments</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card bg-success text-white text-center">
                                <div class="card-body">
                                    <i class="fas fa-user-graduate fa-2x mb-2"></i>
                                    <h5><a href="{{ route('admin.class-teacher-control.student-records') }}" class="text-white">Student Records</a></h5>
                                    <p class="mb-0">View and edit student records</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card bg-info text-white text-center">
                                <div class="card-body">
                                    <i class="fas fa-lock fa-2x mb-2"></i>
                                    <h5><a href="{{ route('admin.class-teacher-control.edit-permissions') }}" class="text-white">Edit Permissions</a></h5>
                                    <p class="mb-0">Configure field-level permissions</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mt-4">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header bg-secondary text-white">
                                    <h5 class="mb-0"><i class="fas fa-list"></i> Quick Actions</h5>
                                </div>
                                <div class="card-body">
                                    <div class="list-group">
                                        <a href="{{ route('admin.class-teacher-control.assigned-classes') }}" class="list-group-item list-group-item-action">
                                            <i class="fas fa-chalkboard-teacher text-primary"></i> Manage Class Teachers
                                        </a>
                                        <a href="{{ route('admin.class-teacher-control.student-records') }}" class="list-group-item list-group-item-action">
                                            <i class="fas fa-user-graduate text-success"></i> View Student Records
                                        </a>
                                        <a href="{{ route('admin.class-teacher-control.edit-permissions') }}" class="list-group-item list-group-item-action">
                                            <i class="fas fa-key text-info"></i> Configure Permissions
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header bg-secondary text-white">
                                    <h5 class="mb-0"><i class="fas fa-shield-alt"></i> Audit & Compliance</h5>
                                </div>
                                <div class="card-body">
                                    <div class="list-group">
                                        <a href="{{ route('admin.audit-logs.index') }}" class="list-group-item list-group-item-action">
                                            <i class="fas fa-history text-warning"></i> View Audit Logs
                                        </a>
                                        <a href="{{ route('admin.audit-logs.index') }}" class="list-group-item list-group-item-action">
                                            <i class="fas fa-file-contract text-danger"></i> Compliance Reports
                                        </a>
                                        <a href="{{ route('admin.audit-logs.index') }}" class="list-group-item list-group-item-action">
                                            <i class="fas fa-bell text-info"></i> Activity Monitoring
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

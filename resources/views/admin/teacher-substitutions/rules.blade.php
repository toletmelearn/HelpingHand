@extends('layouts.admin')

@section('title', 'Substitution Rules - Admin Dashboard')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">
                        <i class="fas fa-cogs"></i> Substitution Rules Configuration
                    </h4>
                </div>
                <div class="card-body">
                    <form>
                        <div class="mb-4">
                            <h5><i class="fas fa-sliders-h"></i> Availability Rules</h5>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" id="sameSubjectPriority" checked>
                                <label class="form-check-label" for="sameSubjectPriority">
                                    <strong>Same Subject Priority:</strong> Give preference to teachers who teach the same subject
                                </label>
                            </div>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" id="sameClassPriority" checked>
                                <label class="form-check-label" for="sameClassPriority">
                                    <strong>Same Class Experience:</strong> Give preference to teachers who have experience with the same class
                                </label>
                            </div>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" id="departmentPriority" checked>
                                <label class="form-check-label" for="departmentPriority">
                                    <strong>Department Matching:</strong> Give preference to teachers in the same department
                                </label>
                            </div>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" id="workloadCheck" checked>
                                <label class="form-check-label" for="workloadCheck">
                                    <strong>Workload Check:</strong> Avoid assigning too many periods to a single teacher
                                </label>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <h5><i class="fas fa-bell"></i> Notification Settings</h5>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" id="notifySubstitute" checked>
                                <label class="form-check-label" for="notifySubstitute">
                                    <strong>Notify Substitute:</strong> Send notification to substitute teacher when assigned
                                </label>
                            </div>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" id="notifyClassTeacher" checked>
                                <label class="form-check-label" for="notifyClassTeacher">
                                    <strong>Notify Class Teacher:</strong> Inform class teacher about the substitution
                                </label>
                            </div>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" id="notifyStudents" checked>
                                <label class="form-check-label" for="notifyStudents">
                                    <strong>Notify Students:</strong> Display substitution information to students
                                </label>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <h5><i class="fas fa-tachometer-alt"></i> Automation Settings</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="autoAssignThreshold">Auto-assign Threshold (minutes after absence marked)</label>
                                        <input type="number" class="form-control" id="autoAssignThreshold" value="30" min="0">
                                        <div class="form-text">Minutes to wait before attempting auto-assignment</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="maxSubstitutionsPerDay">Maximum Substitutions Per Teacher Per Day</label>
                                        <input type="number" class="form-control" id="maxSubstitutionsPerDay" value="2" min="1" max="10">
                                        <div class="form-text">Maximum number of periods a teacher can substitute in one day</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <a href="{{ route('admin.teacher-substitutions.index') }}" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Back to Substitutions
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Rules
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

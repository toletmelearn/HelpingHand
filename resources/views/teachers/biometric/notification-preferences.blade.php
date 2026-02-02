@extends('layouts.admin')

@section('title', 'Notification Preferences')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <h1 class="h3 mb-4">
                <i class="bi bi-bell"></i> Notification Preferences
            </h1>
        </div>
    </div>

    <div class="row">
        <div class="col-md-8">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Manage Your Notification Preferences</h6>
                </div>
                <div class="card-body">
                    <form action="{{ route('teacher.biometric.update-notification-preferences') }}" method="POST">
                        @csrf
                        
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="lateArrivalNotifications" name="enable_late_arrival_notifications" 
                                       {{ (isset($settings->notification_preferences['teacher_'.$teacher->id]['enable_late_arrival_notifications']) ? 
                                           $settings->notification_preferences['teacher_'.$teacher->id]['enable_late_arrival_notifications'] : 
                                           $settings->enable_late_arrival_notifications) ? 'checked' : '' }}>
                                <label class="form-check-label" for="lateArrivalNotifications">
                                    Enable Late Arrival Notifications
                                </label>
                                <div class="form-text">Receive notifications when you arrive late</div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="earlyDepartureNotifications" name="enable_early_departure_notifications" 
                                       {{ (isset($settings->notification_preferences['teacher_'.$teacher->id]['enable_early_departure_notifications']) ? 
                                           $settings->notification_preferences['teacher_'.$teacher->id]['enable_early_departure_notifications'] : 
                                           $settings->enable_early_departure_notifications) ? 'checked' : '' }}>
                                <label class="form-check-label" for="earlyDepartureNotifications">
                                    Enable Early Departure Notifications
                                </label>
                                <div class="form-text">Receive notifications when you leave early</div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="halfDayNotifications" name="enable_half_day_notifications" 
                                       {{ (isset($settings->notification_preferences['teacher_'.$teacher->id]['enable_half_day_notifications']) ? 
                                           $settings->notification_preferences['teacher_'.$teacher->id]['enable_half_day_notifications'] : 
                                           $settings->enable_half_day_notifications) ? 'checked' : '' }}>
                                <label class="form-check-label" for="halfDayNotifications">
                                    Enable Half Day Notifications
                                </label>
                                <div class="form-text">Receive notifications when your working hours are marked as half day</div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="performanceNotifications" name="enable_performance_notifications" 
                                       {{ (isset($settings->notification_preferences['teacher_'.$teacher->id]['enable_performance_notifications']) ? 
                                           $settings->notification_preferences['teacher_'.$teacher->id]['enable_performance_notifications'] : 
                                           $settings->enable_performance_notifications) ? 'checked' : '' }}>
                                <label class="form-check-label" for="performanceNotifications">
                                    Enable Performance Notifications
                                </label>
                                <div class="form-text">Receive positive notifications for good attendance</div>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <a href="{{ route('teacher.biometric.dashboard') }}" class="btn btn-secondary">
                                <i class="bi bi-arrow-left"></i> Back to Dashboard
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save"></i> Save Preferences
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">About Notifications</h6>
                </div>
                <div class="card-body">
                    <p class="mb-3">Configure which types of biometric notifications you'd like to receive.</p>
                    <ul class="mb-0">
                        <li>Adjust settings to control the frequency of notifications</li>
                        <li>Preferences are saved per teacher account</li>
                        <li>Changes take effect immediately</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@extends('layouts.admin')

@section('title', 'Create Notification Setting')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>
            <i class="bi bi-plus-circle"></i> Create Notification Setting
        </h1>
        <a href="{{ route('admin.notification-settings.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Back to Settings
        </a>
    </div>

    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-bell"></i> Notification Configuration</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.notification-settings.store') }}">
                        @csrf
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="event_type" class="form-label">Event Type <span class="text-danger">*</span></label>
                                    <select class="form-select @error('event_type') is-invalid @enderror" 
                                            id="event_type" name="event_type" required>
                                        <option value="">Select Event Type</option>
                                        @foreach($eventTypes as $key => $label)
                                            <option value="{{ $key }}" {{ old('event_type') == $key ? 'selected' : '' }}>
                                                {{ $label }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <div class="form-text">Select the event that triggers this notification</div>
                                    @error('event_type')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="notification_type" class="form-label">Notification Type <span class="text-danger">*</span></label>
                                    <select class="form-select @error('notification_type') is-invalid @enderror" 
                                            id="notification_type" name="notification_type" required>
                                        <option value="">Select Notification Type</option>
                                        @foreach($notificationTypes as $key => $label)
                                            <option value="{{ $key }}" {{ old('notification_type') == $key ? 'selected' : '' }}>
                                                {{ $label }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <div class="form-text">Choose how to deliver the notification</div>
                                    @error('notification_type')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="template_subject" class="form-label">Email Subject</label>
                            <input type="text" class="form-control @error('template_subject') is-invalid @enderror" 
                                   id="template_subject" name="template_subject" value="{{ old('template_subject') }}"
                                   placeholder="Enter email subject (optional for SMS)">
                            <div class="form-text">Available placeholders: {{name}}, {{class}}, {{amount}}, {{due_date}}</div>
                            @error('template_subject')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="template_body" class="form-label">Message Template <span class="text-danger">*</span></label>
                            <textarea class="form-control @error('template_body') is-invalid @enderror" 
                                      id="template_body" name="template_body" rows="6" required
                                      placeholder="Enter your notification message template...">{{ old('template_body') }}</textarea>
                            <div class="form-text">
                                Available placeholders: {{name}}, {{class}}, {{section}}, {{amount}}, {{due_date}}, {{exam_date}}, {{subject}}
                            </div>
                            @error('template_body')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Recipients <span class="text-danger">*</span></label>
                                    <div class="border rounded p-3">
                                        @foreach($recipientTypes as $key => $label)
                                            <div class="form-check mb-2">
                                                <input class="form-check-input" type="checkbox" 
                                                       name="recipients[]" value="{{ $key }}" 
                                                       id="recipient_{{ $key }}" {{ in_array($key, old('recipients', [])) ? 'checked' : '' }}>
                                                <label class="form-check-label" for="recipient_{{ $key }}">
                                                    {{ $label }}
                                                </label>
                                            </div>
                                        @endforeach
                                    </div>
                                    <div class="form-text">Select who will receive this notification</div>
                                    @error('recipients')
                                        <div class="text-danger">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="schedule_type" class="form-label">Schedule Type <span class="text-danger">*</span></label>
                                    <select class="form-select @error('schedule_type') is-invalid @enderror" 
                                            id="schedule_type" name="schedule_type" required>
                                        @foreach($scheduleTypes as $key => $label)
                                            <option value="{{ $key }}" {{ old('schedule_type') == $key ? 'selected' : '' }}>
                                                {{ $label }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <div class="form-text">When should the notification be sent?</div>
                                    @error('schedule_type')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" 
                                               id="is_enabled" name="is_enabled" value="1" 
                                               {{ old('is_enabled', true) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="is_enabled">
                                            Enable Notification
                                        </label>
                                    </div>
                                    <div class="form-text">
                                        Disabled notifications won't be triggered automatically
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="{{ route('admin.notification-settings.index') }}" class="btn btn-outline-secondary">
                                <i class="bi bi-x-circle"></i> Cancel
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save"></i> Create Notification Setting
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Template Examples -->
    <div class="card mt-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="bi bi-lightbulb"></i> Template Examples</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <h6>Fee Due Reminder:</h6>
                    <pre class="bg-light p-3 rounded">Dear {{name}},

Your fee amount of â‚¹{{amount}} for {{class}} is due by {{due_date}}.

Please make the payment at the earliest.

Thank you,
School Administration</pre>
                </div>
                <div class="col-md-6">
                    <h6>Exam Schedule:</h6>
                    <pre class="bg-light p-3 rounded">Dear {{name}},

Your {{subject}} exam is scheduled on {{exam_date}}.

Please be present on time.

Best regards,
Examination Department</pre>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

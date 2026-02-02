@extends('layouts.admin')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Library Settings</h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.library-settings.update', $setting) }}" method="POST">
                        @csrf
                        @method('PUT')
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="default_issue_days" class="form-label">Default Issue Days *</label>
                                <input type="number" 
                                       class="form-control @error('default_issue_days') is-invalid @enderror" 
                                       id="default_issue_days" 
                                       name="default_issue_days" 
                                       value="{{ old('default_issue_days', $setting->default_issue_days) }}" 
                                       min="1" 
                                       max="365" 
                                       required>
                                <div class="form-text">Number of days a book can be issued by default</div>
                                @error('default_issue_days')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="fine_per_day" class="form-label">Fine Per Day ($)*</label>
                                <input type="number" 
                                       class="form-control @error('fine_per_day') is-invalid @enderror" 
                                       id="fine_per_day" 
                                       name="fine_per_day" 
                                       value="{{ old('fine_per_day', $setting->fine_per_day) }}" 
                                       min="0" 
                                       step="0.01" 
                                       required>
                                <div class="form-text">Fine amount charged per day for overdue books</div>
                                @error('fine_per_day')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="low_stock_threshold" class="form-label">Low Stock Threshold *</label>
                                <input type="number" 
                                       class="form-control @error('low_stock_threshold') is-invalid @enderror" 
                                       id="low_stock_threshold" 
                                       name="low_stock_threshold" 
                                       value="{{ old('low_stock_threshold', $setting->low_stock_threshold) }}" 
                                       min="0" 
                                       required>
                                <div class="form-text">Minimum number of copies before book is considered low stock</div>
                                @error('low_stock_threshold')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="auto_reminder_enabled" class="form-label">Auto Reminder</label>
                                <div class="form-check form-switch">
                                    <input class="form-check-input @error('auto_reminder_enabled') is-invalid @enderror" 
                                           type="checkbox" 
                                           id="auto_reminder_enabled" 
                                           name="auto_reminder_enabled" 
                                           value="1"
                                           {{ old('auto_reminder_enabled', $setting->auto_reminder_enabled) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="auto_reminder_enabled">
                                        Enable automatic reminders for overdue books
                                    </label>
                                </div>
                                @error('auto_reminder_enabled')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <a href="{{ route('admin.library.dashboard') }}" class="btn btn-secondary">
                                <i class="bi bi-arrow-left"></i> Back to Dashboard
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save"></i> Save Settings
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

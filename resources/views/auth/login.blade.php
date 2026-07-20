@extends('layouts.public')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-body p-5">
                    <div class="text-center mb-4">
                        @if($logo = \App\Models\AdminConfiguration::get('general', 'school_logo'))
                            <img src="{{ asset('storage/' . $logo) }}" alt="Logo" class="mb-3 rounded bg-light p-2" style="max-height: 80px; max-width: 180px; object-fit: contain; border: 1px solid #dee2e6;">
                        @endif
                        <h3 class="fw-bold mb-1">Sign In</h3>
                        <p class="text-muted small">Access your account</p>
                    </div>

                    <form method="POST" action="{{ route('login') }}">
                        @csrf

                        <div class="mb-3">
                            <label for="login" class="form-label">{{ __('Email / Mobile / ID') }}</label>
                            <input id="login" type="text" class="form-control @error('login') is-invalid @enderror" name="login" value="{{ old('login') }}" required autocomplete="off" placeholder="{{ __('Enter your email, mobile number, or ID') }}">

                            @error('login')
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label for="password" class="form-label">{{ __('Password') }}</label>
                            <input id="password" type="password" class="form-control @error('password') is-invalid @enderror" name="password" required autocomplete="off">

                            @error('password')
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>

                        <div class="d-grid mb-3">
                            <button type="submit" class="btn btn-primary">
                                {{ __('Sign In') }}
                            </button>
                        </div>
                    </form>

                    <div class="text-center">
                        <p class="text-muted small mb-3">Use your registered credential to continue.</p>
                        <div class="small">
                            <a href="{{ route('teacher.login') }}" class="text-muted text-decoration-none me-3">Teacher Portal</a>
                            <span class="text-muted">·</span>
                            <a href="{{ route('parent.login') }}" class="text-muted text-decoration-none ms-3">Parent Portal</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

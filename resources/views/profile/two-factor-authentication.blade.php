@extends('layouts.admin')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h4>Two-Factor Authentication</h4>
                </div>
                
                <div class="card-body">
                    @if (session('status'))
                        <div class="alert alert-success" role="alert">
                            {{ session('status') }}
                        </div>
                    @endif
                    
                    <div class="mb-4">
                        <p>Two-factor authentication (2FA) adds an extra layer of security to your account. When enabled, you'll be prompted for a secure, random token during authentication.</p>
                    </div>
                    
                    @if(auth()->user()->two_factor_enabled ?? false)
                        <div class="alert alert-info">
                            <strong>Two-Factor Authentication is Enabled</strong>
                            <p>You have enabled two-factor authentication. Scan the following QR code using your authenticator application.</p>
                        </div>
                        
                        <div class="mb-4">
                            <div>{!! auth()->user()->twoFactorQrCode() !!}</div>
                            <div class="mt-2">Setup Key: {{ decrypt(auth()->user()->two_factor_secret) }}</div>
                        </div>
                        
                        <div class="mb-4">
                            <p><strong>Setup Key:</strong> {{ decrypt(auth()->user()->two_factor_secret) }}</p>
                        </div>
                        
                        <form method="POST" action="{{ route('user-two-factor-authentication.destroy') }}" class="d-inline">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure you want to disable two-factor authentication?')">Disable Two-Factor Authentication</button>
                        </form>
                    @else
                        <div class="alert alert-warning">
                            <strong>Two-Factor Authentication is Disabled</strong>
                            <p>Two-factor authentication is currently disabled on your account. Enable it to increase the security of your account.</p>
                        </div>
                        
                        <form method="POST" action="{{ route('user-two-factor-authentication.store') }}">
                            @csrf
                            <button type="submit" class="btn btn-primary">Enable Two-Factor Authentication</button>
                        </form>
                    @endif
                    
                    @if(auth()->user()->two_factor_confirmed_at)
                        <div class="mt-4">
                            <h5>Recovery Codes</h5>
                            <p>Store these recovery codes in a secure password manager. They can be used to recover access to your account if your two-factor authentication device is lost.</p>
                            
                            <div class="bg-light p-3 rounded">
                                @foreach (json_decode(decrypt(auth()->user()->two_factor_recovery_codes), true) as $code)
                                    <div>{{ $code }}</div>
                                @endforeach
                            </div>
                            
                            <form method="POST" action="{{ route('user-two-factor-recovery-codes.update') }}" class="mt-3">
                                @csrf
                                @method('PUT')
                                <button type="submit" class="btn btn-secondary">Regenerate Recovery Codes</button>
                            </form>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

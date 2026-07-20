<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Login - HelpingHand School ERP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .login-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            width: 100%;
            max-width: 450px;
        }
        .login-header {
            background: linear-gradient(135deg, #11998e 0%, #0d7566 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .login-header h2 {
            margin: 0;
            font-weight: 600;
        }
        .login-header p {
            margin: 10px 0 0 0;
            opacity: 0.9;
        }
        .login-body {
            padding: 30px;
        }
        .form-control {
            border-radius: 10px;
            padding: 12px 15px;
            border: 2px solid #e9ecef;
            transition: all 0.3s;
        }
        .form-control:focus {
            border-color: #11998e;
            box-shadow: 0 0 0 0.2rem rgba(17, 153, 142, 0.25);
        }
        .btn-login {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            border: none;
            border-radius: 10px;
            padding: 12px;
            font-weight: 600;
            transition: all 0.3s;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(17, 153, 142, 0.4);
        }
        .alert {
            border-radius: 10px;
        }
        .school-logo {
            width: 80px;
            height: 80px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 30px;
            color: #11998e;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <div class="school-logo" style="overflow: hidden; display: flex; align-items: center; justify-content: center; background: white;">
                @if($logo = \App\Models\AdminConfiguration::get('general', 'school_logo'))
                    <img src="{{ asset('storage/' . $logo) }}" alt="Logo" style="width: 100%; height: 100%; object-fit: contain; padding: 5px;">
                @else
                    <i class="fas fa-chalkboard-teacher"></i>
                @endif
            </div>
            <h2>Teacher Login</h2>
            <p>{{ \App\Models\AdminConfiguration::get('general', 'school_name', 'HelpingHand School ERP') }}</p>
        </div>
        
        <div class="login-body">
            @if($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            
            @if(session('error'))
                <div class="alert alert-danger">
                    {{ session('error') }}
                </div>
            @endif

            @if(session('success'))
                <div class="alert alert-success">
                    {{ session('success') }}
                </div>
            @endif
            
            <div class="alert alert-info mb-3" style="font-size: 0.9rem;">
                <strong><i class="fas fa-info-circle"></i> Teacher Portal</strong><br>
                <small>
                    Login with your <strong>Mobile Number</strong> or <strong>Employee ID</strong><br>
                    Default password: <strong>123456</strong><br>
                    <hr class="my-1">
                    <strong>Admin?</strong> <a href="{{ route('login') }}" class="alert-link">Admin Login</a> | 
                    <strong>Parent?</strong> <a href="{{ route('parent.login') }}" class="alert-link">Parent Login</a>
                </small>
            </div>
            
            <form method="POST" action="{{ route('teacher.login.post') }}">
                @csrf
                
                <div class="mb-3">
                    <label class="form-label fw-bold">Username (Mobile or Employee ID)</label>
                    <input type="text" 
                           class="form-control" 
                           name="identifier" 
                           placeholder="Enter your username"
                           value="{{ old('identifier') }}"
                           required
                           autofocus>
                    <div class="form-text">Use your mobile number or employee ID</div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label fw-bold">Password</label>
                    <input type="password" 
                           class="form-control" 
                           name="password" 
                           placeholder="Enter your password"
                           required>
                    <div class="form-text">Default password is <strong>123456</strong> (you will be asked to change it)</div>
                </div>
                
                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input" id="remember" name="remember">
                    <label class="form-check-label" for="remember">
                        Remember me
                    </label>
                </div>
                
                <div class="d-grid">
                    <button type="submit" class="btn btn-login text-white">
                        <i class="fas fa-sign-in-alt me-2"></i> Login
                    </button>
                </div>
            </form>
            
            <div class="mt-4 text-center">
                <small class="text-muted">
                    <i class="fas fa-lock me-1"></i> Secure Teacher Portal
                </small>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apply for Admission - HelpingHand School ERP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: 20px 0;
        }
        .apply-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            width: 100%;
            max-width: 550px;
        }
        .apply-header {
            background: linear-gradient(135deg, #4e73df 0%, #224abe 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .apply-header h2 {
            margin: 0;
            font-weight: 600;
        }
        .apply-header p {
            margin: 10px 0 0 0;
            opacity: 0.9;
        }
        .apply-body {
            padding: 30px;
        }
        .form-control {
            border-radius: 10px;
            padding: 12px 15px;
            border: 2px solid #e9ecef;
            transition: all 0.3s;
        }
        .form-control:focus {
            border-color: #4e73df;
            box-shadow: 0 0 0 0.2rem rgba(78, 115, 223, 0.25);
        }
        .btn-apply {
            background: linear-gradient(135deg, #4e73df 0%, #224abe 100%);
            border: none;
            border-radius: 10px;
            padding: 12px;
            font-weight: 600;
            transition: all 0.3s;
        }
        .btn-apply:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(78, 115, 223, 0.4);
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
            color: #4e73df;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="apply-container">
        <div class="apply-header">
            <div class="school-logo" style="overflow: hidden; display: flex; align-items: center; justify-content: center; background: white;">
                @if($logo = \App\Models\AdminConfiguration::get('general', 'school_logo'))
                    <img src="{{ asset('storage/' . $logo) }}" alt="Logo" style="width: 100%; height: 100%; object-fit: contain; padding: 5px;">
                @else
                    HH
                @endif
            </div>
            <h2>Apply for Admission</h2>
            <p>{{ \App\Models\AdminConfiguration::get('general', 'school_name', 'HelpingHand School ERP') }}</p>
        </div>

        <div class="apply-body">
            @if(session('success'))
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
                </div>
            @endif

            @if($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <p class="text-muted mb-4">Fill in the details below and our admissions team will get in touch with you shortly.</p>

            <form method="POST" action="{{ route('admissions.apply.submit') }}">
                @csrf

                <div class="mb-3">
                    <label class="form-label fw-bold">Candidate / Student Name</label>
                    <input type="text"
                           class="form-control"
                           name="candidate_name"
                           placeholder="Enter student full name"
                           value="{{ old('candidate_name') }}"
                           required>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold">Parent / Guardian Name</label>
                    <input type="text"
                           class="form-control"
                           name="parent_name"
                           placeholder="Enter father or mother name"
                           value="{{ old('parent_name') }}"
                           required>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold">Mobile Phone</label>
                    <input type="text"
                           class="form-control"
                           name="phone"
                           placeholder="Primary contact number"
                           value="{{ old('phone') }}"
                           required>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-bold">Email Address (Optional)</label>
                    <input type="email"
                           class="form-control"
                           name="email"
                           placeholder="parent@example.com"
                           value="{{ old('email') }}">
                </div>

                <div class="d-grid">
                    <button type="submit" class="btn btn-apply btn-lg text-white">
                        <i class="fas fa-paper-plane me-2"></i>Submit Enquiry
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

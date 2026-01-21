<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teachers List - HelpingHand</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        .status-badge {
            font-size: 0.8em;
            padding: 3px 8px;
            border-radius: 12px;
        }
        .status-active { background-color: #d4edda; color: #155724; }
        .status-inactive { background-color: #f8d7da; color: #721c24; }
        .status-on_leave { background-color: #fff3cd; color: #856404; }

        .profile-img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }
    </style>
</head>

<body>
<div class="container-fluid mt-3">

    <!-- HEADER -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">
            👨‍🏫 Teachers Management
            <small class="text-muted fs-6">({{ $teachers->count() }} teachers)</small>
        </h1>

        <div>
            <a href="{{ route('teachers.create') }}" class="btn btn-success">
                ➕ Add New Teacher
            </a>
            <a href="{{ url('/') }}" class="btn btn-outline-secondary ms-2">
                ← Home
            </a>
        </div>
    </div>

    <!-- SUCCESS MESSAGE -->
    @if(session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    <!-- TABLE -->
    @if($teachers->count() > 0)
        <div class="card shadow">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Profile</th>
                            <th>Name</th>
                            <th>Subject</th>
                            <th>Qualification</th>
                            <th>Joining</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                        </thead>

                        <tbody>
                        @foreach($teachers as $teacher)
                            <tr>
                                <td>#{{ $teacher->id }}</td>

                                <!-- PROFILE IMAGE -->
                                <td>
                                    @if($teacher->profile_image)
                                        <img src="{{ asset('storage/'.$teacher->profile_image) }}" class="profile-img">
                                    @else
                                        <div class="profile-img bg-secondary text-white d-flex align-items-center justify-content-center">
                                            {{ strtoupper(substr($teacher->name,0,1)) }}
                                        </div>
                                    @endif
                                </td>

                                <!-- NAME -->
                                <td>
                                    <strong>{{ $teacher->name }}</strong><br>
                                    <small class="text-muted">{{ $teacher->email }}</small>
                                </td>

                                <!-- SUBJECT -->
                                <td>
                                    {{ $teacher->subject_specialization ?? 'N/A' }}
                                </td>

                                <!-- QUALIFICATION -->
                                <td>
                                    {{ $teacher->qualification ?? 'N/A' }}
                                </td>

                                <!-- DATE OF JOINING -->
                                <td>
                                    @if($teacher->date_of_joining)
                                        {{ \Carbon\Carbon::parse($teacher->date_of_joining)->format('d M Y') }}
                                    @else
                                        <span class="text-muted">Not set</span>
                                    @endif
                                </td>

                                <!-- STATUS -->
                                <td>
                                    @php
                                        $statusClass = match($teacher->status) {
                                            'active' => 'status-active',
                                            'on_leave' => 'status-on_leave',
                                            default => 'status-inactive'
                                        };
                                    @endphp

                                    <span class="status-badge {{ $statusClass }}">
                                        {{ ucfirst(str_replace('_',' ',$teacher->status ?? 'inactive')) }}
                                    </span>
                                </td>

                                <!-- ACTIONS -->
                                <td>
                                    @if(!empty($teacher->id))
                                        <div class="btn-group btn-group-sm">

                                            <!-- VIEW -->
                                            <a href="{{ route('teachers.show', ['teacher' => $teacher->id]) }}" class="btn btn-outline-primary">
                                                👁 View
                                            </a>

                                            <!-- EDIT -->
                                            <a href="{{ route('teachers.edit', ['teacher' => $teacher->id]) }}" class="btn btn-outline-warning">
                                                ✏️ Edit
                                            </a>

                                            <!-- DELETE -->
                                            <form action="{{ route('teachers.destroy', ['teacher' => $teacher->id]) }}" method="POST">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit"
                                                        class="btn btn-outline-danger"
                                                        onclick="return confirm('Are you sure you want to delete {{ $teacher->name }}?')">
                                                    🗑 Delete
                                                </button>
                                            </form>

                                        </div>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>

                    </table>
                </div>
            </div>
        </div>

    @else
        <!-- EMPTY STATE -->
        <div class="card shadow text-center py-5">
            <div class="card-body">
                <h3>No Teachers Found</h3>
                <p class="text-muted">Add your first teacher to begin.</p>
                <a href="{{ route('teachers.create') }}" class="btn btn-success">
                    ➕ Add Teacher
                </a>
            </div>
        </div>
    @endif

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

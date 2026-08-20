<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bell Timing Templates - HelpingHand</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body>
    <div class="container mt-4 mb-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="bi bi-journals"></i> Bell Timing Templates</h1>
            <div>
                <a href="{{ route('bell-timing.save-as-template') }}" class="btn btn-outline-primary"><i class="bi bi-copy"></i> Save Bell Timing as Template</a>
                <a href="{{ route('bell-timing-templates.create') }}" class="btn btn-success"><i class="bi bi-plus-circle"></i> Create Template</a>
                <a href="{{ route('bell-timing.index') }}" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Back to Bell Timings</a>
            </div>
        </div>

        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        @endif

        <div class="card">
            <div class="card-body">
                @if($templates->isEmpty())
                    <p class="text-muted mb-0">No templates yet. Create one, or save an existing class's Bell Timing as a template.</p>
                @else
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Description</th>
                                <th>Periods</th>
                                <th>Academic Year</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($templates as $template)
                                <tr>
                                    <td><strong>{{ $template->name }}</strong></td>
                                    <td class="text-muted small">{{ \Illuminate\Support\Str::limit($template->description, 60) }}</td>
                                    <td>{{ $template->slots_count }}</td>
                                    <td>{{ $template->academic_year ?? '—' }}</td>
                                    <td class="text-end">
                                        <a href="{{ route('bell-timing-templates.apply.form', $template) }}" class="btn btn-primary btn-sm"><i class="bi bi-send"></i> Apply to Classes</a>
                                        <a href="{{ route('bell-timing-templates.edit', $template) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-pencil"></i> Edit</a>
                                        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#dup-{{ $template->id }}"><i class="bi bi-files"></i> Duplicate</button>
                                        <form action="{{ route('bell-timing-templates.destroy', $template) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete template &quot;{{ $template->name }}&quot;? Classes it was already applied to are not affected.');">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i> Delete</button>
                                        </form>
                                    </td>
                                </tr>

                                <div class="modal fade" id="dup-{{ $template->id }}" tabindex="-1">
                                    <div class="modal-dialog">
                                        <form action="{{ route('bell-timing-templates.duplicate', $template) }}" method="POST" class="modal-content">
                                            @csrf
                                            <div class="modal-header"><h5 class="modal-title">Duplicate "{{ $template->name }}"</h5></div>
                                            <div class="modal-body">
                                                <label class="form-label">New template name</label>
                                                <input type="text" class="form-control" name="name" value="{{ $template->name }} (Copy)" required>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-success">Duplicate</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

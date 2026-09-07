{{--
    Bulk-assign-by-teacher modal: lets an admin create every class/section/
    subject assignment for one teacher in a single submit instead of
    reopening the Assign New form per class. Posts to the bulk-create
    endpoint added alongside TeacherSubjectAssignmentController::bulkStore().
--}}
<div id="bulkAssignModal" class="modal fade" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-layer-group"></i> Bulk Assign by Teacher</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form id="bulkAssignForm">
                @csrf
                <div class="modal-body">
                    <div id="bulkAssignAlert" class="alert d-none" role="alert"></div>

                    <div class="mb-3">
                        <label for="bulk_teacher_id" class="form-label fw-bold">
                            <i class="fas fa-chalkboard-teacher"></i> Select Teacher <span class="text-danger">*</span>
                        </label>
                        <select id="bulk_teacher_id" class="form-select" required>
                            <option value="">-- Choose Teacher --</option>
                            @foreach($teachers as $teacher)
                                <option value="{{ $teacher->id }}">{{ $teacher->name }} - {{ $teacher->designation ?? 'Teacher' }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="bulk_academic_year" class="form-label fw-bold">
                            <i class="fas fa-calendar-alt"></i> Academic Year
                        </label>
                        <input type="text" id="bulk_academic_year" class="form-control"
                               value="{{ date('Y') . '-' . (date('Y') + 1) }}" placeholder="e.g., 2026-2027">
                    </div>

                    <label class="form-label fw-bold"><i class="fas fa-list"></i> Subject Assignments</label>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle">
                            <thead class="table-dark">
                                <tr>
                                    <th style="width:28%">Class *</th>
                                    <th style="width:20%">Section</th>
                                    <th style="width:32%">Subject *</th>
                                    <th style="width:14%">Periods/Wk</th>
                                    <th style="width:6%"></th>
                                </tr>
                            </thead>
                            <tbody id="bulkAssignRows"></tbody>
                        </table>
                    </div>
                    <button type="button" id="bulkAddRow" class="btn btn-sm btn-outline-secondary mb-4">
                        <i class="fas fa-plus"></i> Add Another Subject
                    </button>

                    <hr>

                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="bulk_make_class_teacher">
                        <label class="form-check-label fw-bold" for="bulk_make_class_teacher">
                            <i class="fas fa-crown text-success"></i> Make this teacher a Class Teacher
                        </label>
                    </div>
                    <div id="bulkClassTeacherOptions" class="row mt-3 d-none">
                        <div class="col-md-6">
                            <label for="bulk_class_teacher_class_id" class="form-label">For Class <span class="text-danger">*</span></label>
                            <select id="bulk_class_teacher_class_id" class="form-select">
                                <option value="">-- Select Class --</option>
                                @foreach($classes as $class)
                                    <option value="{{ $class->id }}">{{ $class->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="bulk_class_teacher_section_id" class="form-label">Section (if any)</label>
                            <select id="bulk_class_teacher_section_id" class="form-select">
                                <option value="">-- Whole Class --</option>
                                @foreach($sections as $section)
                                    <option value="{{ $section->id }}">{{ $section->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <small class="text-muted mt-2">Must match the class/section of one of the subject assignments above.</small>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="bulkAssignSubmit">
                        <i class="fas fa-save"></i> Save All Assignments
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const classesData = @json($classes->map(fn($c) => ['id' => $c->id, 'name' => $c->name])->values());
    const sectionsData = @json($sections->map(fn($s) => ['id' => $s->id, 'name' => $s->name])->values());
    const subjectsData = @json($subjects->map(fn($s) => ['id' => $s->id, 'name' => $s->name])->values());

    const rowsBody = document.getElementById('bulkAssignRows');
    const addRowBtn = document.getElementById('bulkAddRow');
    const form = document.getElementById('bulkAssignForm');
    const alertBox = document.getElementById('bulkAssignAlert');
    const makeClassTeacherCheckbox = document.getElementById('bulk_make_class_teacher');
    const classTeacherOptions = document.getElementById('bulkClassTeacherOptions');
    const submitBtn = document.getElementById('bulkAssignSubmit');

    function optionsHtml(items, placeholder) {
        return `<option value="">${placeholder}</option>` +
            items.map(i => `<option value="${i.id}">${i.name}</option>`).join('');
    }

    function addRow() {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td><select class="form-select form-select-sm row-class" required>${optionsHtml(classesData, '-- Select --')}</select></td>
            <td><select class="form-select form-select-sm row-section">${optionsHtml(sectionsData, '-- All --')}</select></td>
            <td><select class="form-select form-select-sm row-subject" required>${optionsHtml(subjectsData, '-- Select --')}</select></td>
            <td><input type="number" class="form-control form-control-sm row-periods" min="1" max="12" value="3"></td>
            <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger row-remove"><i class="fas fa-times"></i></button></td>
        `;
        rowsBody.appendChild(tr);
    }

    addRowBtn.addEventListener('click', addRow);

    rowsBody.addEventListener('click', function (e) {
        const btn = e.target.closest('.row-remove');
        if (!btn) return;
        if (rowsBody.children.length > 1) {
            btn.closest('tr').remove();
        }
    });

    makeClassTeacherCheckbox.addEventListener('change', function () {
        classTeacherOptions.classList.toggle('d-none', !this.checked);
    });

    document.getElementById('bulkAssignModal').addEventListener('show.bs.modal', function () {
        if (rowsBody.children.length === 0) {
            addRow();
        }
    });

    function showAlert(message, type) {
        alertBox.textContent = message;
        alertBox.className = `alert alert-${type}`;
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        alertBox.className = 'alert d-none';

        const teacherId = document.getElementById('bulk_teacher_id').value;
        if (!teacherId) {
            showAlert('Please select a teacher.', 'danger');
            return;
        }

        const assignments = [];
        rowsBody.querySelectorAll('tr').forEach(row => {
            const classId = row.querySelector('.row-class').value;
            const subjectId = row.querySelector('.row-subject').value;
            if (!classId || !subjectId) return;
            assignments.push({
                class_id: classId,
                section_id: row.querySelector('.row-section').value || null,
                subject_id: subjectId,
                periods_per_week: row.querySelector('.row-periods').value || null,
            });
        });

        if (assignments.length === 0) {
            showAlert('Add at least one subject assignment.', 'danger');
            return;
        }

        const makeClassTeacher = makeClassTeacherCheckbox.checked;
        if (makeClassTeacher && !document.getElementById('bulk_class_teacher_class_id').value) {
            showAlert('Select a class for the class teacher assignment.', 'danger');
            return;
        }

        const payload = {
            teacher_id: teacherId,
            academic_year: document.getElementById('bulk_academic_year').value || null,
            assignments: assignments,
            make_class_teacher: makeClassTeacher,
            class_teacher_class_id: makeClassTeacher ? document.getElementById('bulk_class_teacher_class_id').value : null,
            class_teacher_section_id: makeClassTeacher ? (document.getElementById('bulk_class_teacher_section_id').value || null) : null,
        };

        submitBtn.disabled = true;
        fetch('{{ route('admin.teacher-subject-assignments.bulk-create') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('#bulkAssignForm input[name="_token"]').value,
            },
            body: JSON.stringify(payload),
        })
            .then(response => response.json().then(json => ({ status: response.status, json })))
            .then(({ status, json }) => {
                if (status === 200 && json.success) {
                    showAlert(json.message, 'success');
                    setTimeout(() => window.location.reload(), 800);
                } else {
                    showAlert(json.error || 'Something went wrong.', 'danger');
                }
            })
            .catch(() => showAlert('Network error -- please try again.', 'danger'))
            .finally(() => { submitBtn.disabled = false; });
    });
});
</script>

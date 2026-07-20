@extends('layouts.admin')

@section('title', 'Advanced Report Builder')

@section('content')
<div class="container-fluid">
    <h1 class="mb-4"><i class="bi bi-bar-chart-line"></i> Advanced Report Builder</h1>

    <div class="row">
        <!-- Report Configuration Panel -->
        <div class="col-lg-4 mb-4">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-gear"></i> Report Configuration</h5>
                </div>
                <div class="card-body">
                    <form id="reportBuilderForm">
                        <!-- Report Type -->
                        <div class="mb-3">
                            <label for="report_type" class="form-label">Report Type</label>
                            <select class="form-select" id="report_type" name="report_type" required>
                                <option value="">Select Type</option>
                                <option value="student">Student Report</option>
                                <option value="teacher">Teacher Report</option>
                                <option value="attendance">Attendance Report</option>
                                <option value="fee">Fee Report</option>
                                <option value="exam">Exam Report</option>
                                <option value="result">Result Report</option>
                                <option value="custom">Custom Query</option>
                            </select>
                        </div>

                        <!-- Data Source -->
                        <div class="mb-3">
                            <label for="data_source" class="form-label">Data Source</label>
                            <select class="form-select" id="data_source" name="data_source" required>
                                <option value="">Select Source</option>
                                <option value="students">Students</option>
                                <option value="teachers">Teachers</option>
                                <option value="attendance">Attendance</option>
                                <option value="fees">Fees</option>
                                <option value="exams">Exams</option>
                                <option value="results">Results</option>
                            </select>
                        </div>

                        <!-- Columns Selection -->
                        <div class="mb-3">
                            <label class="form-label">Select Columns</label>
                            <div id="columnsContainer" class="border rounded p-3" style="max-height: 200px; overflow-y: auto;">
                                <p class="text-muted">Select a data source first</p>
                            </div>
                        </div>

                        <!-- Filters -->
                        <div class="mb-3">
                            <label class="form-label">Filters</label>
                            <div id="filtersContainer">
                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="addFilter()">
                                    <i class="bi bi-plus"></i> Add Filter
                                </button>
                            </div>
                        </div>

                        <!-- Group By -->
                        <div class="mb-3">
                            <label for="group_by" class="form-label">Group By (Optional)</label>
                            <input type="text" class="form-control" id="group_by" name="group_by" placeholder="e.g., class_id">
                        </div>

                        <!-- Order By -->
                        <div class="row mb-3">
                            <div class="col-8">
                                <label for="order_by" class="form-label">Order By</label>
                                <input type="text" class="form-control" id="order_by" name="order_by" placeholder="e.g., name">
                            </div>
                            <div class="col-4">
                                <label for="order_direction" class="form-label">Direction</label>
                                <select class="form-select" id="order_direction" name="order_direction">
                                    <option value="asc">ASC</option>
                                    <option value="desc">DESC</option>
                                </select>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-play-fill"></i> Generate Report
                            </button>
                            <button type="button" class="btn btn-outline-success" onclick="saveTemplate()">
                                <i class="bi bi-save"></i> Save as Template
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Saved Templates -->
            <div class="card mt-3">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-bookmarks"></i> Saved Templates</h6>
                </div>
                <div class="card-body">
                    <div id="templatesContainer">
                        <p class="text-muted">No templates saved</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Report Preview Panel -->
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-table"></i> Report Preview</h5>
                    <div class="btn-group">
                        <button class="btn btn-sm btn-light" onclick="exportReport('pdf')">
                            <i class="bi bi-file-pdf"></i> PDF
                        </button>
                        <button class="btn btn-sm btn-light" onclick="exportReport('excel')">
                            <i class="bi bi-file-excel"></i> Excel
                        </button>
                        <button class="btn btn-sm btn-light" onclick="exportReport('csv')">
                            <i class="bi bi-filetype-csv"></i> CSV
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div id="reportPreview">
                        <div class="text-center text-muted py-5">
                            <i class="bi bi-file-earmark-text" style="font-size: 4rem;"></i>
                            <p class="mt-3">Configure report settings and click "Generate Report" to preview</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Chart Builder -->
            <div class="card mt-3">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="bi bi-graph-up"></i> Chart Builder</h5>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="chart_type" class="form-label">Chart Type</label>
                            <select class="form-select" id="chart_type">
                                <option value="bar">Bar Chart</option>
                                <option value="line">Line Chart</option>
                                <option value="pie">Pie Chart</option>
                                <option value="doughnut">Doughnut Chart</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">&nbsp;</label>
                            <button class="btn btn-info w-100" onclick="generateChart()">
                                <i class="bi bi-bar-chart"></i> Generate Chart
                            </button>
                        </div>
                    </div>
                    <canvas id="reportChart" height="100"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
let reportData = [];
let selectedColumns = [];

// Column definitions for each data source
const dataSourceColumns = {
    students: ['id', 'name', 'roll_number', 'class_id', 'section_id', 'email', 'phone', 'date_of_birth', 'gender', 'admission_date'],
    teachers: ['id', 'name', 'email', 'phone', 'designation', 'wing', 'date_of_joining', 'qualification'],
    attendance: ['id', 'student_id', 'date', 'status', 'remarks', 'marked_by'],
    fees: ['id', 'student_id', 'amount', 'status', 'due_date', 'paid_date', 'payment_mode'],
    exams: ['id', 'name', 'class_id', 'section_id', 'subject_id', 'date', 'total_marks', 'duration'],
    results: ['id', 'student_id', 'exam_id', 'subject_id', 'marks_obtained', 'total_marks', 'grade']
};

// Data source change handler
document.getElementById('data_source').addEventListener('change', function() {
    const source = this.value;
    if (source && dataSourceColumns[source]) {
        renderColumnCheckboxes(dataSourceColumns[source]);
    }
});

function renderColumnCheckboxes(columns) {
    const container = document.getElementById('columnsContainer');
    container.innerHTML = columns.map(col => `
        <div class="form-check">
            <input class="form-check-input column-checkbox" type="checkbox" value="${col}" id="col_${col}">
            <label class="form-check-label" for="col_${col}">
                ${col.replace(/_/g, ' ').toUpperCase()}
            </label>
        </div>
    `).join('');
}

// Form submission
document.getElementById('reportBuilderForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    // Get selected columns
    selectedColumns = Array.from(document.querySelectorAll('.column-checkbox:checked')).map(cb => cb.value);
    
    if (selectedColumns.length === 0) {
        alert('Please select at least one column');
        return;
    }
    
    const formData = {
        report_type: document.getElementById('report_type').value,
        data_source: document.getElementById('data_source').value,
        columns: selectedColumns,
        filters: [], // Collect from filter inputs
        group_by: document.getElementById('group_by').value,
        order_by: document.getElementById('order_by').value,
        order_direction: document.getElementById('order_direction').value,
    };
    
    try {
        const response = await fetch('{{ route("admin.report-builder.generate") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify(formData)
        });
        
        const result = await response.json();
        if (result.success) {
            reportData = result.data;
            renderReportPreview(result.data);
        }
    } catch (error) {
        console.error('Error generating report:', error);
        alert('Error generating report');
    }
});

function renderReportPreview(data) {
    if (data.length === 0) {
        document.getElementById('reportPreview').innerHTML = '<p class="text-muted text-center">No data found</p>';
        return;
    }
    
    let html = `
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead class="table-dark">
                    <tr>${selectedColumns.map(col => `<th>${col.replace(/_/g, ' ').toUpperCase()}</th>`).join('')}</tr>
                </thead>
                <tbody>
                    ${data.map(row => `<tr>${selectedColumns.map(col => `<td>${row[col] ?? 'N/A'}</td>`).join('')}</tr>`).join('')}
                </tbody>
            </table>
        </div>
        <p class="text-muted">Total Records: ${data.length}</p>
    `;
    
    document.getElementById('reportPreview').innerHTML = html;
}

function exportReport(format) {
    if (reportData.length === 0) {
        alert('Please generate a report first');
        return;
    }
    
    const formData = new FormData();
    formData.append('report_type', document.getElementById('report_type').value);
    formData.append('data_source', document.getElementById('data_source').value);
    formData.append('columns', JSON.stringify(selectedColumns));
    
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = `/admin/report-builder/export/${format}`;
    form.innerHTML = `
        <input type="hidden" name="_token" value="{{ csrf_token() }}">
        <input type="hidden" name="report_type" value="${document.getElementById('report_type').value}">
        <input type="hidden" name="data_source" value="${document.getElementById('data_source').value}">
        <input type="hidden" name="columns" value='${JSON.stringify(selectedColumns)}'>
    `;
    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
}

function generateChart() {
    if (reportData.length === 0) {
        alert('Please generate a report first');
        return;
    }
    
    const chartType = document.getElementById('chart_type').value;
    const ctx = document.getElementById('reportChart').getContext('2d');
    
    // Simple chart with first column as labels, second as data
    const labels = reportData.map(row => row[selectedColumns[0]]);
    const data = reportData.map(row => row[selectedColumns[1]] || 0);
    
    if (window.reportChart) {
        window.reportChart.destroy();
    }
    
    window.reportChart = new Chart(ctx, {
        type: chartType,
        data: {
            labels: labels,
            datasets: [{
                label: selectedColumns[1],
                data: data,
                backgroundColor: 'rgba(54, 162, 235, 0.8)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true
        }
    });
}
</script>
@endpush
@endsection

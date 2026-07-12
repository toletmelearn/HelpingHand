<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\Attendance;
use App\Models\FeeCollection;
use App\Models\Exam;
use App\Models\Result;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\CustomReportExport;

class ReportBuilderController extends Controller
{
    /**
     * Show report builder interface
     */
    public function index()
    {
        return view('admin.report-builder.index');
    }

    /**
     * Generate custom report
     */
    public function generate(Request $request)
    {
        $request->validate([
            'report_type' => 'required|in:student,teacher,attendance,fee,exam,result,custom',
            'data_source' => 'required|string',
            'columns' => 'required|array',
            'filters' => 'sometimes|array',
            'group_by' => 'sometimes|string',
            'order_by' => 'sometimes|string',
            'order_direction' => 'sometimes|in:asc,desc',
        ]);

        $data = $this->fetchReportData($request->all());
        
        return response()->json([
            'success' => true,
            'data' => $data,
            'total' => count($data),
        ]);
    }

    /**
     * Export report
     */
    public function export(Request $request, $format = 'pdf')
    {
        $request->validate([
            'report_type' => 'required|string',
            'data_source' => 'required|string',
            'columns' => 'required|array',
            'filters' => 'sometimes|array',
        ]);

        $data = $this->fetchReportData($request->all());
        $columns = $request->columns;
        $reportType = $request->report_type;

        if ($format === 'pdf') {
            return $this->exportPdf($data, $columns, $reportType);
        } elseif ($format === 'excel') {
            return $this->exportExcel($data, $columns, $reportType);
        } elseif ($format === 'csv') {
            return $this->exportCsv($data, $columns, $reportType);
        }

        return back()->with('error', 'Invalid export format');
    }

    /**
     * Save report template
     */
    public function saveTemplate(Request $request)
    {
        $request->validate([
            'template_name' => 'required|string|max:255',
            'report_type' => 'required|string',
            'configuration' => 'required|array',
        ]);

        // Save to database or session
        $template = [
            'name' => $request->template_name,
            'type' => $request->report_type,
            'config' => $request->configuration,
            'created_at' => now(),
            'created_by' => auth()->id(),
        ];

        session()->push('report_templates', $template);

        return response()->json([
            'success' => true,
            'message' => 'Report template saved successfully',
            'template' => $template,
        ]);
    }

    /**
     * Get saved templates
     */
    public function templates()
    {
        $templates = session('report_templates', []);
        
        return response()->json([
            'success' => true,
            'templates' => $templates,
        ]);
    }

    /**
     * Schedule report
     */
    public function scheduleReport(Request $request)
    {
        $request->validate([
            'template_id' => 'required',
            'frequency' => 'required|in:daily,weekly,monthly',
            'recipients' => 'required|array',
            'recipients.*' => 'email',
            'export_format' => 'required|in:pdf,excel,csv',
        ]);

        // Store scheduled report configuration
        $scheduled = [
            'template_id' => $request->template_id,
            'frequency' => $request->frequency,
            'recipients' => $request->recipients,
            'format' => $request->export_format,
            'next_run' => $this->calculateNextRun($request->frequency),
            'created_at' => now(),
        ];

        session()->push('scheduled_reports', $scheduled);

        return response()->json([
            'success' => true,
            'message' => 'Report scheduled successfully',
            'scheduled' => $scheduled,
        ]);
    }

    /**
     * Fetch report data based on configuration
     */
    private function fetchReportData($config)
    {
        $dataSource = $config['data_source'];
        $columns = $config['columns'];
        $filters = $config['filters'] ?? [];
        
        // Build query based on data source
        $query = $this->buildQuery($dataSource);
        
        // Apply filters
        foreach ($filters as $filter) {
            if (isset($filter['field']) && isset($filter['operator']) && isset($filter['value'])) {
                $this->applyFilter($query, $filter);
            }
        }
        
        // Apply grouping
        if (isset($config['group_by'])) {
            $query->groupBy($config['group_by']);
        }
        
        // Apply ordering
        $orderBy = $config['order_by'] ?? 'id';
        $orderDirection = $config['order_direction'] ?? 'asc';
        $query->orderBy($orderBy, $orderDirection);
        
        // Get results
        $results = $query->get();
        
        // Select only requested columns
        return $results->map(function($item) use ($columns) {
            $data = [];
            foreach ($columns as $column) {
                $data[$column] = $item->$column ?? null;
            }
            return $data;
        })->toArray();
    }

    /**
     * Build base query for data source
     */
    private function buildQuery($dataSource)
    {
        switch ($dataSource) {
            case 'students':
                return Student::with(['class', 'section']);
            case 'teachers':
                return Teacher::query();
            case 'attendance':
                return Attendance::with(['student', 'teacher']);
            case 'fees':
                return FeeCollection::with(['student']);
            case 'exams':
                return Exam::with(['class', 'section', 'subject']);
            case 'results':
                return Result::with(['student', 'exam', 'subject']);
            default:
                return Student::query();
        }
    }

    /**
     * Apply filter to query
     */
    private function applyFilter($query, $filter)
    {
        $field = $filter['field'];
        $operator = $filter['operator'];
        $value = $filter['value'];

        switch ($operator) {
            case 'equals':
                $query->where($field, '=', $value);
                break;
            case 'not_equals':
                $query->where($field, '!=', $value);
                break;
            case 'contains':
                $query->where($field, 'like', '%' . $value . '%');
                break;
            case 'starts_with':
                $query->where($field, 'like', $value . '%');
                break;
            case 'ends_with':
                $query->where($field, 'like', '%' . $value);
                break;
            case 'greater_than':
                $query->where($field, '>', $value);
                break;
            case 'less_than':
                $query->where($field, '<', $value);
                break;
            case 'between':
                if (is_array($value) && count($value) === 2) {
                    $query->whereBetween($field, $value);
                }
                break;
            case 'in':
                if (is_array($value)) {
                    $query->whereIn($field, $value);
                }
                break;
        }
    }

    /**
     * Export to PDF
     */
    private function exportPdf($data, $columns, $reportType)
    {
        $pdf = Pdf::loadView('admin.report-builder.export-pdf', [
            'data' => $data,
            'columns' => $columns,
            'reportType' => $reportType,
            'generatedAt' => now()->format('Y-m-d H:i:s'),
        ]);

        return $pdf->download('custom-report-' . now()->format('Y-m-d') . '.pdf');
    }

    /**
     * Export to Excel
     */
    private function exportExcel($data, $columns, $reportType)
    {
        return Excel::download(
            new CustomReportExport($data, $columns, $reportType),
            'custom-report-' . now()->format('Y-m-d') . '.xlsx'
        );
    }

    /**
     * Export to CSV
     */
    private function exportCsv($data, $columns, $reportType)
    {
        $filename = 'custom-report-' . now()->format('Y-m-d') . '.csv';
        
        $handle = fopen('php://temp', 'r+');
        
        // Add headers
        fputcsv($handle, $columns);
        
        // Add data
        foreach ($data as $row) {
            fputcsv($handle, array_values($row));
        }
        
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);
        
        return response($csv)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }

    /**
     * Calculate next run time for scheduled reports
     */
    private function calculateNextRun($frequency)
    {
        switch ($frequency) {
            case 'daily':
                return Carbon::tomorrow()->setTime(8, 0);
            case 'weekly':
                return Carbon::now()->next('Monday')->setTime(8, 0);
            case 'monthly':
                return Carbon::now()->addMonth()->startOfMonth()->setTime(8, 0);
            default:
                return Carbon::tomorrow()->setTime(8, 0);
        }
    }
}

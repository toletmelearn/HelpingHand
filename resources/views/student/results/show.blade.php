@extends('layouts.admin')

@section('title', 'Result Details')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">Result Details</h4>
                    <a href="{{ route('student.results.index') }}" class="btn btn-sm btn-secondary">Back to My Results</a>
                </div>
                <div class="card-body">
                    <table class="table table-bordered">
                        <tbody>
                            <tr>
                                <th style="width: 35%;">Exam</th>
                                <td>{{ $result->exam->name ?? 'N/A' }}</td>
                            </tr>
                            <tr>
                                <th>Subject</th>
                                <td>{{ $result->subject }}</td>
                            </tr>
                            <tr>
                                <th>Marks Obtained</th>
                                <td>{{ $result->marks_obtained }}</td>
                            </tr>
                            <tr>
                                <th>Total Marks</th>
                                <td>{{ $result->total_marks }}</td>
                            </tr>
                            <tr>
                                <th>Percentage</th>
                                <td>{{ $result->percentage }}%</td>
                            </tr>
                            <tr>
                                <th>Grade</th>
                                <td>{{ $result->grade }}</td>
                            </tr>
                            <tr>
                                <th>Status</th>
                                <td>
                                    <span class="badge bg-{{ $result->result_status == 'pass' ? 'success' : 'danger' }}">
                                        {{ ucfirst($result->result_status) }}
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <th>Term</th>
                                <td>{{ $result->term }}</td>
                            </tr>
                            <tr>
                                <th>Academic Year</th>
                                <td>{{ $result->academic_year }}</td>
                            </tr>
                            @if($result->remarks)
                            <tr>
                                <th>Remarks</th>
                                <td>{{ $result->remarks }}</td>
                            </tr>
                            @endif
                        </tbody>
                    </table>

                    <a href="{{ route('student.results.generate-pdf', $result) }}" class="btn btn-primary" target="_blank">Print / PDF</a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

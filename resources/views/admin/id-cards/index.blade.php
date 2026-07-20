@extends('layouts.admin')

@section('title', 'ID Cards')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-flex align-items-center justify-content-between">
                <h4 class="mb-0">ID Cards</h4>
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item active">ID Cards</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">ID Card Management</h5>
                    <a href="{{ route('admin.id-cards.create') }}" class="btn btn-primary">
                        <i class="fas fa-plus me-1"></i> Generate ID Card
                    </a>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th>Card Number</th>
                                    <th>Student</th>
                                    <th>Class</th>
                                    <th>Issue Date</th>
                                    <th>Expiry Date</th>
                                    <th>Status</th>
                                    <th>Printed By</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($idCards as $card)
                                <tr>
                                    <td>{{ $card->card_number }}</td>
                                    <td>{{ $card->student->name ?? 'N/A' }}</td>
                                    <td>{{ $card->student->schoolClass->name ?? 'N/A' }}</td>
                                    <td>{{ $card->issue_date ? $card->issue_date->format('d-m-Y') : 'N/A' }}</td>
                                    <td>{{ $card->expiry_date ? $card->expiry_date->format('d-m-Y') : 'N/A' }}</td>
                                    <td>
                                        <span class="badge 
                                            @if($card->status == 'active') bg-success 
                                            @elseif($card->status == 'expired') bg-danger 
                                            @elseif($card->status == 'suspended') bg-warning 
                                            @else bg-secondary @endif">
                                            {{ ucfirst($card->status) }}
                                        </span>
                                    </td>
                                    <td>{{ $card->printedBy->name ?? 'N/A' }}</td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="{{ route('admin.id-cards.show', $card->id) }}" 
                                               class="btn btn-sm btn-info">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="{{ route('admin.id-cards.edit', $card->id) }}" 
                                               class="btn btn-sm btn-warning">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="{{ route('admin.id-cards.print', $card->id) }}" 
                                               class="btn btn-sm btn-success" target="_blank">
                                                <i class="fas fa-print"></i>
                                            </a>
                                            <form action="{{ route('admin.id-cards.destroy', $card->id) }}" 
                                                  method="POST" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-danger" 
                                                        onclick="return confirm('Are you sure you want to delete this ID card?')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="8" class="text-center">No ID cards found.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex justify-content-center">
                        {{ $idCards->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
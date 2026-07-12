@extends('layouts.parent')

@section('content')
<div class="container py-4">
    <div class="row">
        <div class="col-md-12 mb-4">
            <h1 class="h3 mb-2 text-gray-800">Online Fee Payments</h1>
            <p class="mb-4">Review pending tuition installments for <strong>{{ $student->name }}</strong> and checkout securely online.</p>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Pending Tuition Installments</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <th>Fee Structure</th>
                                    <th>Total Charge</th>
                                    <th>Amount Paid</th>
                                    <th>Remaining Balance</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($pendingFees as $fee)
                                    <tr>
                                        <td><strong>{{ $fee['name'] }}</strong></td>
                                        <td>₹{{ number_format($fee['total_amount'], 2) }}</td>
                                        <td>₹{{ number_format($fee['paid_amount'], 2) }}</td>
                                        <td><strong class="text-danger">₹{{ number_format($fee['balance'], 2) }}</strong></td>
                                        <td>
                                            <!-- Online payment isn't wired up yet; this records intent and shows the parent an honest status instead of a fake checkout. -->
                                            <form action="{{ route('parent.payments.stripe-checkout') }}" method="POST">
                                                @csrf
                                                <input type="hidden" name="fee_structure_id" value="{{ $fee['fee_structure_id'] }}">
                                                <input type="hidden" name="amount" value="{{ $fee['balance'] }}">
                                                <button type="submit" class="btn btn-outline-secondary btn-sm px-3">
                                                    Online Payment Options
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center py-4 text-success font-weight-bold">
                                            All tuition fee installments have been completely paid! No balance outstanding.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

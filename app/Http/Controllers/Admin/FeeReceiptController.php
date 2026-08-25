<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\FeeCollection;

class FeeReceiptController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        // Fees V1: this controller had no authorization at all -- not
        // even 'auth' -- so any authenticated account of any role could
        // download any student's fee receipt PDF (amounts, payment mode,
        // transaction id) by guessing/iterating {id}. Matches
        // Admin\FeeCollectionController's own view-fees gate on its
        // sibling receipt actions.
        $this->middleware('permission:view-fees');
    }

    public function downloadPdf($id)
    {
        $feeCollection = FeeCollection::withTrashed()->with([
            'student.schoolClass',
            'feeStructure',
            'feeCollectionItems.feeType',
            'collectedBy'
        ])->findOrFail($id);

        $pdf = \PDF::loadView('admin.fees.receipt-pdf', compact('feeCollection'));
        return $pdf->download('receipt-' . $feeCollection->receipt_no . '.pdf');
    }
}
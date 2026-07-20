<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\FeeCollection;

class FeeReceiptController extends Controller
{
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
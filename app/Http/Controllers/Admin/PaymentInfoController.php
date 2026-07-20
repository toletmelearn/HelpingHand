<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminConfiguration;
use App\Services\UpiQrService;

class PaymentInfoController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:view-payment-info');
    }

    /**
     * Generic, non-amount QR + bank details for walk-ins/notice board --
     * distinct from the per-student dynamic QR on the parent portal, which
     * carries a live am= (outstanding balance).
     */
    public function show()
    {
        $vpa = AdminConfiguration::get('fee', 'upi_vpa', '');
        $schoolName = AdminConfiguration::get('general', 'school_name', 'Helping Hand School');

        $qr = null;
        if ($vpa) {
            $qr = UpiQrService::generate(
                $vpa,
                $schoolName,
                null,
                'Fee Payment',
                'SCHOOL-GENERIC'
            );
        }

        $bank = [
            'account_name' => AdminConfiguration::get('fee', 'bank_account_name', ''),
            'account_number' => AdminConfiguration::get('fee', 'bank_account_number', ''),
            'ifsc' => AdminConfiguration::get('fee', 'bank_ifsc', ''),
            'bank_name' => AdminConfiguration::get('fee', 'bank_name', ''),
        ];

        return view('admin.payment-info.show', compact('vpa', 'qr', 'bank', 'schoolName'));
    }
}

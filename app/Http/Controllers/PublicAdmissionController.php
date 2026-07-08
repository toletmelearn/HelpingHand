<?php

namespace App\Http\Controllers;

use App\Models\AdmissionEnquiry;
use App\Services\AdmissionNotificationService;
use App\Services\FrontOffice\FrontOfficeService;
use Illuminate\Http\Request;

class PublicAdmissionController extends Controller
{
    protected FrontOfficeService $service;
    protected AdmissionNotificationService $notificationService;

    public function __construct(FrontOfficeService $service, AdmissionNotificationService $notificationService)
    {
        $this->service = $service;
        $this->notificationService = $notificationService;
    }

    public function showForm()
    {
        return view('public.admissions.apply');
    }

    public function submit(Request $request)
    {
        $validated = $request->validate([
            'candidate_name' => 'required|string|max:255',
            'parent_name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'email' => 'nullable|email|max:255',
        ]);

        // A guest never sees internal record details or a force-create prompt;
        // if we already have this lead, just confirm receipt again.
        $duplicate = $this->service->checkDuplicateEnquiry($validated);

        if (!$duplicate) {
            $enquiry = AdmissionEnquiry::create(array_merge($validated, [
                'status' => 'new',
            ]));

            $this->notificationService->notifyStage($enquiry, 'admission_enquiry_received');
        }

        return redirect()->route('admissions.apply')->with('success',
            'Thank you! We have received your admission enquiry and our team will be in touch shortly.'
        );
    }
}

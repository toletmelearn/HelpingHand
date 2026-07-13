<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdmissionEnquiry;
use App\Models\AdmissionEnquiryDocument;
use App\Models\AdmissionEnquiryPayment;
use App\Models\FeeType;
use App\Models\User;
use App\Services\AdmissionNotificationService;
use App\Services\FrontOffice\FrontOfficeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Carbon\Carbon;

class AdmissionEnquiryController extends Controller
{
    protected FrontOfficeService $service;
    protected AdmissionNotificationService $notificationService;

    public function __construct(FrontOfficeService $service, AdmissionNotificationService $notificationService)
    {
        $this->service = $service;
        $this->notificationService = $notificationService;
    }

    public function index(Request $request)
    {
        $query = AdmissionEnquiry::with('counsellor');

        $user = auth()->user();
        $isAdminOrReceptionist = $user->hasRole('admin') || $user->hasRole('super-admin') || $user->hasRole('receptionist');
        
        if (!$isAdminOrReceptionist) {
            $query->where('counsellor_id', $user->id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('candidate_name', 'like', "%{$search}%")
                  ->orWhere('parent_name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('counsellor_id') && $isAdminOrReceptionist) {
            $query->where('counsellor_id', $request->counsellor_id);
        }

        $enquiries = $query->latest()->paginate(15)->withQueryString();
        $counsellors = User::whereHas('roles', function($q) {
            $q->whereIn('name', ['admin', 'super-admin', 'teacher', 'clerk']);
        })->orderBy('name')->get();

        return view('admin.front-office.enquiries.index', compact('enquiries', 'counsellors'));
    }

    public function create()
    {
        $counsellors = User::whereHas('roles', function($q) {
            $q->whereIn('name', ['admin', 'super-admin', 'teacher', 'clerk']);
        })->orderBy('name')->get();
        
        return view('admin.front-office.enquiries.create', compact('counsellors'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'candidate_name' => 'required|string|max:255',
            'parent_name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'email' => 'nullable|email|max:255',
            'aadhaar_number' => 'nullable|string|max:20',
            'counsellor_id' => 'nullable|exists:users,id',
            'visit_scheduled_at' => 'nullable|date',
            'follow_up_date' => 'nullable|date|after_or_equal:today',
            'status' => 'required|in:new,interested,follow_up,documents_pending,confirmed,rejected,closed',
            'remarks' => 'nullable|string',
            'interview_date' => 'nullable|date',
            'interview_score' => 'nullable|numeric|min:0|max:100',
            'entrance_score' => 'nullable|numeric|min:0|max:100',
        ]);

        // Duplicate check
        $duplicate = $this->service->checkDuplicateEnquiry($validated);
        if ($duplicate && !$request->has('force_create')) {
            return back()->withInput()->with('duplicate_found', [
                'id' => $duplicate->id,
                'name' => $duplicate->candidate_name,
                'parent' => $duplicate->parent_name,
                'phone' => $duplicate->phone,
            ]);
        }

        $enquiry = AdmissionEnquiry::create($validated);

        activity()->causedBy(auth()->user())->performedOn($enquiry)
            ->withProperties(['status' => $enquiry->status])
            ->log('admission_enquiry_created');

        $this->notificationService->notifyStage($enquiry, 'admission_enquiry_received');

        return redirect()->route('admin.front-office.enquiries.index')->with('success', 'Admission enquiry registered successfully.');
    }

    public function show($id)
    {
        $enquiry = AdmissionEnquiry::with(['counsellor', 'documents.verifier', 'payments.collector', 'payments.feeType'])->findOrFail($id);
        $this->checkAccess($enquiry);
        $feeTypes = FeeType::where('status', 'active')->orderBy('name')->get();
        return view('admin.front-office.enquiries.show', compact('enquiry', 'feeTypes'));
    }

    public function edit($id)
    {
        $enquiry = AdmissionEnquiry::with('documents.verifier')->findOrFail($id);
        $this->checkAccess($enquiry);
        $counsellors = User::whereHas('roles', function($q) {
            $q->whereIn('name', ['admin', 'super-admin', 'teacher', 'clerk']);
        })->orderBy('name')->get();

        return view('admin.front-office.enquiries.edit', compact('enquiry', 'counsellors'));
    }

    public function update(Request $request, $id)
    {
        $enquiry = AdmissionEnquiry::findOrFail($id);
        $this->checkAccess($enquiry);
        $previousStatus = $enquiry->status;

        $validated = $request->validate([
            'candidate_name' => 'required|string|max:255',
            'parent_name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'email' => 'nullable|email|max:255',
            'aadhaar_number' => 'nullable|string|max:20',
            'counsellor_id' => 'nullable|exists:users,id',
            'visit_scheduled_at' => 'nullable|date',
            'follow_up_date' => 'nullable|date',
            'status' => 'required|in:new,interested,follow_up,documents_pending,selected,confirmed,rejected,closed,admitted',
            'remarks' => 'nullable|string',
            'interview_date' => 'nullable|date',
            'interview_score' => 'nullable|numeric|min:0|max:100',
            'entrance_score' => 'nullable|numeric|min:0|max:100',
        ]);

        // Duplicate check (ignoring current id)
        $duplicate = $this->service->checkDuplicateEnquiry($validated, $id);
        if ($duplicate && !$request->has('force_create')) {
            return back()->withInput()->with('duplicate_found', [
                'id' => $duplicate->id,
                'name' => $duplicate->candidate_name,
                'parent' => $duplicate->parent_name,
                'phone' => $duplicate->phone,
            ]);
        }

        $enquiry->update($validated);
        $this->handleStatusChangeIfApplicable($enquiry, $previousStatus);

        return redirect()->route('admin.front-office.enquiries.index')->with('success', 'Admission enquiry updated successfully.');
    }

    public function destroy($id)
    {
        $enquiry = AdmissionEnquiry::findOrFail($id);
        $this->checkAccess($enquiry);

        activity()->causedBy(auth()->user())->performedOn($enquiry)
            ->withProperties(['candidate_name' => $enquiry->candidate_name, 'status' => $enquiry->status])
            ->log('admission_enquiry_deleted');

        $enquiry->delete();

        return redirect()->route('admin.front-office.enquiries.index')->with('success', 'Admission enquiry deleted successfully.');
    }

    public function storeFollowUp(Request $request, $id)
    {
        $enquiry = AdmissionEnquiry::findOrFail($id);
        $this->checkAccess($enquiry);
        $previousStatus = $enquiry->status;

        $validated = $request->validate([
            'follow_up_date' => 'required|date|after_or_equal:today',
            'follow_up_notes' => 'required|string|max:2000',
            'status' => 'required|in:interested,follow_up,documents_pending,closed,rejected',
        ]);

        $enquiry->update([
            'follow_up_date' => $validated['follow_up_date'],
            'follow_up_notes' => $validated['follow_up_notes'],
            'status' => $validated['status'],
        ]);
        $this->handleStatusChangeIfApplicable($enquiry, $previousStatus);

        return back()->with('success', 'Follow-up registered successfully.');
    }

    public function assignCounsellor(Request $request, $id)
    {
        $enquiry = AdmissionEnquiry::findOrFail($id);
        $this->checkAccess($enquiry);
        $previousCounsellorId = $enquiry->counsellor_id;

        $validated = $request->validate([
            'counsellor_id' => 'required|exists:users,id',
        ]);

        $enquiry->update([
            'counsellor_id' => $validated['counsellor_id'],
        ]);

        activity()->causedBy(auth()->user())->performedOn($enquiry)
            ->withProperties(['from_counsellor_id' => $previousCounsellorId, 'to_counsellor_id' => $enquiry->counsellor_id])
            ->log('admission_counsellor_assigned');

        return back()->with('success', 'Counsellor assigned successfully.');
    }

    public function uploadDocuments(Request $request, $id)
    {
        $enquiry = AdmissionEnquiry::findOrFail($id);
        $this->checkAccess($enquiry);

        $request->validate([
            'documents' => 'required|array|min:1',
            'documents.*' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120', // max 5MB
        ]);

        foreach ($request->file('documents') as $file) {
            $originalName = $file->getClientOriginalName();
            $extension = $file->getClientOriginalExtension();
            $size = $file->getSize();
            $mimeType = $file->getMimeType();

            $filename = Str::random(40) . '.' . $extension;
            $path = $file->storeAs('admission-documents/' . $enquiry->id, $filename, 'public');

            $document = AdmissionEnquiryDocument::create([
                'admission_enquiry_id' => $enquiry->id,
                'document_type' => $this->determineDocumentType($originalName),
                'document_path' => $path,
                'original_filename' => $originalName,
                'file_size' => $size,
                'file_mime_type' => $mimeType,
            ]);

            activity()->causedBy(auth()->user())->performedOn($document)
                ->withProperties(['admission_enquiry_id' => $enquiry->id, 'document_type' => $document->document_type, 'original_filename' => $originalName])
                ->log('admission_document_uploaded');
        }

        return redirect()->back()->with('success', 'Documents uploaded successfully!');
    }

    public function verifyDocument(Request $request, $documentId)
    {
        $document = AdmissionEnquiryDocument::findOrFail($documentId);
        $this->checkAccess($document->enquiry);

        $request->validate([
            'is_verified' => 'required|boolean',
            'verification_notes' => 'nullable|string|max:1000',
        ]);

        $document->update([
            'is_verified' => $request->is_verified,
            'verified_by' => Auth::id(),
            'verified_at' => now(),
            'verification_notes' => $request->verification_notes,
        ]);

        activity()->causedBy(auth()->user())->performedOn($document)
            ->withProperties(['is_verified' => $document->is_verified, 'verification_notes' => $document->verification_notes])
            ->log('admission_document_verified');

        return redirect()->back()->with('success', 'Document verification updated successfully!');
    }

    public function recordPayment(Request $request, $id)
    {
        $enquiry = AdmissionEnquiry::findOrFail($id);
        $this->checkAccess($enquiry);

        $validated = $request->validate([
            'fee_type_id' => 'nullable|exists:fee_types,id',
            'amount' => 'required|numeric|min:0.01',
            'payment_mode' => 'required|in:' . implode(',', array_keys(AdmissionEnquiryPayment::PAYMENT_MODES)),
            'receipt_no' => 'nullable|string|max:50|unique:admission_enquiry_payments,receipt_no',
            'paid_at' => 'required|date',
            'remarks' => 'nullable|string|max:1000',
        ]);

        $receiptNo = $validated['receipt_no'] ?? null;
        if (!$receiptNo) {
            $year = date('Y');
            do {
                $receiptNo = 'ADMFEE-' . $year . '-' . mt_rand(1000, 9999);
            } while (AdmissionEnquiryPayment::where('receipt_no', $receiptNo)->exists());
        }

        $feeTypeId = $validated['fee_type_id'] ?? FeeType::where('name', 'Admission')->value('id');

        $payment = AdmissionEnquiryPayment::create([
            'admission_enquiry_id' => $enquiry->id,
            'fee_type_id' => $feeTypeId,
            'amount' => $validated['amount'],
            'payment_mode' => $validated['payment_mode'],
            'receipt_no' => $receiptNo,
            'paid_at' => $validated['paid_at'],
            'collected_by' => auth()->id(),
            'remarks' => $validated['remarks'] ?? null,
        ]);

        activity()->causedBy(auth()->user())->performedOn($payment)
            ->withProperties([
                'admission_enquiry_id' => $enquiry->id,
                'amount' => (string) $payment->amount,
                'payment_mode' => $payment->payment_mode,
                'receipt_no' => $payment->receipt_no,
            ])
            ->log('admission_fee_recorded');

        $this->notificationService->notifyStage($enquiry, 'admission_fee_received', [
            'amount' => number_format((float) $payment->amount, 2),
            'payment_mode' => AdmissionEnquiryPayment::PAYMENT_MODES[$payment->payment_mode] ?? $payment->payment_mode,
            'receipt_no' => $payment->receipt_no,
        ]);

        return redirect()->back()->with('success', 'Payment recorded successfully. Receipt No: ' . $receiptNo);
    }

    private function determineDocumentType(string $filename): string
    {
        $lowerFilename = strtolower($filename);

        if (str_contains($lowerFilename, 'birth') || str_contains($lowerFilename, 'dob')) {
            return 'birth_certificate';
        } elseif (str_contains($lowerFilename, 'aadhaar') || str_contains($lowerFilename, 'aadhar')) {
            return 'aadhaar_card';
        } elseif (str_contains($lowerFilename, 'transfer') || str_contains($lowerFilename, 'tc')) {
            return 'transfer_certificate';
        } elseif (str_contains($lowerFilename, 'photo') || str_contains($lowerFilename, 'pic')) {
            return 'photo';
        } elseif (str_contains($lowerFilename, 'mark') || str_contains($lowerFilename, 'certificate')) {
            return 'academic_certificate';
        }

        return 'other';
    }

    public function checkDuplicate(Request $request)
    {
        $duplicate = $this->service->checkDuplicateEnquiry($request->only(['phone', 'email', 'aadhaar_number']), $request->id);
        
        return response()->json([
            'duplicate' => !is_null($duplicate),
            'record' => $duplicate ? [
                'id' => $duplicate->id,
                'candidate_name' => $duplicate->candidate_name,
                'parent_name' => $duplicate->parent_name,
            ] : null
        ]);
    }

    private function checkAccess($enquiry)
    {
        $user = auth()->user();
        $isAdminOrReceptionist = $user->hasRole('admin') || $user->hasRole('super-admin') || $user->hasRole('receptionist');

        if (!$isAdminOrReceptionist && $enquiry->counsellor_id !== $user->id) {
            abort(403, 'Unauthorized action.');
        }
    }

    private function handleStatusChangeIfApplicable(AdmissionEnquiry $enquiry, string $previousStatus): void
    {
        if ($enquiry->status === $previousStatus) {
            return;
        }

        activity()->causedBy(auth()->user())->performedOn($enquiry)
            ->withProperties(['from' => $previousStatus, 'to' => $enquiry->status])
            ->log('admission_status_changed');

        $eventType = match ($enquiry->status) {
            'selected', 'confirmed' => 'admission_confirmed',
            'rejected', 'closed' => 'admission_rejected',
            default => null,
        };

        if ($eventType) {
            $this->notificationService->notifyStage($enquiry, $eventType);
        }
    }
}

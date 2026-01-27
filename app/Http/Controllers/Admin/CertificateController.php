<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class CertificateController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }
    
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Certificate::with(['creator', 'approver', 'recipient']);
        
        // Filter by certificate type
        if ($request->filled('type')) {
            $query->where('certificate_type', $request->type);
        }
        
        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        
        // Search by serial number or recipient name
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('serial_number', 'LIKE', "%$search%")
                  ->orWhereHasMorph('recipient', [Student::class, Teacher::class], function($q) use ($search) {
                      $q->where('name', 'LIKE', "%$search%");
                  });
            });
        }
        
        $certificates = $query->latest()->paginate(20);
        
        return view('admin.certificates.index', compact('certificates'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $templates = CertificateTemplate::where('is_active', true)->get();
        $students = Student::all();
        $teachers = Teacher::all();
        
        return view('admin.certificates.create', compact('templates', 'students', 'teachers'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'certificate_type' => 'required|in:tc,bonafide,character,experience',
            'recipient_type' => 'required|in:App\\Models\\Student,App\\Models\\Teacher',
            'recipient_id' => 'required|exists:students,id,teachers,id',
            'template_id' => 'nullable|exists:certificate_templates,id',
            'content_data' => 'required|array',
            'content_data.*' => 'string',
        ]);
        
        DB::beginTransaction();
        
        try {
            $serialNumber = 'CERT-' . date('Y') . '-' . str_pad(Certificate::count() + 1, 6, '0', STR_PAD_LEFT);
            
            $certificate = Certificate::create([
                'certificate_type' => $request->certificate_type,
                'serial_number' => $serialNumber,
                'recipient_id' => $request->recipient_id,
                'recipient_type' => $request->recipient_type,
                'content_data' => $request->content_data,
                'status' => 'draft',
                'created_by' => Auth::id(),
            ]);
            
            DB::commit();
            
            return redirect()->route('admin.certificates.show', $certificate->id)
                             ->with('success', 'Certificate created successfully.');
        } catch (\Exception $e) {
            DB::rollback();
            return back()->withErrors(['error' => 'Failed to create certificate: ' . $e->getMessage()]);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Certificate $certificate)
    {
        $certificate->load(['creator', 'approver', 'recipient']);
        
        return view('admin.certificates.show', compact('certificate'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Certificate $certificate)
    {
        $certificate->load(['recipient']);
        $templates = CertificateTemplate::where('is_active', true)->get();
        
        return view('admin.certificates.edit', compact('certificate', 'templates'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Certificate $certificate)
    {
        if (!$certificate->canBeModified()) {
            return back()->withErrors(['error' => 'Certificate cannot be modified in its current status.']);
        }
        
        $request->validate([
            'content_data' => 'required|array',
            'content_data.*' => 'string',
        ]);
        
        $certificate->update([
            'content_data' => $request->content_data,
        ]);
        
        return redirect()->route('admin.certificates.show', $certificate->id)
                         ->with('success', 'Certificate updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Certificate $certificate)
    {
        if (!$certificate->canBeModified()) {
            return back()->withErrors(['error' => 'Certificate cannot be deleted in its current status.']);
        }
        
        $certificate->delete();
        
        return redirect()->route('admin.certificates.index')
                         ->with('success', 'Certificate deleted successfully.');
    }
    
    /**
     * Approve the certificate
     */
    public function approve(Request $request, Certificate $certificate)
    {
        if (!$certificate->canBeApproved()) {
            return back()->withErrors(['error' => 'Certificate cannot be approved in its current state.']);
        }
        
        $certificate->approve(Auth::id());
        
        return redirect()->back()->with('success', 'Certificate approved successfully.');
    }
    
    /**
     * Publish the certificate
     */
    public function publish(Certificate $certificate)
    {
        if (!$certificate->canBePublished()) {
            return back()->withErrors(['error' => 'Certificate cannot be published in its current state.']);
        }
        
        $certificate->publish();
        
        return redirect()->back()->with('success', 'Certificate published successfully.');
    }
    
    /**
     * Lock the certificate
     */
    public function lock(Certificate $certificate)
    {
        if (!in_array($certificate->status, ['published'])) {
            return back()->withErrors(['error' => 'Certificate cannot be locked in its current state.']);
        }
        
        $certificate->lock();
        
        return redirect()->back()->with('success', 'Certificate locked successfully.');
    }
    
    /**
     * Revoke the certificate
     */
    public function revoke(Request $request, Certificate $certificate)
    {
        if (!$certificate->canBeRevoked()) {
            return back()->withErrors(['error' => 'Certificate cannot be revoked in its current state.']);
        }
        
        $request->validate([
            'reason' => 'required|string|max:500',
        ]);
        
        $certificate->revoke($request->reason, Auth::id());
        
        return redirect()->back()->with('success', 'Certificate revoked successfully.');
    }
    
    /**
     * Generate certificate preview
     */
    public function preview(Certificate $certificate)
    {
        $certificate->load(['recipient']);
        
        $template = CertificateTemplate::getDefaultTemplate($certificate->certificate_type);
        if (!$template) {
            return back()->withErrors(['error' => 'No template found for this certificate type.']);
        }
        
        $content = $this->renderCertificateContent($certificate, $template);
        
        return view('admin.certificates.preview', compact('certificate', 'content'));
    }
    
    private function renderCertificateContent(Certificate $certificate, CertificateTemplate $template)
    {
        $content = $template->template_content;
        $data = $certificate->content_data;
        
        // Replace placeholders in template
        foreach ($data as $key => $value) {
            $content = str_replace('{{' . $key . '}}', $value, $content);
        }
        
        // Replace recipient info
        $recipient = $certificate->recipient;
        $content = str_replace('{{recipient.name}}', $recipient->name ?? '', $content);
        $content = str_replace('{{recipient.id}}', $recipient->id ?? '', $content);
        
        // Replace certificate info
        $content = str_replace('{{certificate.serial_number}}', $certificate->serial_number, $content);
        $content = str_replace('{{certificate.type}}', strtoupper($certificate->certificate_type), $content);
        $content = str_replace('{{certificate.created_at}}', $certificate->created_at->format('d/m/Y'), $content);
        
        return $content;
    }
}

    /**
     * Display a listing of the exam papers.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', ExamPaper::class);
        $query = ExamPaper::with(['uploadedBy', 'approvedBy']);

        // Apply filters
        if ($request->filled('subject')) {
            $query->where('subject', $request->subject);
        }

        if ($request->filled('class_section')) {
            $query->where('class_section', $request->class_section);
        }

        if ($request->filled('exam_type')) {
            $query->where('exam_type', $request->exam_type);
        }

        if ($request->filled('paper_type')) {
            $query->where('paper_type', $request->paper_type);
        }

        if ($request->filled('academic_year')) {
            $query->where('academic_year', $request->academic_year);
        }

        if ($request->filled('is_published')) {
            $query->where('is_published', $request->is_published);
        }

        $examPapers = $query->orderBy('exam_date', 'desc')
                           ->orderBy('created_at', 'desc')
                           ->paginate(15);

        // Get unique values for filters
        $subjects = ExamPaper::distinct()->pluck('subject')->filter();
        $classSections = ExamPaper::distinct()->pluck('class_section')->filter()->sortBy('class_section');
        $examTypes = ExamPaper::distinct()->pluck('exam_type')->filter();
        $paperTypes = ExamPaper::distinct()->pluck('paper_type')->filter();
        $academicYears = ExamPaper::distinct()->pluck('academic_year')->filter();

        return view('exam-papers.index', compact(
            'examPapers', 'subjects', 'classSections', 'examTypes', 'paperTypes', 'academicYears'
        ));
    }

    /**
     * Show the form for creating a new exam paper.
     */
    public function create()
    {
        $this->authorize('create', ExamPaper::class);
        $subjects = Teacher::distinct()->pluck('subject_specialization')->filter()->sortBy('subject_specialization');
        $classSections = Student::distinct()->pluck('class')->filter()->sortBy('class');
        $examTypes = [
            ExamPaper::EXAM_TYPE_MIDTERM,
            ExamPaper::EXAM_TYPE_FINAL,
            ExamPaper::EXAM_TYPE_UNIT_TEST,
            ExamPaper::EXAM_TYPE_QUIZ,
            ExamPaper::EXAM_TYPE_ASSIGNMENT,
            ExamPaper::EXAM_TYPE_PROJECT,
            ExamPaper::EXAM_TYPE_PRACTICAL
        ];
        $paperTypes = [
            ExamPaper::PAPER_TYPE_QUESTION,
            ExamPaper::PAPER_TYPE_ANSWER_KEY,
            ExamPaper::PAPER_TYPE_SOLUTION,
            ExamPaper::PAPER_TYPE_SYLLABUS,
            ExamPaper::PAPER_TYPE_SAMPLE,
            ExamPaper::PAPER_TYPE_PREVIOUS_YEAR
        ];
        $accessLevels = [
            ExamPaper::ACCESS_PUBLIC,
            ExamPaper::ACCESS_TEACHERS_ONLY,
            ExamPaper::ACCESS_STUDENTS_ONLY,
            ExamPaper::ACCESS_PRIVATE
        ];
        $academicYears = [
            date('Y') . '-' . (date('Y') + 1),
            (date('Y') - 1) . '-' . date('Y'),
            (date('Y') + 1) . '-' . (date('Y') + 2)
        ];

        return view('exam-papers.create', compact(
            'subjects', 'classSections', 'examTypes', 'paperTypes', 'accessLevels', 'academicYears'
        ));
    }

    /**
     * Store a newly created exam paper in storage.
     */
    public function store(Request $request)
    {
        $this->authorize('create', ExamPaper::class);
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'subject' => 'required|string|max:100',
            'class_section' => 'required|string|max:50',
            'exam_type' => 'required|in:' . implode(',', [
                ExamPaper::EXAM_TYPE_MIDTERM,
                ExamPaper::EXAM_TYPE_FINAL,
                ExamPaper::EXAM_TYPE_UNIT_TEST,
                ExamPaper::EXAM_TYPE_QUIZ,
                ExamPaper::EXAM_TYPE_ASSIGNMENT,
                ExamPaper::EXAM_TYPE_PROJECT,
                ExamPaper::EXAM_TYPE_PRACTICAL
            ]),
            'paper_type' => 'required|in:' . implode(',', [
                ExamPaper::PAPER_TYPE_QUESTION,
                ExamPaper::PAPER_TYPE_ANSWER_KEY,
                ExamPaper::PAPER_TYPE_SOLUTION,
                ExamPaper::PAPER_TYPE_SYLLABUS,
                ExamPaper::PAPER_TYPE_SAMPLE,
                ExamPaper::PAPER_TYPE_PREVIOUS_YEAR
            ]),
            'file' => 'required|file|mimes:pdf,doc,docx,txt,jpeg,jpg,png|max:10240', // 10MB max
            'academic_year' => 'nullable|string|max:20',
            'semester' => 'nullable|string|max:20',
            'duration_minutes' => 'nullable|integer|min:1',
            'total_marks' => 'nullable|integer|min:1',
            'exam_date' => 'nullable|date',
            'exam_time' => 'nullable|date_format:H:i',
            'access_level' => 'required|in:' . implode(',', [
                ExamPaper::ACCESS_PUBLIC,
                ExamPaper::ACCESS_TEACHERS_ONLY,
                ExamPaper::ACCESS_STUDENTS_ONLY,
                ExamPaper::ACCESS_PRIVATE
            ]),
            'instructions' => 'nullable|string',
            'is_published' => 'boolean',
            'is_answer_key' => 'boolean',
            'password_protected' => 'boolean',
            'access_password' => 'nullable|required_if:password_protected,true|string|min:6'
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        // Handle file upload
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $originalName = $file->getClientOriginalName();
            $extension = $file->getClientOriginalExtension();
            $fileName = time() . '_' . uniqid() . '.' . $extension;
            $filePath = $file->storeAs('exam-papers', $fileName, 'public');
            $fileSize = $file->getSize();
        } else {
            return back()->withErrors(['file' => 'File is required'])->withInput();
        }

        $examPaper = new ExamPaper();
        $examPaper->title = $request->title;
        $examPaper->subject = $request->subject;
        $examPaper->class_section = $request->class_section;
        $examPaper->exam_type = $request->exam_type;
        $examPaper->paper_type = $request->paper_type;
        $examPaper->file_path = $filePath;
        $examPaper->file_name = $originalName;
        $examPaper->file_size = $fileSize;
        $examPaper->file_extension = $extension;
        $examPaper->uploaded_by = Auth::id(); // Current authenticated user
        $examPaper->academic_year = $request->academic_year;
        $examPaper->semester = $request->semester;
        $examPaper->duration_minutes = $request->duration_minutes;
        $examPaper->total_marks = $request->total_marks;
        $examPaper->instructions = $request->instructions;
        $examPaper->exam_date = $request->exam_date;
        $examPaper->exam_time = $request->exam_time;
        $examPaper->access_level = $request->access_level;
        $examPaper->is_published = $request->is_published ? true : false;
        $examPaper->is_answer_key = $request->is_answer_key ? true : false;
        $examPaper->password_protected = $request->password_protected ? true : false;
        $examPaper->access_password = $request->password_protected ? bcrypt($request->access_password) : null;
        $examPaper->created_by = Auth::id(); // Current authenticated user
        $examPaper->is_approved = true; // Auto-approve for demo purposes
        $examPaper->approved_by = Auth::id(); // Current authenticated user
        $examPaper->valid_from = $request->valid_from;
        $examPaper->valid_until = $request->valid_until;
        
        if ($request->filled('marks_distribution')) {
            $examPaper->marks_distribution = json_decode($request->marks_distribution, true);
        }

        $examPaper->save();

        return redirect()->route('exam-papers.index')
                         ->with('success', 'Exam paper uploaded successfully!');
    }

    /**
     * Display the specified exam paper.
     */
    public function show(ExamPaper $examPaper)
    {
        $this->authorize('view', $examPaper);
        $examPaper->load(['uploadedBy', 'approvedBy']);
        return view('exam-papers.show', compact('examPaper'));
    }

    /**
     * Show the form for editing the specified exam paper.
     */
    public function edit(ExamPaper $examPaper)
    {
        $this->authorize('update', $examPaper);
        $subjects = Teacher::distinct()->pluck('subject_specialization')->filter()->sortBy('subject_specialization');
        $classSections = Student::distinct()->pluck('class')->filter()->sortBy('class');
        $examTypes = [
            ExamPaper::EXAM_TYPE_MIDTERM,
            ExamPaper::EXAM_TYPE_FINAL,
            ExamPaper::EXAM_TYPE_UNIT_TEST,
            ExamPaper::EXAM_TYPE_QUIZ,
            ExamPaper::EXAM_TYPE_ASSIGNMENT,
            ExamPaper::EXAM_TYPE_PROJECT,
            ExamPaper::EXAM_TYPE_PRACTICAL
        ];
        $paperTypes = [
            ExamPaper::PAPER_TYPE_QUESTION,
            ExamPaper::PAPER_TYPE_ANSWER_KEY,
            ExamPaper::PAPER_TYPE_SOLUTION,
            ExamPaper::PAPER_TYPE_SYLLABUS,
            ExamPaper::PAPER_TYPE_SAMPLE,
            ExamPaper::PAPER_TYPE_PREVIOUS_YEAR
        ];
        $accessLevels = [
            ExamPaper::ACCESS_PUBLIC,
            ExamPaper::ACCESS_TEACHERS_ONLY,
            ExamPaper::ACCESS_STUDENTS_ONLY,
            ExamPaper::ACCESS_PRIVATE
        ];
        $academicYears = [
            date('Y') . '-' . (date('Y') + 1),
            (date('Y') - 1) . '-' . date('Y'),
            (date('Y') + 1) . '-' . (date('Y') + 2)
        ];

        return view('exam-papers.edit', compact(
            'examPaper', 'subjects', 'classSections', 'examTypes', 'paperTypes', 'accessLevels', 'academicYears'
        ));
    }

    /**
     * Update the specified exam paper in storage.
     */
    public function update(Request $request, ExamPaper $examPaper)
    {
        $this->authorize('update', $examPaper);
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'subject' => 'required|string|max:100',
            'class_section' => 'required|string|max:50',
            'exam_type' => 'required|in:' . implode(',', [
                ExamPaper::EXAM_TYPE_MIDTERM,
                ExamPaper::EXAM_TYPE_FINAL,
                ExamPaper::EXAM_TYPE_UNIT_TEST,
                ExamPaper::EXAM_TYPE_QUIZ,
                ExamPaper::EXAM_TYPE_ASSIGNMENT,
                ExamPaper::EXAM_TYPE_PROJECT,
                ExamPaper::EXAM_TYPE_PRACTICAL
            ]),
            'paper_type' => 'required|in:' . implode(',', [
                ExamPaper::PAPER_TYPE_QUESTION,
                ExamPaper::PAPER_TYPE_ANSWER_KEY,
                ExamPaper::PAPER_TYPE_SOLUTION,
                ExamPaper::PAPER_TYPE_SYLLABUS,
                ExamPaper::PAPER_TYPE_SAMPLE,
                ExamPaper::PAPER_TYPE_PREVIOUS_YEAR
            ]),
            'file' => 'nullable|file|mimes:pdf,doc,docx,txt,jpeg,jpg,png|max:10240', // 10MB max
            'academic_year' => 'nullable|string|max:20',
            'semester' => 'nullable|string|max:20',
            'duration_minutes' => 'nullable|integer|min:1',
            'total_marks' => 'nullable|integer|min:1',
            'exam_date' => 'nullable|date',
            'exam_time' => 'nullable|date_format:H:i',
            'access_level' => 'required|in:' . implode(',', [
                ExamPaper::ACCESS_PUBLIC,
                ExamPaper::ACCESS_TEACHERS_ONLY,
                ExamPaper::ACCESS_STUDENTS_ONLY,
                ExamPaper::ACCESS_PRIVATE
            ]),
            'instructions' => 'nullable|string',
            'is_published' => 'boolean',
            'is_answer_key' => 'boolean',
            'password_protected' => 'boolean',
            'access_password' => 'nullable|required_if:password_protected,true|string|min:6'
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        // Handle file upload if provided
        if ($request->hasFile('file')) {
            // Delete old file
            if ($examPaper->file_path) {
                Storage::disk('public')->delete($examPaper->file_path);
            }

            $file = $request->file('file');
            $originalName = $file->getClientOriginalName();
            $extension = $file->getClientOriginalExtension();
            $fileName = time() . '_' . uniqid() . '.' . $extension;
            $filePath = $file->storeAs('exam-papers', $fileName, 'public');
            $fileSize = $file->getSize();

            $examPaper->file_path = $filePath;
            $examPaper->file_name = $originalName;
            $examPaper->file_size = $fileSize;
            $examPaper->file_extension = $extension;
        }

        $examPaper->title = $request->title;
        $examPaper->subject = $request->subject;
        $examPaper->class_section = $request->class_section;
        $examPaper->exam_type = $request->exam_type;
        $examPaper->paper_type = $request->paper_type;
        $examPaper->academic_year = $request->academic_year;
        $examPaper->semester = $request->semester;
        $examPaper->duration_minutes = $request->duration_minutes;
        $examPaper->total_marks = $request->total_marks;
        $examPaper->instructions = $request->instructions;
        $examPaper->exam_date = $request->exam_date;
        $examPaper->exam_time = $request->exam_time;
        $examPaper->access_level = $request->access_level;
        $examPaper->is_published = $request->is_published ? true : false;
        $examPaper->is_answer_key = $request->is_answer_key ? true : false;
        $examPaper->password_protected = $request->password_protected ? true : false;
        $examPaper->access_password = $request->password_protected ? bcrypt($request->access_password) : null;
        $examPaper->valid_from = $request->valid_from;
        $examPaper->valid_until = $request->valid_until;
        
        if ($request->filled('marks_distribution')) {
            $examPaper->marks_distribution = json_decode($request->marks_distribution, true);
        }

        $examPaper->save();

        return redirect()->route('exam-papers.index')
                         ->with('success', 'Exam paper updated successfully!');
    }

    /**
     * Remove the specified exam paper from storage.
     */
    public function destroy(ExamPaper $examPaper)
    {
        $this->authorize('delete', $examPaper);
        // Delete the physical file
        if ($examPaper->file_path) {
            Storage::disk('public')->delete($examPaper->file_path);
        }

        $examPaper->delete();

        return redirect()->route('exam-papers.index')
                         ->with('success', 'Exam paper deleted successfully!');
    }

    /**
     * Download the specified exam paper file.
     */
    public function download(ExamPaper $examPaper, Request $request)
    {
        $this->authorize('view', $examPaper);
        // Check if user can access this file
        if (!$examPaper->canBeAccessedBy(Auth::user())) {
            abort(403, 'Unauthorized to access this file');
        }

        // Check if password is required
        if ($examPaper->password_protected) {
            $password = $request->input('password');
            
            // Verify password
            if (!$password || !password_verify($password, $examPaper->access_password)) {
                return back()->with('error', 'Invalid password for this exam paper');
            }
        }

        // Increment download count
        $examPaper->incrementDownloadCount();

        // Return file download
        return Storage::disk('public')->download($examPaper->file_path, $examPaper->file_name);
    }

    /**
     * Display available exam papers for a class.
     */
    public function availableForClass(Request $request)
    {
        $this->authorize('viewAny', ExamPaper::class);
        $classSection = $request->class_section;
        $academicYear = $request->academic_year ?: date('Y') . '-' . (date('Y') + 1);

        if ($classSection) {
            $papers = ExamPaper::getAvailableForClass($classSection, $academicYear);
        } else {
            $papers = collect();
        }

        $classSections = Student::distinct()->pluck('class')->filter()->sortBy('class');

        return view('exam-papers.available', compact('papers', 'classSection', 'academicYear', 'classSections'));
    }

    /**
     * Search exam papers.
     */
    public function search(Request $request)
    {
        $this->authorize('viewAny', ExamPaper::class);
        $query = $request->input('query');
        $classSection = $request->input('class_section');

        if ($query) {
            $papers = ExamPaper::where(function($q) use ($query) {
                $q->where('title', 'LIKE', "%{$query}%")
                  ->orWhere('subject', 'LIKE', "%{$query}%")
                  ->orWhere('class_section', 'LIKE', "%{$query}%");
            })
            ->when($classSection, function($q, $classSection) {
                $q->where('class_section', $classSection);
            })
            ->published()
            ->approved()
            ->orderBy('exam_date', 'desc')
            ->get();
        } else {
            $papers = collect();
        }

        $classSections = Student::distinct()->pluck('class')->filter()->sortBy('class');

        return view('exam-papers.search', compact('papers', 'query', 'classSection', 'classSections'));
    }

    /**
     * Get upcoming exams.
     */
    public function upcoming()
    {
        $this->authorize('viewAny', ExamPaper::class);
        $upcomingExams = ExamPaper::getUpcomingExams(30); // Next 30 days
        return view('exam-papers.upcoming', compact('upcomingExams'));
    }

    /**
     * Toggle publish status.
     */
    public function togglePublish(ExamPaper $examPaper)
    {
        $this->authorize('update', $examPaper);
        $examPaper->is_published = !$examPaper->is_published;
        $examPaper->save();

        $status = $examPaper->is_published ? 'published' : 'unpublished';
        return back()->with('success', "Exam paper {$status} successfully!");
    }
}
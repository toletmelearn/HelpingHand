<?php

namespace App\Http\Controllers;

use App\Models\BellTimingTemplate;
use App\Models\SchoolClass;
use App\Services\Timetable\BellTimingTemplateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

class BellTimingTemplateController extends Controller
{
    private const DAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

    public function __construct(private BellTimingTemplateService $templates)
    {
    }

    public function index()
    {
        $this->authorize('viewAny', BellTimingTemplate::class);

        $templates = BellTimingTemplate::withCount('slots')->orderBy('name')->get();

        return view('bell-timing-templates.index', compact('templates'));
    }

    public function create()
    {
        $this->authorize('create', BellTimingTemplate::class);

        return view('bell-timing-templates.create', [
            'academicYears' => $this->academicYearOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', BellTimingTemplate::class);

        $data = $this->validateTemplate($request);

        $template = $this->templates->create($data['attributes'], $data['slots'], $request->user());

        return redirect()->route('bell-timing-templates.index')
            ->with('success', "Template \"{$template->name}\" created with {$template->slots->count()} periods.");
    }

    public function edit(BellTimingTemplate $bellTimingTemplate)
    {
        $this->authorize('update', $bellTimingTemplate);

        $bellTimingTemplate->load('slots');

        return view('bell-timing-templates.edit', [
            'template' => $bellTimingTemplate,
            'academicYears' => $this->academicYearOptions(),
        ]);
    }

    public function update(Request $request, BellTimingTemplate $bellTimingTemplate): RedirectResponse
    {
        $this->authorize('update', $bellTimingTemplate);

        $data = $this->validateTemplate($request);

        $this->templates->update($bellTimingTemplate, $data['attributes'], $data['slots'], $request->user());

        return redirect()->route('bell-timing-templates.index')
            ->with('success', "Template \"{$bellTimingTemplate->name}\" updated. Classes already applied are NOT affected -- use Apply again to propagate changes.");
    }

    public function destroy(BellTimingTemplate $bellTimingTemplate): RedirectResponse
    {
        $this->authorize('delete', $bellTimingTemplate);

        $name = $bellTimingTemplate->name;
        $bellTimingTemplate->delete();

        return redirect()->route('bell-timing-templates.index')
            ->with('success', "Template \"{$name}\" deleted. Classes it was previously applied to are unaffected.");
    }

    public function duplicate(Request $request, BellTimingTemplate $bellTimingTemplate): RedirectResponse
    {
        $this->authorize('create', BellTimingTemplate::class);

        $request->validate(['name' => 'required|string|max:255']);

        $copy = $this->templates->duplicate($bellTimingTemplate, $request->string('name')->toString(), $request->user());

        return redirect()->route('bell-timing-templates.edit', $copy)
            ->with('success', "Duplicated as \"{$copy->name}\". You can now customize it.");
    }

    /**
     * "Save as Template" -- snapshot an existing class's schedule for one
     * day into a brand-new template. GET shows a small form (class + day +
     * template name); POST creates it.
     */
    public function saveAsTemplateForm(Request $request)
    {
        $this->authorize('create', BellTimingTemplate::class);

        return view('bell-timing-templates.save-as-template', [
            'classSections' => $this->classSectionOptions(),
            'days' => self::DAYS,
            'presetClassSection' => $request->query('class_section'),
        ]);
    }

    public function saveAsTemplateStore(Request $request): RedirectResponse
    {
        $this->authorize('create', BellTimingTemplate::class);

        $validated = $request->validate([
            'class_section' => 'required|string|max:50',
            'day' => 'required|string|in:' . implode(',', self::DAYS),
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
        ]);

        try {
            $template = $this->templates->createFromExistingClass(
                $validated['class_section'],
                $validated['day'],
                ['name' => $validated['name'], 'description' => $validated['description'] ?? null],
                $request->user()
            );
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('bell-timing-templates.index')
            ->with('success', "Saved \"{$validated['class_section']}\"'s {$validated['day']} schedule as template \"{$template->name}\".");
    }

    /**
     * Step 1 of Apply: pick target days and target classes.
     */
    public function applyForm(BellTimingTemplate $bellTimingTemplate)
    {
        $this->authorize('apply', $bellTimingTemplate);

        $bellTimingTemplate->load('slots');

        return view('bell-timing-templates.apply', [
            'template' => $bellTimingTemplate,
            'days' => self::DAYS,
            'classSections' => $this->classSectionOptions(),
            'academicYears' => $this->academicYearOptions(),
        ]);
    }

    /**
     * Step 2a: validate the admin's day/class selection and stash it in the
     * session, then redirect (Post/Redirect/Get) to the GET preview route.
     * No comparison work and no writes happen here -- this action's only
     * job is to turn a POST body into GET-able state.
     */
    public function applyPreview(Request $request, BellTimingTemplate $bellTimingTemplate): RedirectResponse
    {
        $this->authorize('apply', $bellTimingTemplate);

        $validated = $request->validate([
            'days' => 'required|array|min:1',
            'days.*' => 'in:' . implode(',', self::DAYS),
            'classes' => 'required|array|min:1',
            'classes.*' => 'string|max:50',
            'academic_year' => 'nullable|string|max:20',
            'semester' => 'nullable|string|max:20',
        ]);

        $request->session()->put($this->previewSessionKey($bellTimingTemplate), [
            'days' => $validated['days'],
            'classes' => $validated['classes'],
            'academic_year' => $validated['academic_year'] ?? null,
            'semester' => $validated['semester'] ?? null,
        ]);

        return redirect()->route('bell-timing-templates.apply.preview.show', $bellTimingTemplate);
    }

    /**
     * Step 2b: the GET-able preview page. Reads the selection stashed by
     * applyPreview() out of the session and recomputes the structure
     * comparison fresh on every load -- read-only (compareStructure() never
     * writes), so this route is safe to refresh, bookmark, or revisit and
     * can never itself apply anything. If there's no stashed selection
     * (session expired, or the URL was visited without going through the
     * form), bounce back to step 1 instead of erroring.
     */
    public function applyPreviewShow(BellTimingTemplate $bellTimingTemplate)
    {
        $this->authorize('apply', $bellTimingTemplate);

        $selection = session($this->previewSessionKey($bellTimingTemplate));

        if (! $selection) {
            return redirect()->route('bell-timing-templates.apply.form', $bellTimingTemplate)
                ->with('error', 'Your preview selection has expired. Please reselect classes and preview again.');
        }

        $bellTimingTemplate->load('slots');
        $firstDay = $selection['days'][0];

        $comparisons = collect($selection['classes'])->mapWithKeys(function ($classSection) use ($bellTimingTemplate, $firstDay) {
            return [$classSection => $this->templates->compareStructure($bellTimingTemplate, $classSection, $firstDay)];
        });

        return view('bell-timing-templates.apply-preview', [
            'template' => $bellTimingTemplate,
            'days' => $selection['days'],
            'classes' => $selection['classes'],
            'academicYear' => $selection['academic_year'],
            'semester' => $selection['semester'],
            'comparisons' => $comparisons,
        ]);
    }

    private function previewSessionKey(BellTimingTemplate $bellTimingTemplate): string
    {
        return "bell_timing_template_preview.{$bellTimingTemplate->id}";
    }

    /**
     * Step 3: execute the apply, transactionally, using the admin's
     * explicit per-class decisions from the preview screen.
     */
    public function applyConfirm(Request $request, BellTimingTemplate $bellTimingTemplate): RedirectResponse
    {
        $this->authorize('apply', $bellTimingTemplate);

        $validated = $request->validate([
            'days' => 'required|array|min:1',
            'days.*' => 'in:' . implode(',', self::DAYS),
            'academic_year' => 'nullable|string|max:20',
            'semester' => 'nullable|string|max:20',
            'decisions' => 'required|array|min:1',
            'decisions.*.action' => 'required|in:apply,replace,customize,copy_matching,skip',
            'decisions.*.slots' => 'nullable|array',
            'decisions.*.slots.*.period_name' => 'required_with:decisions.*.slots|string|max:100',
            'decisions.*.slots.*.start_time' => 'required_with:decisions.*.slots|date_format:H:i',
            'decisions.*.slots.*.end_time' => 'required_with:decisions.*.slots|date_format:H:i|after:decisions.*.slots.*.start_time',
            'decisions.*.slots.*.is_break' => 'nullable|boolean',
            'decisions.*.slots.*.period_type' => 'nullable|string',
            'decisions.*.slots.*.custom_label' => 'nullable|string|max:100',
            'decisions.*.slots.*.color_code' => 'nullable|string|max:20',
        ]);

        try {
            $results = $this->templates->applyToClasses(
                $bellTimingTemplate,
                $validated['days'],
                $validated['decisions'],
                $validated['academic_year'] ?? null,
                $validated['semester'] ?? null,
                $request->user()
            );
        } catch (RuntimeException $e) {
            return back()->with('error', 'Nothing was applied. ' . $e->getMessage());
        }

        $applied = collect($results)->reject(fn ($r) => $r['action'] === 'skip')->count();
        $skipped = collect($results)->where('action', 'skip')->count();

        $request->session()->forget($this->previewSessionKey($bellTimingTemplate));

        return redirect()->route('bell-timing-templates.index')
            ->with('success', "Applied \"{$bellTimingTemplate->name}\" to {$applied} class(es)." . ($skipped ? " {$skipped} class(es) skipped." : ''));
    }

    private function validateTemplate(Request $request): array
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'academic_year' => 'nullable|string|max:20',
            'semester' => 'nullable|string|max:20',
            'slots' => 'required|array|min:1',
            'slots.*.period_name' => 'required|string|max:100',
            'slots.*.start_time' => 'required|date_format:H:i',
            'slots.*.end_time' => 'required|date_format:H:i|after:slots.*.start_time',
            'slots.*.is_break' => 'nullable|boolean',
            'slots.*.order_index' => 'required|integer|min:0',
            'slots.*.custom_label' => 'nullable|string|max:100',
            'slots.*.color_code' => 'nullable|string|max:20',
        ]);

        return [
            'attributes' => $request->only(['name', 'description', 'academic_year', 'semester']),
            'slots' => $request->input('slots'),
        ];
    }

    private function classSectionOptions()
    {
        return SchoolClass::active()->orderByOrder()->pluck('name');
    }

    private function academicYearOptions(): array
    {
        $currentYear = (int) date('Y');

        return [
            $currentYear . '-' . ($currentYear + 1),
            ($currentYear - 1) . '-' . $currentYear,
            ($currentYear + 1) . '-' . ($currentYear + 2),
        ];
    }
}

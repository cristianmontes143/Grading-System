<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Curriculum;
use App\Models\ReportCardTemplate;
use App\Services\Audit\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ReportCardTemplateController extends Controller
{
    public function __construct(private readonly AuditLogService $auditLog)
    {
    }

    public function index(): View
    {
        $templates = ReportCardTemplate::query()->with('curriculum')->orderByDesc('is_default')->orderBy('id')->get();
        $curricula = Curriculum::query()->orderBy('id')->get();

        return view('admin.report-card-templates.index', [
            'templates' => $templates,
            'curricula' => $curricula,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150', 'unique:report_card_templates,name'],
            'curriculum_id' => ['nullable', 'exists:curricula,id'],
            'header_subtitle' => ['required', 'string', 'max:200'],
            'disclaimer_label' => ['required', 'string', 'max:200'],
            'footer_note' => ['nullable', 'string', 'max:1000'],
            'paper_size' => ['required', Rule::in(['a4', 'letter', 'legal'])],
            'orientation' => ['required', Rule::in(['portrait', 'landscape'])],
            'show_attendance' => ['nullable', 'boolean'],
            'show_observed_values' => ['nullable', 'boolean'],
            'show_lrn' => ['nullable', 'boolean'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        $template = DB::transaction(function () use ($validated, $request): ReportCardTemplate {
            if ($request->boolean('is_default')) {
                ReportCardTemplate::query()->update(['is_default' => false]);
            }

            return ReportCardTemplate::query()->create([
                'name' => trim($validated['name']),
                'curriculum_id' => $validated['curriculum_id'] ?? null,
                'header_subtitle' => trim($validated['header_subtitle']),
                'disclaimer_label' => trim($validated['disclaimer_label']),
                'footer_note' => $validated['footer_note'] ?? null,
                'paper_size' => $validated['paper_size'],
                'orientation' => $validated['orientation'],
                'show_attendance' => $request->boolean('show_attendance', true),
                'show_observed_values' => $request->boolean('show_observed_values', true),
                'show_lrn' => $request->boolean('show_lrn', true),
                'is_default' => $request->boolean('is_default'),
            ]);
        });

        $this->auditLog->record(
            eventType: 'report_card_template.created',
            auditable: $template,
            newValues: $template->only(['name', 'paper_size', 'orientation', 'is_default']),
            request: $request
        );

        return redirect()
            ->route('admin.report-card-templates.index')
            ->with('status', "Report card template \"{$template->name}\" saved.");
    }

    public function setDefault(Request $request, ReportCardTemplate $reportCardTemplate): RedirectResponse
    {
        DB::transaction(function () use ($reportCardTemplate): void {
            ReportCardTemplate::query()->update(['is_default' => false]);
            $reportCardTemplate->update(['is_default' => true]);
        });

        $this->auditLog->record(
            eventType: 'report_card_template.default_changed',
            auditable: $reportCardTemplate,
            newValues: ['name' => $reportCardTemplate->name],
            request: $request
        );

        return back()->with('status', "\"{$reportCardTemplate->name}\" is now the default report card template.");
    }
}

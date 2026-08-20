<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ControlledFormCategory;
use App\Enums\ControlledFormFieldType;
use App\Enums\ControlledFormRevisionStatus;
use App\Http\Controllers\Controller;
use App\Models\AnalysisCategory;
use App\Models\AnalysisPackage;
use App\Models\AnalysisType;
use App\Models\ControlledForm;
use App\Models\ControlledFormRevision;
use App\Models\JobOrder;
use App\Services\ControlledDocumentGenerator;
use App\Services\ControlledFormService;
use App\Services\ControlledPdfFiller;
use App\Services\DocumentAuditLogger;
use App\Services\FieldValueResolver;
use App\Services\RevisionWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ControlledFormAdminController extends Controller
{
    public function __construct(
        private readonly ControlledFormService $forms,
        private readonly RevisionWorkflow $workflow,
    ) {}

    public function index(): Response
    {
        $forms = ControlledForm::query()
            ->with(['currentRevision.creator', 'currentRevision.approver'])
            ->withCount('revisions')
            ->latest()
            ->get()
            ->map(fn (ControlledForm $form) => $this->serializeForm($form, false))
            ->values();

        return Inertia::render('admin/controlled-forms/index', [
            'forms' => $forms,
            'categories' => collect(ControlledFormCategory::cases())->map(fn ($c) => [
                'value' => $c->value,
                'label' => $c->label(),
            ]),
            'analysisGroups' => $this->analysisGroups(),
            'packages' => $this->packages(),
        ]);
    }

    public function show(ControlledForm $controlledForm): Response
    {
        $controlledForm->load([
            'revisions.creator',
            'revisions.approver',
            'currentRevision',
            'analysisTypes.category',
        ]);

        return Inertia::render('admin/controlled-forms/show', [
            'form' => $this->serializeForm($controlledForm, true),
            'analysisGroups' => $this->analysisGroups(),
            'packages' => $this->packages(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->formRules());
        $data = $this->normalizePackageBindings($data);
        $file = $request->file('file');

        try {
            $form = $this->forms->createForm($data, $request->user(), $file);

            if ($request->boolean('activate') && $form->revisions()->first()?->hasCanonicalPdf()) {
                $this->workflow->transition(
                    $form->revisions()->first(),
                    ControlledFormRevisionStatus::Active,
                    $request->user(),
                );
            }

            return redirect()
                ->route('admin.controlled-forms.show', $form)
                ->with('success', 'Controlled form created.');
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);

            return back()->withErrors([
                'file' => $e->getMessage(),
            ]);
        }
    }

    public function update(Request $request, ControlledForm $controlledForm): RedirectResponse
    {
        $resultBindings = $controlledForm->category === ControlledFormCategory::AnalysisResult
            && ! $request->filled('analysis_package_id')
            ? ['required', 'array', 'min:1']
            : ['nullable', 'array'];

        $data = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:2000'],
            'department' => ['nullable', 'string', 'max:120'],
            'analysis_package_id' => ['nullable', 'integer', 'exists:analysis_packages,id'],
            'analysis_type_ids' => $resultBindings,
            'analysis_type_ids.*' => ['integer', 'exists:analysis_types,id'],
        ]);
        $data = $this->normalizePackageBindings($data);

        $this->forms->updateForm($controlledForm, $data, $request->user());

        return back()->with('success', 'Controlled form updated.');
    }

    public function storeRevision(Request $request, ControlledForm $controlledForm): RedirectResponse
    {
        $data = $request->validate([
            'revision' => ['nullable', 'string', 'max:20'],
            'effective_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'file' => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:15360'],
            'activate' => ['sometimes', 'boolean'],
            'copy_from_revision_id' => [
                'nullable',
                'integer',
                Rule::exists('controlled_form_revisions', 'id')->where('controlled_form_id', $controlledForm->id),
            ],
        ]);

        try {
            $revision = $this->forms->createRevision(
                $controlledForm,
                $data,
                $request->user(),
                $request->file('file'),
            );

            if ($request->boolean('activate') && $revision->hasCanonicalPdf()) {
                $this->workflow->transition($revision, ControlledFormRevisionStatus::Active, $request->user());
            }

            return redirect()
                ->route('admin.form-designer.show', [$controlledForm, $revision])
                ->with('success', 'Revision created.');
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);

            return back()->withErrors([
                'file' => $e->getMessage(),
            ]);
        }
    }

    public function uploadRevisionFile(Request $request, ControlledForm $controlledForm, ControlledFormRevision $revision): RedirectResponse
    {
        abort_unless($revision->controlled_form_id === $controlledForm->id, 404);

        $request->validate([
            'file' => ['required', 'file', 'mimes:pdf,doc,docx', 'max:15360'],
        ]);

        try {
            $this->forms->attachFile($controlledForm, $revision, $request->file('file'));
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);

            return back()->withErrors([
                'file' => $e->getMessage(),
            ]);
        }

        return back()->with('success', 'File uploaded. The canonical PDF was stored without modifying the original layout.');
    }

    public function transition(Request $request, ControlledForm $controlledForm, ControlledFormRevision $revision): RedirectResponse
    {
        abort_unless($revision->controlled_form_id === $controlledForm->id, 404);

        $data = $request->validate([
            'status' => ['required', Rule::enum(ControlledFormRevisionStatus::class)],
            'comment' => ['nullable', 'string', 'max:500'],
        ]);

        $this->workflow->transition(
            $revision,
            ControlledFormRevisionStatus::from($data['status']),
            $request->user(),
            $data['comment'] ?? null,
        );

        return back()->with('success', 'Revision status updated.');
    }

    public function designer(ControlledForm $controlledForm, ControlledFormRevision $revision): Response
    {
        abort_unless($revision->controlled_form_id === $controlledForm->id, 404);

        $revision->load(['fields', 'form', 'creator']);
        $controlledForm->load(['analysisTypes', 'analysisPackage.analysisTypes']);

        return Inertia::render('admin/form-designer', [
            'form' => $this->serializeForm($controlledForm, false),
            'revision' => $this->serializeRevision($revision, true),
            'next_revision' => $this->forms->nextRevisionNumber($controlledForm),
            'sources' => FieldValueResolver::catalog($controlledForm->category->value, $controlledForm),
            'packages' => $this->packages(),
            'fieldTypes' => collect(ControlledFormFieldType::cases())->map(fn ($t) => [
                'value' => $t->value,
                'label' => $t->label(),
                'width' => $t->defaultWidth(),
                'height' => $t->defaultHeight(),
            ]),
            'jobOrders' => JobOrder::query()
                ->latest()
                ->limit(25)
                ->get(['id', 'reference_no', 'customer_name'])
                ->map(fn (JobOrder $order) => [
                    'id' => $order->id,
                    'label' => $order->reference_no.' — '.$order->customer_name,
                ]),
        ]);
    }

    public function saveFields(Request $request, ControlledForm $controlledForm, ControlledFormRevision $revision): RedirectResponse
    {
        abort_unless($revision->controlled_form_id === $controlledForm->id, 404);

        $data = $request->validate([
            'fields' => ['required', 'array'],
            'fields.*.id' => ['nullable', 'integer'],
            'fields.*.name' => ['nullable', 'string', 'max:80'],
            'fields.*.label' => ['required', 'string', 'max:120'],
            'fields.*.field_type' => ['required', 'string', 'max:30'],
            'fields.*.page_number' => ['required', 'integer', 'min:1'],
            'fields.*.x' => ['required', 'numeric'],
            'fields.*.y' => ['required', 'numeric'],
            'fields.*.width' => ['required', 'numeric', 'min:0.5'],
            'fields.*.height' => ['required', 'numeric', 'min:0.5'],
            'fields.*.font_size' => ['nullable', 'numeric'],
            'fields.*.font_family' => ['nullable', 'string', Rule::in(['calibri', 'helvetica', 'times', 'courier'])],
            'fields.*.font_color' => ['nullable', 'string', 'max:20'],
            'fields.*.alignment' => ['nullable', 'string', 'max:5'],
            'fields.*.data_source_key' => ['nullable', 'string', 'max:120'],
            'fields.*.format' => ['nullable', 'string', 'max:40'],
            'fields.*.checkbox_true_value' => ['nullable', 'string', 'max:80'],
            'fields.*.options' => ['nullable', 'array'],
            'fields.*.table_config' => ['nullable', 'array'],
            'fields.*.z_order' => ['nullable', 'integer'],
        ]);

        $this->forms->replaceFields($revision, $data['fields'], $request->user());

        return back()->with('success', 'Field layout saved.');
    }

    public function importBlueprint(Request $request, ControlledForm $controlledForm, ControlledFormRevision $revision): RedirectResponse
    {
        abort_unless($revision->controlled_form_id === $controlledForm->id, 404);

        $this->forms->importRfaBlueprint($revision);

        app(DocumentAuditLogger::class)->record('field.added', $revision, $request->user(), null, [
            'source' => 'rfa_form_fields',
        ]);

        return back()->with('success', 'RFA field blueprint imported. Recalibrate positions in the designer.');
    }

    public function canonical(ControlledForm $controlledForm, ControlledFormRevision $revision): BinaryFileResponse
    {
        abort_unless($revision->controlled_form_id === $controlledForm->id, 404);
        abort_unless($revision->hasCanonicalPdf(), 404);

        return response()->file($revision->canonicalAbsolutePath(), [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$controlledForm->form_code.'-REV-'.$revision->revision.'.pdf"',
        ]);
    }

    public function original(ControlledForm $controlledForm, ControlledFormRevision $revision): BinaryFileResponse
    {
        abort_unless($revision->controlled_form_id === $controlledForm->id, 404);
        $path = $revision->originalAbsolutePath();
        abort_unless($path !== null, 404);

        return response()->file($path, [
            'Content-Disposition' => 'attachment; filename="'.($revision->original_name ?: 'original').'"',
        ]);
    }

    public function preview(Request $request, ControlledForm $controlledForm, ControlledFormRevision $revision, ControlledDocumentGenerator $generator): HttpResponse
    {
        abort_unless($revision->controlled_form_id === $controlledForm->id, 404);
        abort_unless($revision->hasCanonicalPdf(), 404);

        $jobOrder = $request->integer('job_order_id') > 0
            ? JobOrder::query()->find($request->integer('job_order_id'))
            : null;

        $result = $generator->preview($revision->load('fields', 'form'), $jobOrder);

        return response($result['binary'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="preview-'.$controlledForm->form_code.'.pdf"',
        ]);
    }

    public function calibration(ControlledForm $controlledForm, ControlledFormRevision $revision, ControlledPdfFiller $filler): HttpResponse
    {
        abort_unless($revision->controlled_form_id === $controlledForm->id, 404);
        abort_unless($revision->hasCanonicalPdf(), 404);

        $binary = $filler->calibrationOverlay($revision->load('fields'));

        return response($binary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="calibration-'.$controlledForm->form_code.'.pdf"',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function serializeForm(ControlledForm $form, bool $withRevisions): array
    {
        $current = $form->currentRevision ?? $form->activeRevision();

        $payload = [
            'id' => $form->id,
            'form_code' => $form->form_code,
            'name' => $form->name,
            'description' => $form->description,
            'department' => $form->department,
            'category' => $form->category->value,
            'category_label' => $form->category->label(),
            'combination_key' => $form->combination_key,
            'current_revision' => $current ? self::serializeRevision($current, false) : null,
            'status' => $current?->status->value,
            'status_label' => $current?->status->label() ?? 'NO REVISION',
            'updated_at' => $form->updated_at?->toIso8601String(),
            'revisions_count' => $form->revisions_count ?? $form->revisions()->count(),
            'analysis_type_ids' => $form->orderedTypeIds(),
            'analysis_package_id' => $form->analysis_package_id,
        ];

        if ($withRevisions) {
            $payload['revisions'] = $form->revisions
                ->map(fn (ControlledFormRevision $revision) => self::serializeRevision($revision, false))
                ->values();
            $payload['analysis_types'] = $form->analysisTypes->map(fn (AnalysisType $type) => [
                'id' => $type->id,
                'code' => $type->code,
                'name' => $type->name,
            ])->values();
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    public static function serializeRevision(ControlledFormRevision $revision, bool $withFields): array
    {
        $payload = [
            'id' => $revision->id,
            'revision' => $revision->revision,
            'status' => $revision->status->value,
            'status_label' => $revision->status->label(),
            'effective_date' => $revision->effective_date?->format('Y-m-d'),
            'notes' => $revision->notes,
            'original_name' => $revision->original_name,
            'has_canonical' => $revision->hasCanonicalPdf(),
            'has_original' => $revision->originalAbsolutePath() !== null,
            'page_count' => $revision->page_count,
            'page_width_mm' => $revision->page_width_mm !== null ? (float) $revision->page_width_mm : null,
            'page_height_mm' => $revision->page_height_mm !== null ? (float) $revision->page_height_mm : null,
            'fill_mode' => $revision->fill_mode,
            'sha256' => $revision->sha256,
            'created_by' => $revision->creator?->name,
            'approved_by' => $revision->approver?->name,
            'approved_at' => $revision->approved_at?->toDayDateTimeString(),
            'created_at' => $revision->created_at?->toDayDateTimeString(),
            'editable' => $revision->status->isEditable(),
            'field_count' => $withFields ? $revision->fields->count() : $revision->fields()->count(),
        ];

        if ($withFields) {
            $payload['fields'] = $revision->fields->map(fn ($field) => $field->toDesignerArray())->values();
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizePackageBindings(array $data): array
    {
        $packageId = isset($data['analysis_package_id']) ? (int) $data['analysis_package_id'] : 0;
        if ($packageId < 1) {
            $data['analysis_package_id'] = $data['analysis_package_id'] ?? null;

            return $data;
        }

        $package = AnalysisPackage::query()->find($packageId);
        if ($package) {
            $data['analysis_package_id'] = $package->id;
            $data['analysis_type_ids'] = $package->orderedTypeIds();
        }

        return $data;
    }

    /**
     * @return list<array{id: int, code: string, name: string, analysis_type_ids: list<int>}>
     */
    private function packages(): array
    {
        return AnalysisPackage::query()
            ->where('is_active', true)
            ->with('analysisTypes')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (AnalysisPackage $package) => [
                'id' => $package->id,
                'code' => $package->code,
                'name' => $package->name,
                'analysis_type_ids' => $package->orderedTypeIds(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{label: string, items: list<array{id: int, code: string, name: string}>}>
     */
    private function analysisGroups(): array
    {
        $categories = AnalysisCategory::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $types = AnalysisType::query()
            ->with('category')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return $categories->map(function (AnalysisCategory $category) use ($types) {
            $items = $types
                ->where('category_id', $category->id)
                ->values()
                ->map(fn (AnalysisType $type) => [
                    'id' => $type->id,
                    'code' => $type->code,
                    'name' => $type->name,
                ]);

            return [
                'label' => $category->name,
                'items' => $items->all(),
            ];
        })->filter(fn (array $group) => $group['items'] !== [])->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function formRules(): array
    {
        return [
            'form_code' => ['required', 'string', 'max:40', 'unique:controlled_forms,form_code', 'regex:/^[A-Za-z0-9][A-Za-z0-9._-]*$/'],
            'name' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:2000'],
            'department' => ['nullable', 'string', 'max:120'],
            'category' => ['required', Rule::enum(ControlledFormCategory::class)],
            'revision' => ['nullable', 'string', 'max:20'],
            'effective_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'file' => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:15360'],
            'analysis_package_id' => ['nullable', 'integer', 'exists:analysis_packages,id'],
            'analysis_type_ids' => request('category') === ControlledFormCategory::AnalysisResult->value
                && ! request()->filled('analysis_package_id')
                ? ['required', 'array', 'min:1']
                : ['nullable', 'array'],
            'analysis_type_ids.*' => ['integer', 'exists:analysis_types,id'],
            'activate' => ['sometimes', 'boolean'],
            'fill_mode' => ['nullable', Rule::in(['overlay', 'named'])],
        ];
    }
}

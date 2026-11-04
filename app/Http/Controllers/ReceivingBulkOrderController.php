<?php

namespace App\Http\Controllers;

use App\Enums\AnalysisCategory as AnalysisCategoryEnum;
use App\Enums\CatalogScope;
use App\Enums\ControlledFormCategory;
use App\Enums\PaymentMode;
use App\Enums\PaymentTerms;
use App\Models\AnalysisCategory;
use App\Models\AnalysisPackage;
use App\Models\AnalysisType;
use App\Models\ControlledForm;
use App\Models\JobOrder;
use App\Services\JobOrderService;
use App\Support\OfficialAnalysisCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReceivingBulkOrderController extends Controller
{
    /**
     * @var array<string, array{key: string, label: string, classifications: list<string>}>
     */
    private const PRESET_META = [
        'LSP-7.8-FO2' => ['key' => 'ww_physico_fo2', 'label' => 'Physico-Chemical (Wastewater)', 'classifications' => ['Wastewater']],
        'LSP-7.8-F016-PROX' => ['key' => 'proximate_f016', 'label' => 'Proximate Analysis', 'classifications' => ['Proximate Analysis']],
        'LSP-7.8-F016-MILK' => ['key' => 'milk_f016', 'label' => 'Milk Sample Analysis', 'classifications' => ['Other Food Analysis']],
        'LSP-7.8-F016-WA' => ['key' => 'water_activity_f016', 'label' => 'Water Activity', 'classifications' => ['Other Food Analysis']],
        'LSP-7.8-F016-CAP' => ['key' => 'chloramphenicol_f016', 'label' => 'Chloramphenicol', 'classifications' => ['Other Food Analysis', 'Aqua']],
        'LSP-7.8-F016-NO2' => ['key' => 'nitrite_f016', 'label' => 'Nitrite Content', 'classifications' => ['Other Food Analysis']],
        'LSP-7.8-FO26' => ['key' => 'food_micro_fo26', 'label' => 'Microbiological Examination — Food', 'classifications' => ['Food Products - Microbiological Test']],
        'LSP-7.8-FO27' => ['key' => 'food_micro_sugar_fo27', 'label' => 'Microbiological Examination — Sugar', 'classifications' => ['Food Products - Microbiological Test']],
        'LSP-7.8-FO3' => ['key' => 'dw_physico_old_fo3', 'label' => 'Physico-Chemical (Drinking Water — Old Mandatory)', 'classifications' => ['Potability']],
        'LSP-7.8-FO5' => ['key' => 'dw_micro_fo5', 'label' => 'Microbiological Examination — Drinking Water', 'classifications' => ['Potability']],
    ];

    public function __construct(private readonly JobOrderService $jobOrders) {}

    public function index(Request $request): Response
    {
        $tab = $request->string('tab')->toString();
        if (! in_array($tab, ['drafts', 'submitted'], true)) {
            $tab = 'drafts';
        }
        $search = trim($request->string('q')->toString());

        $draftsCount = JobOrder::query()->where('is_bulk_draft', true)->count();
        $submittedCount = JobOrder::query()
            ->where('is_receiving_bulk', true)
            ->where('is_bulk_draft', false)
            ->count();

        $orders = JobOrder::query()
            ->withCount(['samples', 'analyses'])
            ->with(['samples:id,job_order_id,description'])
            ->when($tab === 'drafts', fn ($query) => $query->where('is_bulk_draft', true))
            ->when($tab === 'submitted', function ($query) {
                $query->where('is_receiving_bulk', true)->where('is_bulk_draft', false);
            })
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('reference_no', 'like', "%{$search}%")
                        ->orWhere('customer_name', 'like', "%{$search}%")
                        ->orWhere('company_name', 'like', "%{$search}%")
                        ->orWhere('customer_contact', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString()
            ->through(function (JobOrder $order) {
                $named = $order->samples
                    ->filter(fn ($sample) => filled(trim((string) ($sample->description ?? ''))))
                    ->count();

                return [
                    'id' => $order->id,
                    'reference_no' => $order->reference_no,
                    'customer_name' => $order->customer_name,
                    'company_name' => $order->company_name,
                    'customer_contact' => $order->customer_contact,
                    'classification' => $order->classification,
                    'status' => $order->status->value,
                    'status_label' => $order->status->label(),
                    'samples_count' => $order->samples_count,
                    'samples_named_count' => $named,
                    'samples_encode_complete' => (int) $order->samples_count < 2
                        || $named === (int) $order->samples_count,
                    'analyses_count' => $order->analyses_count,
                    'is_bulk_draft' => (bool) $order->is_bulk_draft,
                    'has_attachment' => filled($order->attachment_path),
                    'attachment_original_name' => $order->attachment_original_name,
                    'continue_url' => $order->is_bulk_draft
                        ? route('receiving.bulk-orders.edit', $order, false)
                        : null,
                    'encode_url' => ! $order->is_bulk_draft
                        ? route('receiving.samples.edit', $order, false)
                        : null,
                    'show_url' => $order->is_bulk_draft
                        ? null
                        : route('receiving.show', $order, false),
                    'updated_at' => $order->updated_at?->toDayDateTimeString(),
                    'created_at' => $order->created_at?->toDayDateTimeString(),
                ];
            });

        return Inertia::render('receiving/bulk-orders', [
            'orders' => $orders,
            'counts' => [
                'drafts' => $draftsCount,
                'submitted' => $submittedCount,
            ],
            'filters' => [
                'tab' => $tab,
                'q' => $search,
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('receiving/bulk-create', $this->formProps());
    }

    public function edit(JobOrder $jobOrder): Response
    {
        abort_unless($jobOrder->is_bulk_draft, 404);
        $jobOrder->load('samples');

        return Inertia::render('receiving/bulk-create', [
            ...$this->formProps(),
            'draft' => $this->draftPayload($jobOrder),
        ]);
    }

    public function saveDraft(Request $request, ?JobOrder $jobOrder = null): RedirectResponse
    {
        $data = $this->validateBulkPayload($request, requireTests: false, minSamples: 1);

        if ($jobOrder !== null) {
            abort_unless($jobOrder->is_bulk_draft, 404);
        }

        $samples = $this->mapSampleRows($data, withTests: false);
        $header = $this->headerPayload($data);

        $saved = $this->jobOrders->saveBulkDraft($header, $samples, $jobOrder);
        $this->storeAttachment($request, $saved);

        return redirect()
            ->route('receiving.bulk-orders.edit', $saved)
            ->with('success', "Draft {$saved->reference_no} saved. Continue anytime from Receiving → Bulk order.");
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateBulkPayload($request, requireTests: true, minSamples: 2);

        $packageIds = array_values(array_unique(array_map('intval', $data['package_ids'] ?? [])));
        $typeIds = array_values(array_unique(array_map('intval', $data['analysis_type_ids'] ?? [])));
        $samples = $this->mapSampleRows($data, withTests: true, packageIds: $packageIds, typeIds: $typeIds);
        $header = $this->headerPayload($data);

        $draftId = isset($data['draft_id']) ? (int) $data['draft_id'] : null;
        $existing = $draftId
            ? JobOrder::query()->whereKey($draftId)->where('is_bulk_draft', true)->first()
            : null;

        if ($existing !== null) {
            $jobOrder = $this->jobOrders->finalizeBulkDraft($existing, $header, $samples);
        } else {
            $jobOrder = $this->jobOrders->createFromIntake([
                ...$header,
                'is_receiving_bulk' => true,
                'samples' => $samples,
            ]);
        }

        $this->storeAttachment($request, $jobOrder);

        return redirect()
            ->route('receiving.show', $jobOrder)
            ->with('success', "Bulk job order {$jobOrder->reference_no} created with {$jobOrder->samples()->count()} samples. Set pricing next.");
    }

    public function downloadAttachment(JobOrder $jobOrder): StreamedResponse
    {
        abort_unless(filled($jobOrder->attachment_path), 404);
        abort_unless(Storage::disk('local')->exists($jobOrder->attachment_path), 404);

        return Storage::disk('local')->download(
            $jobOrder->attachment_path,
            $jobOrder->attachment_original_name ?: basename($jobOrder->attachment_path),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function formProps(): array
    {
        $categories = AnalysisType::query()
            ->with('category')
            ->where('is_active', true)
            ->where('show_on_kiosk', true)
            ->orderBy('sort_order')
            ->get()
            ->groupBy(fn (AnalysisType $type) => $type->category_id)
            ->map(fn ($types) => [
                'category' => $types->first()->category?->slug,
                'label' => $types->first()->category?->name ?? 'Other',
                'items' => $types->map(fn (AnalysisType $type) => [
                    'id' => $type->id,
                    'code' => $type->code,
                    'name' => $type->name,
                    'default_price' => $type->default_price,
                    'catalog_scope' => $type->catalog_scope ?: 'non_aqua',
                ])->values(),
            ])
            ->filter(fn (array $group) => $group['items']->isNotEmpty())
            ->values();

        return [
            'categories' => $categories,
            'packages' => $this->kioskPackages(),
            'type_presets' => $this->typePresets(),
            'draft' => null,
            'options' => [
                'ownership' => OfficialAnalysisCatalog::ownershipTypes(),
                'classifications' => OfficialAnalysisCatalog::classifications(),
                'other_classification_options' => $this->otherClassificationOptions(),
                'wastewater_sources' => OfficialAnalysisCatalog::potabilitySampleSources(),
                'aqua_sources' => OfficialAnalysisCatalog::aquaSampleSources(),
                'wastewater_kinds' => OfficialAnalysisCatalog::wastewaterKinds(),
                'wastewater_fo2_default_codes' => [
                    'Wastewater' => OfficialAnalysisCatalog::wastewaterFo2DefaultTypeCodes('Wastewater'),
                    'River water' => OfficialAnalysisCatalog::wastewaterFo2DefaultTypeCodes('River water'),
                    'Abstracted water' => OfficialAnalysisCatalog::wastewaterFo2DefaultTypeCodes('Abstracted water'),
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function draftPayload(JobOrder $jobOrder): array
    {
        return [
            'id' => $jobOrder->id,
            'reference_no' => $jobOrder->reference_no,
            'customer_name' => $jobOrder->customer_name,
            'customer_email' => $jobOrder->customer_email,
            'customer_contact' => $jobOrder->customer_contact,
            'customer_address' => $jobOrder->customer_address,
            'company_name' => $jobOrder->company_name,
            'ownership_type' => $jobOrder->ownership_type,
            'classification' => $jobOrder->classification,
            'sampling_date' => $jobOrder->sampling_date?->format('Y-m-d'),
            'sampling_time' => $jobOrder->sampling_time,
            'sample_collected_by' => $jobOrder->sample_collected_by,
            'sampling_site' => $jobOrder->sampling_site,
            'payment_mode' => $jobOrder->payment_mode?->value,
            'payment_terms' => $jobOrder->payment_terms?->value,
            'attachment_original_name' => $jobOrder->attachment_original_name,
            'see_attached_file' => (bool) $jobOrder->see_attached_file,
            'samples' => $jobOrder->samples->values()->map(function ($sample) {
                $parsed = $this->parseSampleRemarks((string) ($sample->remarks ?? ''));

                return [
                    'sample_code' => $sample->sample_code ?? '',
                    'description' => $sample->description ?? '',
                    'location' => $parsed['location'],
                    'sample_point' => $parsed['sample_point'],
                    'collected_time' => $parsed['collected_time'],
                    'matrix' => $sample->matrix ?? 'Water',
                    'quantity' => $sample->quantity !== null ? (string) $sample->quantity : '',
                    'unit' => $sample->unit ?? 'mL',
                ];
            })->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validateBulkPayload(Request $request, bool $requireTests, int $minSamples): array
    {
        $data = $request->validate([
            'draft_id' => ['nullable', 'integer', 'exists:job_orders,id'],
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'customer_contact' => ['nullable', 'string', 'max:255'],
            'customer_address' => ['nullable', 'string', 'max:500'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'ownership_type' => ['nullable', 'string', 'max:50'],
            'classification' => ['required', 'string', 'max:255'],
            'sampling_date' => ['nullable', 'date'],
            'sampling_time' => ['nullable', 'string', 'max:50'],
            'sample_collected_by' => ['nullable', 'string', 'max:255'],
            'field_data' => ['nullable', 'string', 'max:2000'],
            'sample_storage_temp' => ['nullable', 'string', 'max:100'],
            'wastewater_source' => ['nullable', 'string', 'max:255'],
            'sampling_site' => ['nullable', 'string', 'max:255'],
            'specimen' => ['nullable', 'string', 'max:255'],
            'payment_mode' => ['nullable', 'string', Rule::in(PaymentMode::values())],
            'payment_terms' => ['nullable', 'string', Rule::in(PaymentTerms::values())],
            'other_tests' => ['nullable', 'string', 'max:500'],
            'see_attached_file' => ['sometimes', 'boolean'],
            'package_ids' => ['nullable', 'array'],
            'package_ids.*' => ['integer', 'exists:analysis_packages,id'],
            'analysis_type_ids' => ['nullable', 'array'],
            'analysis_type_ids.*' => ['integer', 'exists:analysis_types,id'],
            'samples' => ['required', 'array', 'min:'.$minSamples],
            'samples.*.sample_code' => ['nullable', 'string', 'max:100'],
            'samples.*.description' => ['nullable', 'string', 'max:255'],
            'samples.*.matrix' => ['nullable', 'string', 'max:100'],
            'samples.*.quantity' => ['nullable', 'numeric', 'min:0'],
            'samples.*.unit' => ['nullable', 'string', 'max:50'],
            'samples.*.location' => ['nullable', 'string', 'max:255'],
            'samples.*.sample_point' => ['nullable', 'string', 'max:255'],
            'samples.*.collected_time' => ['nullable', 'string', 'max:50'],
            'samples.*.remarks' => ['nullable', 'string', 'max:500'],
            'attachment' => ['nullable', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp'],
        ]);

        if (($data['payment_mode'] ?? null) === PaymentMode::BillingPartial->value
            && empty($data['payment_terms'])
        ) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'payment_terms' => 'Select payment terms (15 or 30 days) for Billing/Partial.',
            ]);
        }

        if (($data['payment_mode'] ?? null) !== PaymentMode::BillingPartial->value) {
            $data['payment_terms'] = null;
        }

        if ($requireTests) {
            $packageIds = array_values(array_unique(array_map('intval', $data['package_ids'] ?? [])));
            $typeIds = array_values(array_unique(array_map('intval', $data['analysis_type_ids'] ?? [])));
            if ($packageIds === [] && $typeIds === [] && empty($data['other_tests'])) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'analysis_type_ids' => 'Select at least one analysis, package, or describe other tests.',
                ]);
            }
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function headerPayload(array $data): array
    {
        return [
            'customer_name' => $data['customer_name'],
            'customer_email' => $data['customer_email'] ?? null,
            'customer_contact' => $data['customer_contact'] ?? null,
            'customer_address' => $data['customer_address'] ?? null,
            'company_name' => $data['company_name'] ?? null,
            'ownership_type' => $data['ownership_type'] ?? null,
            'classification' => $data['classification'],
            'sampling_date' => $data['sampling_date'] ?? null,
            'sampling_time' => $data['sampling_time'] ?? null,
            'sample_collected_by' => $data['sample_collected_by'] ?? null,
            'field_data' => $data['field_data'] ?? null,
            'sample_storage_temp' => $data['sample_storage_temp'] ?? null,
            'wastewater_source' => $data['wastewater_source'] ?? null,
            'sampling_site' => $data['sampling_site'] ?? null,
            'specimen' => $data['specimen'] ?? null,
            'payment_mode' => $data['payment_mode'] ?? null,
            'payment_terms' => $data['payment_terms'] ?? null,
            'other_tests' => $data['other_tests'] ?? null,
            // JO/RFA prints "see attached file"; encoded sample names stay in samples.description for results.
            'see_attached_file' => (bool) ($data['see_attached_file'] ?? false),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<int>  $packageIds
     * @param  list<int>  $typeIds
     * @return list<array<string, mixed>>
     */
    private function mapSampleRows(
        array $data,
        bool $withTests,
        array $packageIds = [],
        array $typeIds = [],
    ): array {
        $samples = [];

        foreach ($data['samples'] as $row) {
            // Keep receiver-encoded names for result sheets. JO overlay uses see_attached_file.
            $description = trim((string) ($row['description'] ?? ''));

            $mapped = [
                'sample_code' => $row['sample_code'] ?? null,
                'description' => $description !== '' ? $description : null,
                'matrix' => $row['matrix'] ?? 'Water',
                'quantity' => $row['quantity'] ?? null,
                'unit' => $row['unit'] ?? null,
                'remarks' => $this->composeSampleRemarks($row),
            ];

            if ($withTests) {
                $mapped['package_ids'] = $packageIds;
                $mapped['analysis_type_ids'] = $typeIds;
            }

            $samples[] = $mapped;
        }

        return $samples;
    }

    private function storeAttachment(Request $request, JobOrder $jobOrder): void
    {
        if (! $request->hasFile('attachment')) {
            return;
        }

        $file = $request->file('attachment');
        $dir = 'job_attachments/'.$jobOrder->id;
        $stored = $file->store($dir, 'local');
        $jobOrder->update([
            'attachment_path' => $stored,
            'attachment_original_name' => $file->getClientOriginalName(),
        ]);
    }

    /**
     * @return array{location: string, sample_point: string, collected_time: string}
     */
    private function parseSampleRemarks(string $remarks): array
    {
        $out = [
            'location' => '',
            'sample_point' => '',
            'collected_time' => '',
        ];

        foreach (explode(' | ', $remarks) as $part) {
            $part = trim($part);
            if (str_starts_with($part, 'Location: ')) {
                $out['location'] = trim(substr($part, 10));
            } elseif (str_starts_with($part, 'Sample point: ')) {
                $out['sample_point'] = trim(substr($part, 14));
            } elseif (str_starts_with($part, 'Collected: ')) {
                $out['collected_time'] = trim(substr($part, 11));
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function composeSampleRemarks(array $row): ?string
    {
        $parts = [];
        $location = trim((string) ($row['location'] ?? ''));
        $point = trim((string) ($row['sample_point'] ?? ''));
        $time = trim((string) ($row['collected_time'] ?? ''));
        $extra = trim((string) ($row['remarks'] ?? ''));

        if ($location !== '') {
            $parts[] = 'Location: '.$location;
        }
        if ($point !== '') {
            $parts[] = 'Sample point: '.$point;
        }
        if ($time !== '') {
            $parts[] = 'Collected: '.$time;
        }
        if ($extra !== '') {
            $parts[] = $extra;
        }

        if ($parts === []) {
            return null;
        }

        return implode(' | ', $parts);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function kioskPackages(): array
    {
        return AnalysisPackage::query()
            ->where('is_active', true)
            ->with(['analysisTypes', 'category'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (AnalysisPackage $package) => [
                'id' => $package->id,
                'code' => $package->code,
                'name' => $package->name,
                'description' => $package->description,
                'default_price' => $package->default_price,
                'form_code' => $package->form_code,
                'report_layout' => $package->report_layout?->value ?? 'controlled_form',
                'classifications' => $package->classifications ?? [],
                'category' => $package->category?->slug,
                'category_label' => $package->category?->name,
                'analysis_type_ids' => $package->orderedTypeIds(),
                'visible_for_aqua' => $package->visibleForAqua(true),
                'visible_for_non_aqua' => $package->visibleForAqua(false),
                'tests' => $package->analysisTypes->map(fn (AnalysisType $type) => [
                    'id' => $type->id,
                    'code' => $type->code,
                    'name' => $type->name,
                    'default_price' => $type->default_price,
                    'catalog_scope' => $type->catalog_scope ?: 'non_aqua',
                ])->values(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function typePresets(): array
    {
        $meta = self::PRESET_META;
        $metaOrder = array_flip(array_keys($meta));

        return ControlledForm::query()
            ->where('category', ControlledFormCategory::AnalysisResult)
            ->whereNull('analysis_package_id')
            ->with(['currentRevision', 'analysisTypes.category'])
            ->get()
            ->filter(fn (ControlledForm $form): bool => $form->activeRevision() !== null)
            ->map(function (ControlledForm $form) use ($meta): ?array {
                $types = $form->analysisTypes
                    ->filter(fn (AnalysisType $type): bool => $type->is_active && $type->show_on_kiosk)
                    ->sortBy(fn (AnalysisType $type): int => (int) ($type->pivot->slot ?? 0))
                    ->values();
                if ($types->isEmpty()) {
                    return null;
                }

                $tests = $types->map(fn (AnalysisType $type): array => [
                    'id' => (int) $type->id,
                    'code' => (string) $type->code,
                    'name' => (string) $type->name,
                    'default_price' => $type->default_price,
                    'catalog_scope' => $type->catalog_scope ?: 'non_aqua',
                ])->all();

                $code = (string) $form->form_code;
                $known = $meta[$code] ?? null;
                $label = $known['label'] ?? trim((string) preg_replace('/\s*(Test\s+)?Result\s+Form$/i', '', (string) $form->name));

                return [
                    'key' => $known['key'] ?? Str::slug($code, '_'),
                    'label' => $label !== '' ? $label : (string) $form->name,
                    'description' => "{$code} panel — individual pay, not a package.",
                    'form_code' => $code,
                    'classifications' => $known['classifications'] ?? $types
                        ->map(fn (AnalysisType $type): ?string => $type->category?->name)
                        ->filter()
                        ->unique()
                        ->values()
                        ->all(),
                    'analysis_type_ids' => array_column($tests, 'id'),
                    'tests' => $tests,
                ];
            })
            ->filter()
            ->sortBy(fn (array $preset): string => isset($metaOrder[$preset['form_code']])
                ? sprintf('0_%03d', $metaOrder[$preset['form_code']])
                : '1_'.$preset['form_code'])
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private function otherClassificationOptions(): array
    {
        return AnalysisCategory::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->filter(function (AnalysisCategory $category): bool {
                $enum = AnalysisCategoryEnum::tryFrom($category->slug);
                if (! $enum) {
                    return true;
                }

                return $enum->catalogScope() !== CatalogScope::Aqua;
            })
            ->map(fn (AnalysisCategory $category): string => $category->name)
            ->values()
            ->unique()
            ->values()
            ->all();
    }
}

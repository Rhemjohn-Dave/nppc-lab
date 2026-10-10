<?php

namespace App\Http\Controllers;

use App\Models\AnalysisCategory;
use App\Models\AnalysisPackage;
use App\Models\AnalysisType;
use App\Models\ControlledForm;
use App\Models\Customer;
use App\Models\JobOrder;
use App\Enums\AnalysisCategory as AnalysisCategoryEnum;
use App\Enums\CatalogScope;
use App\Enums\ControlledFormCategory;
use App\Enums\PaymentMode;
use App\Enums\PaymentTerms;
use App\Services\JobOrderService;
use App\Support\OfficialAnalysisCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class IntakeController extends Controller
{
    /**
     * Fixed key/label/classifications for known result panels (order = intake order).
     * Other active types-only result forms get generated values.
     *
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

    public function index(): Response
    {
        return Inertia::render('intake/index', [
            'packages' => $this->kioskPackages(),
        ]);
    }

    public function lookup(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'query' => ['required', 'string', 'min:3', 'max:255'],
        ]);

        $q = $data['query'];

        $previous = Customer::query()
            ->where(function ($builder) use ($q) {
                $builder->where('customer_email', $q)
                    ->orWhere('customer_contact', $q)
                    ->orWhere('customer_name', 'like', "%{$q}%");
            })
            ->latest('id')
            ->first();

        if (! $previous) {
            $previous = JobOrder::query()
                ->where(function ($builder) use ($q) {
                    $builder->where('customer_email', $q)
                        ->orWhere('customer_contact', $q)
                        ->orWhere('customer_name', 'like', "%{$q}%");
                })
                ->latest()
                ->first();
        }

        if (! $previous) {
            return back()->withErrors([
                'query' => 'No previous customer found for that email, contact, or name.',
            ]);
        }

        return redirect()->route('intake.create', [
            'customer_name' => $previous->customer_name,
            'customer_email' => $previous->customer_email,
            'customer_contact' => $previous->customer_contact,
            'customer_address' => $previous->customer_address,
            'company_name' => $previous->company_name,
            'ownership_type' => $previous->ownership_type,
        ]);
    }

    public function create(Request $request): Response
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

        return Inertia::render('intake/wizard', [
            'categories' => $categories,
            'packages' => $this->kioskPackages(),
            'type_presets' => $this->typePresets(),
            'prefill' => [
                'customer_name' => $request->string('customer_name')->toString() ?: null,
                'customer_email' => $request->string('customer_email')->toString() ?: null,
                'customer_contact' => $request->string('customer_contact')->toString() ?: null,
                'customer_address' => $request->string('customer_address')->toString() ?: null,
                'company_name' => $request->string('company_name')->toString() ?: null,
                'ownership_type' => $request->string('ownership_type')->toString() ?: null,
            ],
            'options' => [
                'ownership' => OfficialAnalysisCatalog::ownershipTypes(),
                'classifications' => OfficialAnalysisCatalog::classifications(),
                'other_classification_options' => $this->otherClassificationOptions(),
                'wastewater_sources' => OfficialAnalysisCatalog::potabilitySampleSources(),
                'aqua_sources' => OfficialAnalysisCatalog::aquaSampleSources(),
            ],
        ]);
    }

    /**
     * Non-package intake panels (e.g. FO2 wastewater physico) that only select analysis types.
     *
     * @return list<array{
     *     key: string,
     *     label: string,
     *     description: string,
     *     form_code: string,
     *     classifications: list<string>,
     *     analysis_type_ids: list<int>,
     *     tests: list<array{id: int, code: string, name: string, default_price: mixed, catalog_scope: string}>
     * }>
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

                return [
                    'key' => $known['key'] ?? Str::slug($code, '_'),
                    'label' => $known['label'] ?? $this->presetLabelFromFormName((string) $form->name),
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

    private function presetLabelFromFormName(string $name): string
    {
        $label = trim((string) preg_replace('/\s*(Test\s+)?Result\s+Form$/i', '', $name));

        return $label !== '' ? $label : $name;
    }

    /**
     * Active packages for public intake (landing browse + wizard selection).
     *
     * @return list<array{
     *     id: int,
     *     code: string,
     *     name: string,
     *     description: string|null,
     *     default_price: mixed,
     *     form_code: string|null,
     *     report_layout: string,
     *     classifications: list<string>,
     *     category: string|null,
     *     category_label: string|null,
     *     analysis_type_ids: list<int>,
     *     visible_for_aqua: bool,
     *     visible_for_non_aqua: bool,
     *     tests: list<array{id: int, code: string, name: string, default_price: mixed, catalog_scope: string}>
     * }>
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
     * Non-aqua (and both) analysis category labels for Others classification dropdown.
     *
     * @return list<string>
     */
    private function otherClassificationOptions(): array
    {
        $excludeAquaOnly = AnalysisCategory::query()
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
            ->all();

        return array_values(array_unique($excludeAquaOnly));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_email' => ['required', 'email', 'max:255'],
            'customer_contact' => ['nullable', 'string', 'max:255'],
            'customer_address' => ['nullable', 'string', 'max:500'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'ownership_type' => ['nullable', 'string', 'max:50'],
            'classification' => ['nullable', 'string', 'max:255'],
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
            'samples' => ['required', 'array', 'min:1'],
            'samples.*.sample_code' => ['nullable', 'string', 'max:100'],
            'samples.*.description' => ['nullable', 'string', 'max:255'],
            'samples.*.matrix' => ['nullable', 'string', 'max:100'],
            'samples.*.quantity' => ['nullable', 'numeric', 'min:0'],
            'samples.*.unit' => ['nullable', 'string', 'max:50'],
            'samples.*.remarks' => ['nullable', 'string', 'max:500'],
            'samples.*.analysis_type_ids' => ['nullable', 'array'],
            'samples.*.analysis_type_ids.*' => ['integer', 'exists:analysis_types,id'],
            'samples.*.package_ids' => ['nullable', 'array'],
            'samples.*.package_ids.*' => ['integer', 'exists:analysis_packages,id'],
            'analysis_type_ids' => ['nullable', 'array'],
            'analysis_type_ids.*' => ['integer', 'exists:analysis_types,id'],
            'package_ids' => ['nullable', 'array'],
            'package_ids.*' => ['integer', 'exists:analysis_packages,id'],
        ]);

        if (($data['payment_mode'] ?? null) === PaymentMode::BillingPartial->value
            && empty($data['payment_terms'])
        ) {
            return back()->withErrors([
                'payment_terms' => 'Select payment terms (15 or 30 days) for Billing/Partial.',
            ])->withInput();
        }

        if (($data['payment_mode'] ?? null) !== PaymentMode::BillingPartial->value) {
            $data['payment_terms'] = null;
        }

        $usesLegacyJoLevelSelection = array_key_exists('analysis_type_ids', $data)
            || array_key_exists('package_ids', $data);

        if ($usesLegacyJoLevelSelection) {
            if (empty($data['analysis_type_ids']) && empty($data['package_ids']) && empty($data['other_tests'])) {
                return back()->withErrors([
                    'analysis_type_ids' => 'Select at least one analysis, package, or describe other tests.',
                ])->withInput();
            }
        } else {
            foreach ($data['samples'] as $index => $sample) {
                $hasSelection = ! empty($sample['analysis_type_ids'])
                    || ! empty($sample['package_ids']);
                $hasOtherOnFirst = $index === 0 && ! empty($data['other_tests']);

                if (! $hasSelection && ! $hasOtherOnFirst) {
                    return back()->withErrors([
                        "samples.{$index}.analysis_type_ids" => 'Select at least one analysis or package for sample '.($index + 1).'.',
                    ])->withInput();
                }
            }
        }

        $jobOrder = $this->jobOrders->createFromIntake($data);

        return redirect()->route('intake.success', $jobOrder);
    }

    public function success(JobOrder $jobOrder): Response
    {
        return Inertia::render('intake/success', [
            'jobOrder' => [
                'id' => $jobOrder->id,
                'reference_no' => $jobOrder->reference_no,
                'customer_name' => $jobOrder->customer_name,
            ],
        ]);
    }
}

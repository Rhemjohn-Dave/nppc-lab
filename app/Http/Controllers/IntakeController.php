<?php

namespace App\Http\Controllers;

use App\Models\AnalysisType;
use App\Models\JobOrder;
use App\Services\JobOrderService;
use App\Support\OfficialAnalysisCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class IntakeController extends Controller
{
    public function __construct(private readonly JobOrderService $jobOrders) {}

    public function index(): Response
    {
        return Inertia::render('intake/index');
    }

    public function lookup(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'query' => ['required', 'string', 'min:3', 'max:255'],
        ]);

        $q = $data['query'];

        $previous = JobOrder::query()
            ->where(function ($builder) use ($q) {
                $builder->where('customer_email', $q)
                    ->orWhere('customer_contact', $q)
                    ->orWhere('customer_name', 'like', "%{$q}%");
            })
            ->latest()
            ->first();

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
                ])->values(),
            ])
            ->values();

        return Inertia::render('intake/wizard', [
            'categories' => $categories,
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
                'wastewater_sources' => [
                    'Local water district',
                    'Tank',
                    'Faucet',
                    'Deepwell',
                    'Others',
                ],
            ],
        ]);
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
            'wastewater_source' => ['nullable', 'string', 'max:100'],
            'other_tests' => ['nullable', 'string', 'max:500'],
            'samples' => ['required', 'array', 'min:1'],
            'samples.*.sample_code' => ['nullable', 'string', 'max:100'],
            'samples.*.description' => ['required', 'string', 'max:255'],
            'samples.*.matrix' => ['nullable', 'string', 'max:100'],
            'samples.*.quantity' => ['nullable', 'numeric', 'min:0'],
            'samples.*.unit' => ['nullable', 'string', 'max:50'],
            'samples.*.remarks' => ['nullable', 'string', 'max:500'],
            'analysis_type_ids' => ['nullable', 'array'],
            'analysis_type_ids.*' => ['integer', 'exists:analysis_types,id'],
        ]);

        if (empty($data['analysis_type_ids']) && empty($data['other_tests'])) {
            return back()->withErrors([
                'analysis_type_ids' => 'Select at least one analysis or describe other tests.',
            ])->withInput();
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

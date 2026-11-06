<?php

namespace App\Http\Controllers;

use App\Enums\JobOrderStatus;
use App\Models\EmailLog;
use App\Models\JobOrder;
use App\Services\JobOrderService;
use App\Support\JobOrderFormPresenter;
use App\Support\RfaPdfExporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class ReceivingController extends Controller
{
    public function __construct(private readonly JobOrderService $jobOrders) {}

    public function index(Request $request): Response
    {
        $statusFilter = $request->string('status')->toString();
        $search = trim($request->string('q')->toString());
        $sort = $request->string('sort')->toString();
        if (! in_array($sort, ['oldest', 'newest'], true)) {
            $sort = 'newest';
        }

        $day = $request->string('day')->toString();
        $dayRange = null;
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) && checkdate((int) substr($day, 5, 2), (int) substr($day, 8, 2), (int) substr($day, 0, 4))) {
            $labStart = Carbon::createFromFormat('Y-m-d', $day, (string) config('app.lab_timezone', 'Asia/Manila'))->startOfDay();
            $appTz = (string) config('app.timezone', 'UTC');
            $dayRange = [$labStart->copy()->timezone($appTz), $labStart->copy()->endOfDay()->timezone($appTz)];
        } else {
            $day = '';
        }

        $intakeStatuses = [
            JobOrderStatus::DraftSubmitted,
            JobOrderStatus::PendingJoApproval,
            JobOrderStatus::Priced,
            JobOrderStatus::JoApproved,
        ];
        if ($dayRange !== null) {
            $intakeStatuses[] = JobOrderStatus::InAnalysis;
            $intakeStatuses[] = JobOrderStatus::PendingReview;
        }

        // Bulk drafts live under Receiving → Bulk order until finished.
        $intakeQuery = JobOrder::query()
            ->whereIn('status', $intakeStatuses)
            ->where('is_bulk_draft', false);
        $sentQuery = JobOrder::query()->whereIn('status', [
            JobOrderStatus::InAnalysis,
            JobOrderStatus::PendingReview,
        ]);
        $reviewedQuery = JobOrder::query()
            ->whereNotNull('reviewed_at')
            ->where('status', JobOrderStatus::ReadyForPickup);

        if ($dayRange !== null) {
            $intakeQuery->whereBetween('created_at', $dayRange);
            $sentQuery->whereBetween('created_at', $dayRange);
            $reviewedQuery->whereBetween('created_at', $dayRange);
        }

        $showingReviewed = $statusFilter === 'reviewed';
        $showingSent = $statusFilter === JobOrderStatus::InAnalysis->value;
        $baseQuery = $showingReviewed
            ? $reviewedQuery
            : ($showingSent ? $sentQuery : $intakeQuery);

        $counts = [
            'all' => (clone $intakeQuery)->count(),
            'draft_submitted' => (clone $intakeQuery)
                ->where('status', JobOrderStatus::DraftSubmitted)
                ->count(),
            'pending_jo_approval' => (clone $intakeQuery)
                ->whereIn('status', [
                    JobOrderStatus::PendingJoApproval,
                    JobOrderStatus::Priced,
                ])
                ->count(),
            'jo_approved' => (clone $intakeQuery)
                ->where('status', JobOrderStatus::JoApproved)
                ->count(),
            'in_analysis' => (clone $sentQuery)->count(),
            'reviewed' => (clone $reviewedQuery)->count(),
        ];

        $orders = (clone $baseQuery)
            ->withCount(['analyses', 'samples'])
            ->with(['samples:id,job_order_id,description'])
            ->when(
                ! $showingReviewed && ! $showingSent && in_array($statusFilter, [
                    JobOrderStatus::DraftSubmitted->value,
                    JobOrderStatus::PendingJoApproval->value,
                    JobOrderStatus::JoApproved->value,
                ], true),
                function ($query) use ($statusFilter) {
                    if ($statusFilter === JobOrderStatus::PendingJoApproval->value) {
                        return $query->whereIn('status', [
                            JobOrderStatus::PendingJoApproval,
                            JobOrderStatus::Priced,
                        ]);
                    }

                    return $query->where('status', $statusFilter);
                },
            )
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('reference_no', 'like', "%{$search}%")
                        ->orWhere('customer_name', 'like', "%{$search}%")
                        ->orWhere('company_name', 'like', "%{$search}%")
                        ->orWhere('customer_email', 'like', "%{$search}%")
                        ->orWhere('customer_contact', 'like', "%{$search}%");
                });
            })
            ->when($showingReviewed, function ($query) use ($sort) {
                return $sort === 'oldest'
                    ? $query->oldest('reviewed_at')
                    : $query->latest('reviewed_at');
            })
            ->when(! $showingReviewed, function ($query) use ($sort) {
                return $sort === 'oldest'
                    ? $query->oldest()
                    : $query->latest();
            })
            ->paginate(15)
            ->withQueryString()
            ->through(function (JobOrder $order) {
                $samplesCount = (int) $order->samples_count;
                $namedCount = $order->samples
                    ->filter(fn ($sample) => filled(trim((string) ($sample->description ?? ''))))
                    ->count();
                $isReceivingBulk = (bool) $order->is_receiving_bulk;
                $canEncode = $isReceivingBulk
                    && ! $order->is_bulk_draft
                    && $samplesCount >= 2
                    && in_array($order->status, [
                        JobOrderStatus::DraftSubmitted,
                        JobOrderStatus::PendingJoApproval,
                        JobOrderStatus::Priced,
                        JobOrderStatus::JoApproved,
                    ], true);

                return [
                    'id' => $order->id,
                    'reference_no' => $order->reference_no,
                    'customer_name' => $order->customer_name,
                    'customer_email' => $order->customer_email,
                    'customer_contact' => $order->customer_contact,
                    'company_name' => $order->company_name,
                    'status' => $order->status->value,
                    'status_label' => $order->status->label(),
                    'reviewed' => $order->reviewed_at !== null,
                    'jo_approved' => $order->jo_approved_at !== null,
                    'can_print_rfa' => $order->status->canPrintRfa(),
                    'can_receive' => $order->status->canReceiveSamples(),
                    'total_cost' => $order->total_cost,
                    'analyses_count' => $order->analyses_count,
                    'samples_count' => $samplesCount,
                    'samples_named_count' => $namedCount,
                    'samples_encode_complete' => ! $isReceivingBulk
                        || $samplesCount < 2
                        || $namedCount === $samplesCount,
                    'can_encode_samples' => $canEncode,
                    'samples_encode_url' => $canEncode
                        ? "/receiving/{$order->id}/samples"
                        : null,
                    'is_bulk' => $isReceivingBulk || (bool) $order->is_bulk_draft,
                    'is_bulk_draft' => (bool) $order->is_bulk_draft,
                    'is_laboratory_services' => (bool) $order->is_laboratory_services,
                    'bulk_continue_url' => $order->is_bulk_draft
                        ? "/receiving/bulk-orders/{$order->id}/edit"
                        : null,
                    'created_at' => $order->created_at?->toDayDateTimeString(),
                ];
            });

        return Inertia::render('receiving/index', [
            'orders' => $orders,
            'counts' => $counts,
            'filters' => [
                'q' => $search,
                'status' => $statusFilter,
                'sort' => $sort,
                'day' => $day,
            ],
        ]);
    }

    public function show(JobOrder $jobOrder): Response
    {
        $jobOrder->load([
            'samples',
            'analyses.analysisType',
            'analyses.assignee',
            'packageLinks.analysisPackage.analysisTypes',
            'joApprover',
        ]);

        return Inertia::render('receiving/show', [
            'jobOrder' => $this->transformJobOrder($jobOrder),
            'emailLogs' => EmailLog::summariesFor($jobOrder),
        ]);
    }

    public function editSamples(JobOrder $jobOrder): Response
    {
        abort_unless($this->jobOrders->canEncodeSampleDetails($jobOrder), 404);

        $jobOrder->load(['samples']);
        $named = $this->jobOrders->samplesNamedCount($jobOrder);

        return Inertia::render('receiving/encode-samples', [
            'jobOrder' => [
                'id' => $jobOrder->id,
                'reference_no' => $jobOrder->reference_no,
                'customer_name' => $jobOrder->customer_name,
                'company_name' => $jobOrder->company_name,
                'classification' => $jobOrder->classification,
                'status' => $jobOrder->status->value,
                'status_label' => $jobOrder->status->label(),
                'see_attached_file' => (bool) $jobOrder->see_attached_file,
                'samples_count' => $jobOrder->samples->count(),
                'samples_named_count' => $named,
                'samples_encode_complete' => $named === $jobOrder->samples->count(),
                'show_url' => route('receiving.show', $jobOrder, false),
            ],
            'samples' => $jobOrder->samples->values()->map(function ($sample, int $index) use ($jobOrder) {
                $parsed = $this->parseSampleRemarks((string) ($sample->remarks ?? ''));

                return [
                    'id' => $sample->id,
                    'control_number' => \App\Support\SampleControlNumber::forIndex(
                        (string) $jobOrder->reference_no,
                        $index,
                        $jobOrder->samples->count(),
                    ),
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
        ]);
    }

    public function updateSamples(Request $request, JobOrder $jobOrder): RedirectResponse
    {
        abort_unless($this->jobOrders->canEncodeSampleDetails($jobOrder), 404);

        $data = $request->validate([
            'samples' => ['required', 'array', 'min:2'],
            'samples.*.id' => ['required', 'integer', 'exists:samples,id'],
            'samples.*.sample_code' => ['nullable', 'string', 'max:100'],
            'samples.*.description' => ['nullable', 'string', 'max:255'],
            'samples.*.location' => ['nullable', 'string', 'max:255'],
            'samples.*.sample_point' => ['nullable', 'string', 'max:255'],
            'samples.*.collected_time' => ['nullable', 'string', 'max:50'],
            'samples.*.matrix' => ['nullable', 'string', 'max:100'],
            'samples.*.quantity' => ['nullable', 'numeric', 'min:0'],
            'samples.*.unit' => ['nullable', 'string', 'max:50'],
        ]);

        $updated = $this->jobOrders->updateBulkSampleDetails($jobOrder, $data['samples']);
        $named = $this->jobOrders->samplesNamedCount($updated);
        $total = $updated->samples()->count();

        return redirect()
            ->route('receiving.samples.edit', $updated)
            ->with(
                'success',
                $named === $total
                    ? "All {$total} sample names saved."
                    : "Sample details saved ({$named}/{$total} named). Continue encoding before Send to analysts.",
            );
    }

    public function updatePricing(Request $request, JobOrder $jobOrder): RedirectResponse
    {
        $data = $request->validate([
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.id' => ['required', 'integer', 'exists:job_order_analyses,id'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
            'lines.*.quantity' => ['nullable', 'integer', 'min:1'],
            'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $result = $this->jobOrders->updatePricing(
            $jobOrder,
            $data['lines'],
            $request->user(),
            $data['discount_percent'] ?? null,
        );

        $message = match ($result['outcome']) {
            'queued_for_approval' => 'Pricing saved and sent to Head for Job Order approval.',
            'awaiting_approval' => 'Pricing updated (still awaiting Head JO approval).',
            default => 'Pricing updated.',
        };

        return back()->with('success', $message);
    }

    public function updateLabServices(Request $request, JobOrder $jobOrder): RedirectResponse
    {
        abort_unless($this->jobOrders->canEditLaboratoryServices($jobOrder), 404);

        $data = $request->validate([
            'services' => ['required', 'array', 'min:1'],
            'services.*.id' => ['nullable', 'integer', 'exists:job_order_analyses,id'],
            'services.*.name' => ['required', 'string', 'max:255'],
            'services.*.quantity' => ['nullable', 'integer', 'min:1'],
            'services.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'save_pricing' => ['sometimes', 'boolean'],
            'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $jobOrder = $this->jobOrders->syncLaboratoryServices($jobOrder, $data['services']);

        if ((bool) ($data['save_pricing'] ?? false)) {
            $lines = $jobOrder->analyses->map(fn ($line) => [
                'id' => $line->id,
                'unit_price' => (float) $line->unit_price,
                'quantity' => (int) $line->quantity,
            ])->all();

            $result = $this->jobOrders->updatePricing(
                $jobOrder,
                $lines,
                $request->user(),
                $data['discount_percent'] ?? null,
            );

            $message = match ($result['outcome']) {
                'queued_for_approval' => 'Laboratory services saved and sent to Head for Job Order approval.',
                'awaiting_approval' => 'Laboratory services updated (still awaiting Head JO approval).',
                default => 'Laboratory services and pricing updated.',
            };

            return back()->with('success', $message);
        }

        return back()->with('success', 'Laboratory services saved.');
    }

    public function markLabServicesReady(Request $request, JobOrder $jobOrder): RedirectResponse
    {
        $updated = $this->jobOrders->markLaboratoryServicesReady($jobOrder, $request->user());

        return redirect()
            ->route('receiving.show', $updated)
            ->with('success', "Job order {$updated->reference_no} marked ready for pickup.");
    }

    public function receive(Request $request, JobOrder $jobOrder): RedirectResponse
    {
        $this->jobOrders->receive($jobOrder, $request->user());

        return redirect()->route('receiving.index')->with('success', "Job order {$jobOrder->reference_no} sent to analysts and assigned.");
    }

    public function print(Request $request, JobOrder $jobOrder): Response
    {
        $this->assertCanPrintRfa($jobOrder);

        $jobOrder->load(['samples', 'analyses', 'receiver', 'reviewer']);
        $copies = max(1, min(20, $request->integer('copies', 3)));

        return Inertia::render('rfa/print', [
            'jobOrder' => [
                'id' => $jobOrder->id,
                'reference_no' => $jobOrder->reference_no,
            ],
            'pdfUrl' => "/receiving/{$jobOrder->id}/pdf?inline=1",
            'copies' => $copies,
            'copyLabels' => [
                'Customer copy',
                'Accounting copy',
                'Head file copy',
            ],
            'showResults' => false,
        ]);
    }

    public function pdf(Request $request, JobOrder $jobOrder): HttpResponse
    {
        $this->assertCanPrintRfa($jobOrder);

        $jobOrder->load(['samples', 'analyses', 'receiver', 'reviewer']);

        return RfaPdfExporter::download(
            $jobOrder,
            showResults: false,
            inline: $request->boolean('inline'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function transformJobOrder(JobOrder $jobOrder, bool $withResults = true): array
    {
        return JobOrderFormPresenter::toArray($jobOrder, $withResults);
    }

    private function assertCanPrintRfa(JobOrder $jobOrder): void
    {
        abort_unless(
            $jobOrder->status->canPrintRfa() || $jobOrder->jo_approved_at !== null,
            403,
            'Print the Job Order / RFA after Head approves the JO (3 copies: customer, accounting, Head).',
        );
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
}

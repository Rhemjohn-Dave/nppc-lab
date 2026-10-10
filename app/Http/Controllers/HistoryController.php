<?php

namespace App\Http\Controllers;

use App\Enums\JobOrderStatus;
use App\Models\ControlledFormRevision;
use App\Models\EmailLog;
use App\Models\JobOrder;
use App\Services\AnalysisResultReportResolver;
use App\Support\AnalysisResultReport;
use App\Support\JobOrderFormPresenter;
use App\Support\ResultSignatories;
use App\Support\RfaPdfExporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

class HistoryController extends Controller
{
    public function __construct(
        private readonly AnalysisResultReportResolver $reports,
    ) {}

    public function index(Request $request): Response
    {
        $search = trim($request->string('q')->toString());
        $statusFilter = $request->string('status')->toString();
        $previewUser = $request->user();

        $baseQuery = JobOrder::query()
            ->where('status', JobOrderStatus::ReadyForPickup);

        $counts = [
            'all' => (clone $baseQuery)->count(),
            'unsigned' => (clone $baseQuery)->whereNull('reviewed_at')->count(),
            'signed' => (clone $baseQuery)->whereNotNull('reviewed_at')->count(),
        ];

        $orders = (clone $baseQuery)
            ->with(['reviewer'])
            ->withCount(['analyses', 'samples'])
            ->when($statusFilter === 'unsigned', fn ($query) => $query->whereNull('reviewed_at'))
            ->when($statusFilter === 'signed', fn ($query) => $query->whereNotNull('reviewed_at'))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('reference_no', 'like', "%{$search}%")
                        ->orWhere('customer_name', 'like', "%{$search}%")
                        ->orWhere('company_name', 'like', "%{$search}%")
                        ->orWhere('customer_email', 'like', "%{$search}%")
                        ->orWhere('customer_contact', 'like', "%{$search}%");
                });
            })
            ->latest('updated_at')
            ->paginate(20)
            ->withQueryString()
            ->through(function (JobOrder $order) use ($previewUser) {
                $canPreview = false;
                $resultPdfUrl = null;

                if ($previewUser) {
                    $resolved = $this->reports->forJobOrder($order, $previewUser, withValues: false);
                    $canPreview = $resolved->canPreview()
                        && $resolved->kind === AnalysisResultReport::KIND_COMBINED
                        && $resolved->isOverlay();
                    $resultPdfUrl = $canPreview
                        ? "/history/{$order->id}/combined-pdf"
                        : null;
                }

                return [
                    'id' => $order->id,
                    'reference_no' => $order->reference_no,
                    'customer_name' => $order->customer_name,
                    'customer_email' => $order->customer_email,
                    'customer_contact' => $order->customer_contact,
                    'company_name' => $order->company_name,
                    'classification' => $order->classification,
                    'total_cost' => $order->total_cost,
                    'analyses_count' => $order->analyses_count,
                    'samples_count' => $order->samples_count,
                    'completed_at' => $order->updated_at?->toDayDateTimeString(),
                    'signed_at' => $order->reviewed_at?->toDayDateTimeString(),
                    'signed_by' => $order->reviewer?->name,
                    'is_signed' => $order->reviewed_at !== null,
                    'can_preview_result' => $canPreview,
                    'result_pdf_url' => $resultPdfUrl,
                ];
            });

        return Inertia::render('history/index', [
            'orders' => $orders,
            'counts' => $counts,
            'filters' => [
                'q' => $search,
                'status' => $statusFilter,
            ],
            'canSign' => $request->user()?->hasRole(['admin', 'head_analysis']) ?? false,
        ]);
    }

    public function show(JobOrder $jobOrder): Response
    {
        abort_unless(
            $jobOrder->status === JobOrderStatus::ReadyForPickup,
            404,
        );

        return Inertia::render('history/show', [
            'jobOrder' => array_merge(
                JobOrderFormPresenter::toArray($jobOrder, withResults: true),
                [
                    'is_signed' => $jobOrder->reviewed_at !== null,
                ],
            ),
            'canSign' => request()->user()?->hasRole(['admin', 'head_analysis']) ?? false,
            'emailLogs' => EmailLog::summariesFor($jobOrder),
            'canResendEmail' => request()->user()?->hasRole(['admin', 'receiving']) ?? false,
        ]);
    }

    public function print(JobOrder $jobOrder): Response
    {
        abort_unless(
            $jobOrder->status === JobOrderStatus::ReadyForPickup,
            404,
        );

        return Inertia::render('rfa/print', [
            'jobOrder' => [
                'id' => $jobOrder->id,
                'reference_no' => $jobOrder->reference_no,
            ],
            'pdfUrl' => "/history/{$jobOrder->id}/pdf?inline=1",
            'copies' => 1,
            'showResults' => true,
        ]);
    }

    public function pdf(Request $request, JobOrder $jobOrder): HttpResponse
    {
        abort_unless(
            $jobOrder->status === JobOrderStatus::ReadyForPickup,
            404,
        );

        $jobOrder->load(['samples', 'analyses', 'receiver', 'reviewer']);

        $showResults = $request->has('results')
            ? $request->boolean('results')
            : true;

        return RfaPdfExporter::download(
            $jobOrder,
            showResults: $showResults,
            inline: $request->boolean('inline'),
        );
    }

    public function resultReport(Request $request, JobOrder $jobOrder): JsonResponse
    {
        abort_unless(
            $jobOrder->status === JobOrderStatus::ReadyForPickup,
            404,
        );

        $user = $request->user();
        abort_unless($user !== null, 403);

        $sampleId = $request->integer('sample_id') ?: null;
        $formId = $request->integer('form_id') ?: null;

        $allReports = $this->reports->reportsForJobOrder($jobOrder, $user, withValues: false);
        $resolved = $this->reports->pickReport($allReports, $sampleId, $formId);

        if (
            $resolved->kind === AnalysisResultReport::KIND_COMBINED
            && $resolved->canPreview()
            && ! $resolved->isOverlay()
        ) {
            $withValues = $this->reports->reportsForJobOrder($jobOrder, $user, withValues: true);
            $resolved = $this->reports->pickReport(
                $withValues,
                $sampleId ?: $resolved->sampleId,
                $formId ?: $resolved->controlledForm?->id,
            );
        }

        $isOverlayCombined = $resolved->kind === AnalysisResultReport::KIND_COMBINED
            && $resolved->isOverlay();

        $selectedQuery = http_build_query(array_filter([
            'sample_id' => $resolved->sampleId,
            'form_id' => $resolved->controlledForm?->id,
        ], fn ($value) => $value !== null && $value !== ''));

        $pdfUrl = $isOverlayCombined && $resolved->canPreview()
            ? "/history/{$jobOrder->id}/combined-pdf".($selectedQuery !== '' ? "?{$selectedQuery}" : '')
            : null;

        $baseReportUrl = "/history/{$jobOrder->id}/result-report";
        $reportsManifest = $allReports->map(
            fn (AnalysisResultReport $report) => $this->reportTabManifest($jobOrder, $report, $baseReportUrl),
        )->values()->all();

        $signatory = [];
        if ($resolved->controlledForm !== null && $resolved->canPreview()) {
            $signatory = [
                'signatory' => ResultSignatories::manifestPayload(
                    $jobOrder,
                    $resolved->controlledForm,
                    null,
                    $resolved->analyses,
                ),
            ];
        }

        return response()->json($resolved->manifest(
            '',
            $pdfUrl,
            $resolved->fillMode(),
            [
                ...$signatory,
                'reports' => $reportsManifest,
            ],
        ));
    }

    public function combinedPdf(Request $request, JobOrder $jobOrder): HttpResponse
    {
        abort_unless(
            $jobOrder->status === JobOrderStatus::ReadyForPickup,
            404,
        );

        $user = $request->user();
        abort_unless($user !== null, 403);

        $sampleId = $request->integer('sample_id') ?: null;
        $formId = $request->integer('form_id') ?: null;

        $resolved = $this->reports->pickReport(
            $this->reports->reportsForJobOrder($jobOrder, $user),
            $sampleId,
            $formId,
        );
        abort_unless($resolved->canPreview(), 403);
        abort_unless(
            $resolved->kind === AnalysisResultReport::KIND_COMBINED
            && $resolved->isOverlay(),
            403,
        );

        if ($request->boolean('print')) {
            abort_unless($resolved->canPrint() || $user->hasRole('admin'), 403);
        }

        abort_unless($resolved->controlledRevision instanceof ControlledFormRevision, 403);
        $binary = $this->reports->renderOverlayPdf($resolved);

        return response($binary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$resolved->filename.'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function reportTabManifest(JobOrder $jobOrder, AnalysisResultReport $report, string $baseReportUrl): array
    {
        $query = http_build_query(array_filter([
            'sample_id' => $report->sampleId,
            'form_id' => $report->controlledForm?->id,
        ], fn ($value) => $value !== null && $value !== ''));

        $isOverlay = $report->kind === AnalysisResultReport::KIND_COMBINED && $report->isOverlay();

        return [
            ...$report->summary(),
            'label' => $report->controlNumber
                ? 'Control no. '.$report->controlNumber
                : $report->title,
            'report_url' => $baseReportUrl.($query !== '' ? "?{$query}" : ''),
            'pdf_url' => $isOverlay && $report->canPreview()
                ? "/history/{$jobOrder->id}/combined-pdf".($query !== '' ? "?{$query}" : '')
                : null,
        ];
    }
}

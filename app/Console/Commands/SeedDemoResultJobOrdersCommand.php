<?php

namespace App\Console\Commands;

use App\Enums\ControlledFormCategory;
use App\Enums\JobOrderAnalysisStatus;
use App\Models\ControlledForm;
use App\Models\JobOrder;
use App\Models\JobOrderAnalysis;
use App\Models\User;
use App\Services\JobOrderService;
use App\Support\OfficialAnalysisCatalog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SeedDemoResultJobOrdersCommand extends Command
{
    protected $signature = 'demo:seed-result-jobs
                            {--form=* : Limit to form_code(s)}
                            {--full : Select all bound tests (default is 1 test × 2 samples)}
                            {--fresh : Delete prior demo jobs for this mode before seeding}';

    protected $description = 'Create sample customer job orders with placeholder results for each key result controlled form.';

    /**
     * Default result forms to seed (one JO each).
     *
     * @var list<string>
     */
    private const FORM_CODES = [
        'LSP-7.8-FO26',
        'LSP-7.8-FO27',
        'LSP-7.8-F016-NO2',
        'LSP-7.8-F016-PROX',
        'LSP-7.8-F016-MILK',
        'LSP-7.8-F016-WA',
        'LSP-7.8-F016-CAP',
        'LSP-7.8-FO2',
        'LSP-7.8-FO2A',
        'LSP-7.8-FO3',
        'LSP-7.8-FO37',
        'LSP-7.8-FO4',
        'LSP-7.8-FO5',
    ];

    public function handle(JobOrderService $jobs): int
    {
        $requested = array_values(array_filter($this->option('form')));
        $codes = $requested === []
            ? self::FORM_CODES
            : array_values(array_intersect(self::FORM_CODES, $requested));

        if ($requested !== [] && $codes === []) {
            $this->error('No matching form codes. Allowed: '.implode(', ', self::FORM_CODES));

            return self::FAILURE;
        }

        $receiving = User::query()->where('email', 'receiving@nppc.local')->first();
        $head = User::query()->where('email', 'head@nppc.local')->first();
        $analyst = User::query()->where('email', 'analyst@nppc.local')->first();

        if (! $receiving || ! $head || ! $analyst) {
            $this->error('Missing seed users (receiving@ / head@ / analyst@ nppc.local). Run db:seed first.');

            return self::FAILURE;
        }

        $full = (bool) $this->option('full');
        $modeLabel = $full ? 'full-panel' : 'lean (1 test × 2 samples)';

        if ($this->option('fresh')) {
            $deleted = $this->deletePriorDemoJobs($requested === [] ? null : $codes, $full);
            $this->info("Removed {$deleted} prior {$modeLabel} demo job order(s).");
        }

        $this->line("Seeding mode: {$modeLabel}");

        $rows = [];

        foreach ($codes as $formCode) {
            $email = $this->demoEmail($formCode, $full);

            if (! $this->option('fresh') && JobOrder::query()->where('customer_email', $email)->exists()) {
                $existing = JobOrder::query()->where('customer_email', $email)->latest('id')->first();
                $rows[] = [$formCode, $existing?->reference_no ?? '—', (string) ($existing?->id ?? '—'), 'skipped (exists)'];
                continue;
            }

            $form = ControlledForm::query()
                ->with(['analysisTypes', 'analysisPackage.analysisTypes', 'currentRevision'])
                ->where('category', ControlledFormCategory::AnalysisResult)
                ->where('form_code', $formCode)
                ->first();

            if (! $form) {
                $rows[] = [$formCode, '—', '—', 'skipped (missing form)'];
                continue;
            }

            if (! $form->activeRevision()) {
                $rows[] = [$formCode, '—', '—', 'skipped (no active revision)'];
                continue;
            }

            try {
                $job = $this->seedOne($jobs, $form, $receiving, $head, $analyst, $full);
                $rows[] = [$formCode, $job->reference_no, (string) $job->id, 'created'];
            } catch (ValidationException $e) {
                $msg = collect($e->errors())->flatten()->first() ?? $e->getMessage();
                $rows[] = [$formCode, '—', '—', 'failed: '.$msg];
                $this->warn("{$formCode}: {$msg}");
            } catch (\Throwable $e) {
                $rows[] = [$formCode, '—', '—', 'failed: '.$e->getMessage()];
                $this->warn("{$formCode}: ".$e->getMessage());
            }
        }

        $this->table(['form_code', 'reference_no', 'job_order_id', 'status'], $rows);

        $created = collect($rows)->where(3, 'created')->count();
        $this->info("Done. Created {$created} {$modeLabel} demo job order(s).");

        return self::SUCCESS;
    }

    private function seedOne(
        JobOrderService $jobs,
        ControlledForm $form,
        User $receiving,
        User $head,
        User $analyst,
        bool $full,
    ): JobOrder {
        $typeIds = $form->orderedTypeIds();
        $packageId = $form->analysis_package_id
            ? (int) $form->analysis_package_id
            : null;

        if ($packageId !== null) {
            $form->loadMissing('analysisPackage.analysisTypes');
            $packageTypes = $form->analysisPackage?->orderedTypeIds() ?? [];
            if ($typeIds === []) {
                $typeIds = $packageTypes;
            }
        }

        if ($typeIds === []) {
            throw ValidationException::withMessages([
                'form' => 'No bound type IDs.',
            ]);
        }

        $selectedTypeIds = $this->demoTypeIds($form, $typeIds, $full);

        $slug = $this->demoSlug($form->form_code);
        $email = $this->demoEmail($form->form_code, $full);
        $sampleMeta = $this->sampleMetaFor($form->form_code);
        $samples = $this->buildSamples($form->form_code, $sampleMeta);
        $nameSuffix = $full ? ' Full Result' : ' Result';

        $intake = [
            'customer_name' => 'Demo '.strtoupper($slug).$nameSuffix,
            'customer_email' => $email,
            'customer_contact' => '09170000000',
            'customer_address' => 'NPPC Demo Lab',
            'company_name' => 'Demo Result Forms Co',
            'classification' => $sampleMeta['classification'],
            'samples' => $samples,
            'package_ids' => $packageId !== null ? [$packageId] : [],
            'analysis_type_ids' => $selectedTypeIds,
        ];

        $job = $jobs->createFromIntake($intake);
        $job->load('analyses');

        $lines = $job->analyses->map(fn (JobOrderAnalysis $line) => [
            'id' => $line->id,
            'unit_price' => max(1, (float) ($line->unit_price ?: $line->analysisType?->default_price ?: 100)),
            'quantity' => max(1, (int) $line->quantity),
        ])->all();

        $jobs->updatePricing($job, $lines, $receiving);
        $job = $job->fresh() ?? $job;

        $jobs->approveJobOrder($job, $head);
        $job = $jobs->receive($job->fresh() ?? $job, $receiving);

        foreach ($job->analyses as $line) {
            if ((int) $line->assigned_to !== (int) $analyst->id) {
                $line->update([
                    'assigned_to' => $analyst->id,
                    'status' => JobOrderAnalysisStatus::Assigned,
                ]);
            }
        }

        $job = $job->fresh(['analyses.analysisType', 'analyses.assignee']) ?? $job;

        foreach ($job->analyses as $line) {
            if ($line->status === JobOrderAnalysisStatus::Completed) {
                continue;
            }

            $jobs->completeAnalysis($line, $this->placeholderPayload($line), $analyst);
        }

        return $job->fresh() ?? $job;
    }

    /**
     * Lean: first bound type (FO5 always full — fixed slots).
     * Full (--full): every bound type.
     *
     * @param  list<int>  $typeIds
     * @return list<int>
     */
    private function demoTypeIds(ControlledForm $form, array $typeIds, bool $full): array
    {
        if ($typeIds === []) {
            return [];
        }

        if ($full || $form->form_code === 'LSP-7.8-FO5') {
            return array_values($typeIds);
        }

        return [(int) $typeIds[0]];
    }

    /**
     * @return array{classification: string, description: string, matrix: string, sample_count: int}
     */
    private function sampleMetaFor(string $formCode): array
    {
        return match ($formCode) {
            'LSP-7.8-FO2' => [
                'classification' => 'Wastewater',
                'description' => 'River water',
                'matrix' => 'Liquid',
                'sample_count' => 2,
            ],
            'LSP-7.8-FO2A' => [
                'classification' => 'Wastewater',
                'description' => 'Abstracted water',
                'matrix' => 'Liquid',
                'sample_count' => 2,
            ],
            'LSP-7.8-FO3', 'LSP-7.8-FO37', 'LSP-7.8-FO5' => [
                'classification' => 'Potability',
                'description' => 'Drinking water sample',
                'matrix' => 'Liquid',
                'sample_count' => 2,
            ],
            'LSP-7.8-FO4' => [
                'classification' => 'Wastewater',
                'description' => 'Non-potable water',
                'matrix' => 'Liquid',
                'sample_count' => 2,
            ],
            'LSP-7.8-F016-MILK' => [
                'classification' => 'Others: Other Food Analysis',
                'description' => 'Raw milk',
                'matrix' => 'Liquid',
                'sample_count' => 2,
            ],
            'LSP-7.8-F016-PROX' => [
                'classification' => 'Others: Proximate Analysis',
                'description' => 'Feed sample',
                'matrix' => 'Solid',
                'sample_count' => 2,
            ],
            'LSP-7.8-F016-WA' => [
                'classification' => 'Others: Other Food Analysis',
                'description' => 'Dried fruit',
                'matrix' => 'Solid',
                'sample_count' => 2,
            ],
            'LSP-7.8-F016-CAP', 'LSP-7.8-F016-NO2' => [
                'classification' => 'Others: Other Food Analysis',
                'description' => 'Food sample',
                'matrix' => 'Solid',
                'sample_count' => 2,
            ],
            'LSP-7.8-FO26', 'LSP-7.8-FO27' => [
                'classification' => 'Others: Other Food Analysis',
                'description' => 'Food sample',
                'matrix' => 'Solid',
                'sample_count' => 2,
            ],
            default => [
                'classification' => 'Others',
                'description' => 'Demo sample',
                'matrix' => 'Solid',
                'sample_count' => 2,
            ],
        };
    }

    /**
     * @param  array{classification: string, description: string, matrix: string, sample_count: int}  $meta
     * @return list<array{description: string, matrix: string}>
     */
    private function buildSamples(string $formCode, array $meta): array
    {
        $samples = [];
        $count = max(1, (int) $meta['sample_count']);

        for ($i = 0; $i < $count; $i++) {
            $label = $count === 1
                ? $meta['description']
                : $meta['description'].' #'.($i + 1);

            // FO2 / FO2A resolution keys off the exact description string.
            if (in_array($formCode, ['LSP-7.8-FO2', 'LSP-7.8-FO2A'], true)) {
                $label = $meta['description'];
            }

            $samples[] = [
                'description' => $label,
                'matrix' => $meta['matrix'],
            ];
        }

        return $samples;
    }

    /**
     * @return array<string, mixed>
     */
    private function placeholderPayload(JobOrderAnalysis $analysis): array
    {
        $analysis->loadMissing('analysisType');
        $type = $analysis->analysisType;
        $code = $type?->code;

        // Remarks/pass-fail: Passed or Failed only (no demo placeholder text).
        $outcome = ((int) $analysis->id % 2 === 0) ? 'Failed' : 'Passed';

        $payload = [
            'performed_at' => now()->format('Y-m-d\TH:i'),
            'result_method' => OfficialAnalysisCatalog::methodForCode($code) ?? 'Standard Method',
            'result_remarks' => $outcome,
        ];

        if ($analysis->isSamplePreparation()) {
            return $payload;
        }

        if (OfficialAnalysisCatalog::usesRawFinishResults($code)) {
            $payload['result_raw'] = '1.0';
            $payload['result_finish'] = '1.0';
        } elseif (OfficialAnalysisCatalog::usesResidualChlorineResults($code)) {
            $payload['result_finish'] = '0.50';
            $payload['result_chlorinated'] = '0.50';
        } else {
            $payload['result_value'] = '1.0';
        }

        if ($type?->requires_pass_fail || $type?->isPassFail()) {
            $payload['result_pass_fail'] = $outcome;
        }

        return $payload;
    }

    /**
     * @param  list<string>|null  $onlyCodes
     */
    private function deletePriorDemoJobs(?array $onlyCodes, bool $full): int
    {
        $query = JobOrder::query();

        if ($onlyCodes !== null) {
            $emails = array_map(fn (string $code): string => $this->demoEmail($code, $full), $onlyCodes);
            $query->whereIn('customer_email', $emails);
        } elseif ($full) {
            $query->where('customer_email', 'like', 'demo-%-full-result@nppc.local');
        } else {
            $query->where('customer_email', 'like', 'demo-%-result@nppc.local')
                ->where('customer_email', 'not like', 'demo-%-full-result@nppc.local');
        }

        $ids = $query->pluck('id')->all();
        if ($ids === []) {
            return 0;
        }

        return DB::transaction(function () use ($ids) {
            return JobOrder::query()->whereIn('id', $ids)->delete();
        });
    }

    private function demoSlug(string $formCode): string
    {
        $tail = preg_replace('/^LSP-7\.8-/i', '', $formCode) ?? $formCode;

        return strtolower($tail);
    }

    private function demoEmail(string $formCode, bool $full = false): string
    {
        $slug = $this->demoSlug($formCode);

        return $full
            ? "demo-{$slug}-full-result@nppc.local"
            : "demo-{$slug}-result@nppc.local";
    }
}

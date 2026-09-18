<?php

namespace App\Support\Performance;

use App\Models\ControlledForm;
use App\Models\ControlledFormRevision;
use App\Models\User;
use App\Services\ControlledDocumentGenerator;
use App\Services\FieldValueResolver;
use App\Support\Dashboard\DashboardPayloadBuilder;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PerformanceAuditor
{
    public function __construct(
        private readonly DashboardPayloadBuilder $dashboard,
        private readonly FieldValueResolver $resolver,
        private readonly ControlledDocumentGenerator $generator,
        private readonly HttpKernel $kernel,
    ) {}

    /**
     * @return Collection<int, PerformanceMeasurement>
     */
    public function run(): Collection
    {
        return collect([
            $this->benchmarkDashboard('admin', 'admin@nppc.local'),
            $this->benchmarkDashboard('receiving', 'receiving@nppc.local'),
            $this->benchmarkDashboard('analyst', 'analyst@nppc.local'),
            $this->benchmarkDashboard('head', 'head@nppc.local'),
            $this->benchmarkHttp('http.dashboard', 'GET /dashboard', 'admin@nppc.local', '/dashboard'),
            $this->benchmarkHttp('http.receiving.index', 'GET /receiving', 'receiving@nppc.local', '/receiving'),
            $this->benchmarkHttp('http.analyst.index', 'GET /analyst', 'analyst@nppc.local', '/analyst'),
            $this->benchmarkHttp('http.head.jo', 'GET /head/jo', 'head@nppc.local', '/head/jo'),
            $this->benchmarkHttp('http.head.results', 'GET /head/results', 'head@nppc.local', '/head/results'),
            $this->benchmarkFieldResolverSample(),
            $this->benchmarkControlledFormPreview(),
            $this->benchmarkDynamicMatrixPreview(),
        ]);
    }

    /**
     * @return array<string, array{max_ms?: int, max_queries?: int, max_payload_kb?: int}>
     */
    public function budgets(): array
    {
        return config('performance_audit.budgets', []);
    }

    public function duplicateQueryThreshold(): int
    {
        return (int) config('performance_audit.duplicate_query_threshold', 4);
    }

    private function benchmarkDashboard(string $key, string $email): PerformanceMeasurement
    {
        $user = User::query()->where('email', $email)->first();
        if ($user === null) {
            return $this->skipped("dashboard.{$key}", "Dashboard ({$key})", 'User not found — run db:seed');
        }

        return $this->measure("dashboard.{$key}", "Dashboard payload ({$key})", function () use ($user) {
            $payload = $this->dashboard->build($user);

            return [
                'payload' => $payload,
                'payload_bytes' => strlen(json_encode($payload, JSON_THROW_ON_ERROR)),
            ];
        });
    }

    private function benchmarkHttp(string $key, string $label, string $email, string $uri): PerformanceMeasurement
    {
        $user = User::query()->where('email', $email)->first();
        if ($user === null) {
            return $this->skipped($key, $label, 'User not found — run db:seed');
        }

        return $this->measure($key, $label, function () use ($user, $uri) {
            $request = Request::create($uri, 'GET');
            $request->setUserResolver(fn () => $user);

            $response = $this->kernel->handle($request);
            $this->kernel->terminate($request, $response);

            return ['status' => $response->getStatusCode()];
        });
    }

    private function benchmarkFieldResolverSample(): PerformanceMeasurement
    {
        $revision = $this->firstRevisionWithFields();
        if ($revision === null) {
            return $this->skipped(
                'service.field_value_resolver.sample',
                'FieldValueResolver sample values',
                'No controlled form revision with fields',
            );
        }

        return $this->measure(
            'service.field_value_resolver.sample',
            'FieldValueResolver sample values',
            fn () => $this->resolver->sampleValues($revision->load('fields')),
        );
    }

    private function benchmarkControlledFormPreview(): PerformanceMeasurement
    {
        $revision = $this->firstActiveRevisionWithPdf();
        if ($revision === null) {
            return $this->skipped(
                'service.controlled_form.preview',
                'Controlled form PDF preview',
                'No active revision with canonical PDF',
            );
        }

        return $this->measure(
            'service.controlled_form.preview',
            'Controlled form PDF preview',
            function () use ($revision) {
                $result = $this->generator->preview($revision);

                return strlen($result['binary']);
            },
        );
    }

    private function benchmarkDynamicMatrixPreview(): PerformanceMeasurement
    {
        $revision = ControlledFormRevision::query()
            ->whereHas('fields', fn ($q) => $q->where('field_type', 'dynamic_test_matrix'))
            ->whereHas('form')
            ->latest('id')
            ->first();

        if ($revision === null || ! $revision->hasCanonicalPdf()) {
            return $this->skipped(
                'service.dynamic_matrix.preview',
                'Dynamic matrix PDF preview',
                'No revision with dynamic_test_matrix field and canonical PDF',
            );
        }

        return $this->measure(
            'service.dynamic_matrix.preview',
            'Dynamic matrix PDF preview',
            function () use ($revision) {
                $result = $this->generator->preview($revision->load('fields', 'form'));

                return strlen($result['binary']);
            },
        );
    }

    /**
     * @param  callable(): mixed  $callback
     */
    private function measure(string $key, string $label, callable $callback): PerformanceMeasurement
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $memoryBefore = memory_get_usage(true);
        $started = hrtime(true);

        $result = $callback();

        $durationMs = (hrtime(true) - $started) / 1_000_000;
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $payloadBytes = null;
        if (is_array($result) && array_key_exists('payload_bytes', $result)) {
            $payloadBytes = (int) $result['payload_bytes'];
        }

        $warnings = $this->detectDuplicateQueries($queries);

        return new PerformanceMeasurement(
            key: $key,
            label: $label,
            durationMs: $durationMs,
            queryCount: count($queries),
            memoryMb: (memory_get_usage(true) - $memoryBefore) / 1024 / 1024,
            payloadBytes: $payloadBytes,
            warnings: $warnings,
        );
    }

    /**
     * @param  list<array{query: string, bindings: array<int, mixed>, time: float}>  $queries
     * @return list<string>
     */
    private function detectDuplicateQueries(array $queries): array
    {
        $threshold = $this->duplicateQueryThreshold();
        $counts = [];

        foreach ($queries as $entry) {
            $normalized = preg_replace('/\s+/', ' ', trim($entry['query'])) ?? $entry['query'];
            $counts[$normalized] = ($counts[$normalized] ?? 0) + 1;
        }

        $warnings = [];
        foreach ($counts as $sql => $count) {
            if ($count >= $threshold) {
                $warnings[] = sprintf(
                    'Same SQL ran %d times (threshold %d): %s',
                    $count,
                    $threshold,
                    mb_strimwidth($sql, 0, 120, '…'),
                );
            }
        }

        return $warnings;
    }

    private function skipped(string $key, string $label, string $reason): PerformanceMeasurement
    {
        return new PerformanceMeasurement(
            key: $key,
            label: $label,
            durationMs: 0,
            queryCount: 0,
            memoryMb: 0,
            skipped: true,
            warnings: [$reason],
        );
    }

    private function firstRevisionWithFields(): ?ControlledFormRevision
    {
        return ControlledFormRevision::query()
            ->whereHas('fields')
            ->latest('id')
            ->first();
    }

    private function firstActiveRevisionWithPdf(): ?ControlledFormRevision
    {
        $form = ControlledForm::jobOrderForm();

        $revision = $form?->activeRevision();
        if ($revision !== null && $revision->hasCanonicalPdf()) {
            return $revision;
        }

        return ControlledFormRevision::query()
            ->latest('id')
            ->get()
            ->first(fn (ControlledFormRevision $candidate) => $candidate->hasCanonicalPdf());
    }

    /**
     * Used by tests to ensure matrix benchmark has a fixture.
     */
    public function matrixRevisionForAudit(): ?ControlledFormRevision
    {
        return ControlledFormRevision::query()
            ->whereHas('fields', fn ($q) => $q->where('field_type', 'dynamic_test_matrix'))
            ->latest('id')
            ->first();
    }

    /**
     * @return list<string>
     */
    public function summarize(Collection $measurements): array
    {
        $budgets = $this->budgets();
        $lines = [];

        foreach ($measurements as $measurement) {
            if ($measurement->skipped) {
                $lines[] = sprintf('[SKIP] %s — %s', $measurement->label, implode('; ', $measurement->warnings));

                continue;
            }

            $budget = $budgets[$measurement->key] ?? [];
            $violations = $measurement->violations($budget);
            $status = $violations === [] && $measurement->warnings === [] ? 'PASS' : ($violations === [] ? 'WARN' : 'FAIL');

            $lines[] = sprintf(
                '[%s] %s — %.1fms, %d queries, %.2fMB%s',
                $status,
                $measurement->label,
                $measurement->durationMs,
                $measurement->queryCount,
                $measurement->memoryMb,
                $measurement->payloadBytes !== null
                    ? ', '.round($measurement->payloadBytes / 1024, 1).'KB payload'
                    : '',
            );

            foreach ($violations as $violation) {
                $lines[] = '       ↳ '.$violation;
            }

            foreach ($measurement->warnings as $warning) {
                $lines[] = '       ↳ '.$warning;
            }
        }

        return $lines;
    }
}

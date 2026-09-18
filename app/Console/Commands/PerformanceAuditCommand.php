<?php

namespace App\Console\Commands;

use App\Support\Performance\PerformanceAuditor;
use Illuminate\Console\Command;

class PerformanceAuditCommand extends Command
{
    protected $signature = 'nppc:performance-audit
                            {--seed : Run database seeders before auditing}
                            {--fail-on-warn : Treat duplicate-query warnings as failures}';

    protected $description = 'Benchmark critical paths and compare against performance budgets (see config/performance_audit.php).';

    public function handle(PerformanceAuditor $auditor): int
    {
        if ($this->option('seed')) {
            $this->components->info('Seeding database…');
            $this->call('db:seed');
        }

        $this->components->info('Running performance audit…');
        $measurements = $auditor->run();
        $budgets = $auditor->budgets();

        $rows = [];
        $failures = 0;
        $warnings = 0;

        foreach ($measurements as $measurement) {
            if ($measurement->skipped) {
                $rows[] = [
                    $measurement->label,
                    'SKIP',
                    '—',
                    '—',
                    '—',
                    implode('; ', $measurement->warnings),
                ];

                continue;
            }

            $budget = $budgets[$measurement->key] ?? [];
            $violations = $measurement->violations($budget);
            $hasWarn = $measurement->warnings !== [];
            $status = $violations === [] ? ($hasWarn ? 'WARN' : 'PASS') : 'FAIL';

            if ($status === 'FAIL') {
                $failures++;
            } elseif ($status === 'WARN') {
                $warnings++;
            }

            $rows[] = [
                $measurement->label,
                $status,
                number_format($measurement->durationMs, 1).' ms',
                (string) $measurement->queryCount,
                $measurement->payloadBytes !== null
                    ? number_format($measurement->payloadBytes / 1024, 1).' KB'
                    : '—',
                trim(implode(' | ', array_merge($violations, $measurement->warnings))) ?: '—',
            ];
        }

        $this->table(
            ['Check', 'Status', 'Duration', 'Queries', 'Payload', 'Notes'],
            $rows,
        );

        $this->newLine();
        $this->line('Budgets: <comment>config/performance_audit.php</comment>');
        $this->line('Guide:   <comment>docs/PERFORMANCE_AUDIT.md</comment>');
        $this->line('UI rules: <comment>docs/UI_PERFORMANCE.md</comment>');

        if ($failures > 0) {
            $this->components->error("Performance audit failed ({$failures} budget violation(s)).");

            return self::FAILURE;
        }

        if ($warnings > 0 && $this->option('fail-on-warn')) {
            $this->components->error("Performance audit failed ({$warnings} warning(s) with --fail-on-warn).");

            return self::FAILURE;
        }

        if ($warnings > 0) {
            $this->components->warn("Performance audit passed with {$warnings} warning(s). Review duplicate queries or large payloads.");
        } else {
            $this->components->info('Performance audit passed.');
        }

        return self::SUCCESS;
    }
}

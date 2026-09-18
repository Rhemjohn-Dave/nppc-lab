<?php

namespace App\Support\Performance;

readonly class PerformanceMeasurement
{
    /**
     * @param  list<string>  $warnings
     */
    public function __construct(
        public string $key,
        public string $label,
        public float $durationMs,
        public int $queryCount,
        public float $memoryMb,
        public ?int $payloadBytes = null,
        public bool $skipped = false,
        public array $warnings = [],
    ) {}

    /**
     * @param  array{max_ms?: int, max_queries?: int, max_payload_kb?: int}  $budget
     * @return list<string>
     */
    public function violations(array $budget): array
    {
        if ($this->skipped) {
            return [];
        }

        $issues = [];

        if (isset($budget['max_ms']) && $this->durationMs > $budget['max_ms']) {
            $issues[] = sprintf('duration %.1fms exceeds budget %dms', $this->durationMs, $budget['max_ms']);
        }

        if (isset($budget['max_queries']) && $this->queryCount > $budget['max_queries']) {
            $issues[] = sprintf('query count %d exceeds budget %d', $this->queryCount, $budget['max_queries']);
        }

        if (
            isset($budget['max_payload_kb'])
            && $this->payloadBytes !== null
            && $this->payloadBytes > ($budget['max_payload_kb'] * 1024)
        ) {
            $issues[] = sprintf(
                'payload %.1fKB exceeds budget %dKB',
                $this->payloadBytes / 1024,
                $budget['max_payload_kb'],
            );
        }

        return $issues;
    }

    /**
     * @return array<string, mixed>
     */
    public function toReportRow(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'duration_ms' => round($this->durationMs, 1),
            'queries' => $this->queryCount,
            'memory_mb' => round($this->memoryMb, 2),
            'payload_kb' => $this->payloadBytes !== null ? round($this->payloadBytes / 1024, 1) : null,
            'skipped' => $this->skipped,
            'warnings' => $this->warnings,
        ];
    }
}

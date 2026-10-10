<?php

namespace App\Support;

use App\Models\ControlledFormRevision;
use App\Models\JobOrder;
use App\Models\JobOrderAnalysis;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class AnalysisResultReport
{
    public const KIND_COMBINED = 'combined';

    public const KIND_INDIVIDUAL = 'individual';

    public const KIND_WAITING = 'waiting';

    public const KIND_UNAVAILABLE = 'unavailable';

    /**
     * @param  array<string, string|null>  $values
     * @param  Collection<int, JobOrderAnalysis>  $analyses
     */
    public function __construct(
        public readonly string $kind,
        public readonly string $filename,
        public readonly string $title,
        public readonly ?string $message,
        public readonly JobOrder $jobOrder,
        public readonly Collection $analyses,
        public readonly array $values,
        public readonly ?JobOrderAnalysis $analysis = null,
        public readonly mixed $controlledForm = null,
        public readonly mixed $controlledRevision = null,
        public readonly ?int $sampleId = null,
        public readonly ?string $controlNumber = null,
    ) {}

    public function isOverlay(): bool
    {
        if ($this->controlledRevision instanceof ControlledFormRevision) {
            return $this->controlledRevision->isOverlay();
        }

        return false;
    }

    public function fillMode(): ?string
    {
        if ($this->controlledRevision instanceof ControlledFormRevision) {
            return $this->controlledRevision->fill_mode;
        }

        return null;
    }

    public function canPreview(): bool
    {
        return in_array($this->kind, [self::KIND_COMBINED, self::KIND_INDIVIDUAL], true);
    }

    public function canPrint(): bool
    {
        return $this->canPreview() && $this->jobOrder->reviewed_at !== null;
    }

    /**
     * @return array{kind: string, title: string, message: string|null, can_preview: bool, can_print: bool, sample_id: int|null, form_id: int|null, control_number: string|null}
     */
    public function summary(): array
    {
        return [
            'kind' => $this->kind,
            'title' => $this->title,
            'message' => $this->message,
            'can_preview' => $this->canPreview(),
            'can_print' => $this->canPrint(),
            'sample_id' => $this->sampleId,
            'form_id' => $this->controlledForm?->id,
            'control_number' => $this->controlNumber,
        ];
    }

    /**
     * Manifest consumed by the reusable PDF preview dialog.
     *
     * @return array<string, mixed>
     */
    public function manifest(string $templateUrl, ?string $pdfUrl, ?string $fillMode = null, array $extra = []): array
    {
        return [
            'kind' => $this->kind,
            'title' => $this->title,
            'message' => $this->message,
            'can_preview' => $this->canPreview(),
            'can_print' => $this->canPrint(),
            'sample_id' => $this->sampleId,
            'form_id' => $this->controlledForm?->id,
            'control_number' => $this->controlNumber,
            'template_url' => $templateUrl,
            'pdf_url' => $pdfUrl,
            'fill_mode' => $fillMode ?? $this->fillMode(),
            'values' => $this->values,
            'filename' => $this->filename,
            ...$extra,
        ];
    }

    public static function slugFor(JobOrder $jobOrder, ?string $formName = null, ?JobOrderAnalysis $analysis = null, ?string $controlNumber = null): string
    {
        $parts = [];

        if ($controlNumber) {
            $parts[] = Str::slug($controlNumber) ?: 'sample';
        }

        if ($formName) {
            $parts[] = Str::slug($formName) ?: 'combined';
        } elseif ($analysis) {
            $parts[] = Str::slug($analysis->name) ?: 'result';
        } else {
            $parts[] = 'result';
        }

        return implode('-', $parts);
    }
}

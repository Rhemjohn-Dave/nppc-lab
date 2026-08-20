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
     * Compact payload for list screens.
     *
     * @return array{kind: string, title: string, message: string|null, can_preview: bool, can_print: bool}
     */
    public function summary(): array
    {
        return [
            'kind' => $this->kind,
            'title' => $this->title,
            'message' => $this->message,
            'can_preview' => $this->canPreview(),
            'can_print' => $this->canPrint(),
        ];
    }

    /**
     * Manifest consumed by the reusable PDF preview dialog.
     *
     * @return array<string, mixed>
     */
    public function manifest(string $templateUrl, ?string $pdfUrl, ?string $fillMode = null): array
    {
        return [
            'kind' => $this->kind,
            'title' => $this->title,
            'message' => $this->message,
            'can_preview' => $this->canPreview(),
            'can_print' => $this->canPrint(),
            'template_url' => $templateUrl,
            'pdf_url' => $pdfUrl,
            'fill_mode' => $fillMode ?? $this->fillMode(),
            'values' => $this->values,
            'filename' => $this->filename,
        ];
    }

    public static function slugFor(JobOrder $jobOrder, ?string $formName = null, ?JobOrderAnalysis $analysis = null): string
    {
        if ($formName) {
            return Str::slug($formName) ?: 'combined';
        }

        if ($analysis) {
            return Str::slug($analysis->name) ?: 'result';
        }

        return 'result';
    }
}

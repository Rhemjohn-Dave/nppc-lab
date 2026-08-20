<?php

namespace App\Services;

use App\Enums\ControlledFormCategory;
use App\Enums\GeneratedDocumentStatus;
use App\Exceptions\ObsoleteFormRevisionException;
use App\Models\ControlledForm;
use App\Models\ControlledFormRevision;
use App\Models\GeneratedDocument;
use App\Models\JobOrder;
use App\Models\JobOrderAnalysis;
use App\Models\PrintLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ControlledDocumentGenerator
{
    public function __construct(
        private readonly ControlledPdfFiller $filler,
        private readonly FieldValueResolver $resolver,
        private readonly ControlledFormStorage $storage,
        private readonly DocumentNumberService $numbers,
        private readonly DocumentAuditLogger $audit,
        private readonly RevisionWorkflow $workflow,
    ) {}

    /**
     * @return array{binary: string, document: GeneratedDocument|null}
     */
    public function fromJobOrder(
        JobOrder $jobOrder,
        User $user,
        bool $showResults = false,
        bool $persist = false,
        ?ControlledFormRevision $revision = null,
    ): array {
        $form = ControlledForm::jobOrderForm();
        if (! $form) {
            throw new RuntimeException('No job-order controlled form is configured.');
        }

        $revision = $this->workflow->assertCanGenerate($revision, $form);
        $values = $this->resolver->forJobOrder($revision->load('fields'), $jobOrder, $showResults);

        return $this->produce($form, $revision, $jobOrder, $user, $values, $persist);
    }

    /**
     * @param  Collection<int, JobOrderAnalysis>|null  $ordered
     * @return array{binary: string, document: GeneratedDocument|null}
     */
    public function fromResultForm(
        ControlledForm $form,
        JobOrder $jobOrder,
        User $user,
        ?Collection $ordered = null,
        bool $persist = false,
        ?ControlledFormRevision $revision = null,
    ): array {
        $revision = $this->workflow->assertCanGenerate($revision, $form);
        $values = $this->resolver->forResult($revision->load('fields'), $jobOrder, $ordered);

        return $this->produce($form, $revision, $jobOrder, $user, $values, $persist);
    }

    /**
     * @return array{binary: string, document: null}
     */
    public function preview(ControlledFormRevision $revision, ?JobOrder $jobOrder = null, bool $showResults = false): array
    {
        if (! $revision->hasCanonicalPdf()) {
            throw new RuntimeException('This revision has no canonical PDF.');
        }

        $revision->loadMissing('fields', 'form');
        $values = $jobOrder
            ? ($revision->form->category === ControlledFormCategory::AnalysisResult
                ? $this->resolver->forResult($revision, $jobOrder)
                : $this->resolver->forJobOrder($revision, $jobOrder, $showResults))
            : $this->resolver->sampleValues($revision);

        return [
            'binary' => $this->filler->fill($revision, $values),
            'document' => null,
        ];
    }

    public function logPrint(
        GeneratedDocument $document,
        User $user,
        int $copies = 1,
        ?string $printerName = null,
        ?string $ip = null,
    ): PrintLog {
        $log = PrintLog::query()->create([
            'generated_document_id' => $document->id,
            'printed_by' => $user->id,
            'printed_at' => now(),
            'number_of_copies' => max(1, $copies),
            'printer_name' => $printerName,
            'ip_address' => $ip,
        ]);

        $this->audit->record('document.printed', $document, $user, null, [
            'copies' => $copies,
            'printer_name' => $printerName,
        ]);

        return $log;
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array{binary: string, document: GeneratedDocument|null}
     */
    private function produce(
        ControlledForm $form,
        ControlledFormRevision $revision,
        Model $source,
        User $user,
        array $values,
        bool $persist,
    ): array {
        if (! $revision->hasCanonicalPdf()) {
            throw new RuntimeException('The active revision has no canonical PDF.');
        }

        $binary = $this->filler->fill($revision, $values);

        if (! $persist) {
            return ['binary' => $binary, 'document' => null];
        }

        $path = $this->storage->storeGeneratedPdf($binary);
        $absolute = $this->storagePath($path);

        $document = GeneratedDocument::query()->create([
            'document_number' => $this->numbers->next(),
            'controlled_form_id' => $form->id,
            'controlled_form_revision_id' => $revision->id,
            'source_type' => $source->getMorphClass(),
            'source_id' => $source->getKey(),
            'generated_by' => $user->id,
            'generated_at' => now(),
            'pdf_path' => $path,
            'status' => GeneratedDocumentStatus::Final,
            'sha256' => is_file($absolute) ? hash_file('sha256', $absolute) : hash('sha256', $binary),
            'template_sha256' => $revision->sha256,
        ]);

        $this->audit->record('document.generated', $document, $user, null, [
            'document_number' => $document->document_number,
            'form_code' => $form->form_code,
            'revision' => $revision->revision,
        ]);

        return ['binary' => $binary, 'document' => $document];
    }

    private function storagePath(string $relative): string
    {
        return Storage::disk('local')->path($relative);
    }

    public function obsoletePayload(ObsoleteFormRevisionException $e): array
    {
        return $e->payload();
    }
}

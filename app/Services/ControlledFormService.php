<?php

namespace App\Services;

use App\Enums\ControlledFormCategory;
use App\Enums\ControlledFormFieldType;
use App\Enums\ControlledFormRevisionStatus;
use App\Models\AnalysisPackage;
use App\Models\AnalysisType;
use App\Models\ControlledForm;
use App\Models\ControlledFormField;
use App\Models\ControlledFormRevision;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ControlledFormService
{
    public function __construct(
        private readonly ControlledFormStorage $storage,
        private readonly RevisionWorkflow $workflow,
        private readonly DocumentAuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function createForm(array $data, User $user, ?UploadedFile $file = null): ControlledForm
    {
        return DB::transaction(function () use ($data, $user, $file) {
            $form = ControlledForm::query()->create([
                'form_code' => strtoupper(trim((string) $data['form_code'])),
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'department' => $data['department'] ?? null,
                'category' => $data['category'] ?? ControlledFormCategory::JobOrder->value,
                'combination_key' => $data['combination_key'] ?? null,
            ]);

            $revision = $form->revisions()->create([
                'revision' => $this->normalizeRevision((string) ($data['revision'] ?? '01')),
                'status' => ControlledFormRevisionStatus::Draft,
                'effective_date' => $data['effective_date'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $user->id,
                'fill_mode' => $data['fill_mode'] ?? ControlledFormRevision::FILL_MODE_OVERLAY,
            ]);

            $this->applyBindings($form, $data);

            if ($file) {
                $this->attachFile($form, $revision, $file);
            }

            $this->audit->record('form.uploaded', $form, $user, null, [
                'form_code' => $form->form_code,
                'revision' => $revision->revision,
            ]);

            return $form->load(['revisions.creator', 'currentRevision', 'analysisTypes']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateForm(ControlledForm $form, array $data, User $user): ControlledForm
    {
        $old = $form->only(['name', 'description', 'department']);
        $form->fill([
            'name' => $data['name'] ?? $form->name,
            'description' => $data['description'] ?? $form->description,
            'department' => $data['department'] ?? $form->department,
        ]);
        $form->save();

        $this->applyBindings($form, $data);

        $this->audit->record('form.edited', $form, $user, $old, $form->only(['name', 'description', 'department']));

        return $form->fresh(['revisions', 'currentRevision', 'analysisTypes']) ?? $form;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createRevision(ControlledForm $form, array $data, User $user, ?UploadedFile $file = null): ControlledFormRevision
    {
        $revisionNo = $this->normalizeRevision((string) ($data['revision'] ?? $this->nextRevisionNumber($form)));

        if ($form->revisions()->where('revision', $revisionNo)->exists()) {
            throw ValidationException::withMessages([
                'revision' => 'That revision already exists for this form.',
            ]);
        }

        return DB::transaction(function () use ($form, $data, $user, $file, $revisionNo) {
            $previous = $this->revisionToCopyFrom($form, $data);

            $revision = $form->revisions()->create([
                'revision' => $revisionNo,
                'status' => ControlledFormRevisionStatus::Draft,
                'effective_date' => $data['effective_date'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $user->id,
                'fill_mode' => $data['fill_mode'] ?? $previous?->fill_mode ?? ControlledFormRevision::FILL_MODE_OVERLAY,
            ]);

            if ($file) {
                $this->attachFile($form, $revision, $file);
            } elseif ($previous?->hasCanonicalPdf()) {
                $this->copyCanonical($form, $previous, $revision);
            }

            if ($previous && $previous->fields()->exists() && ! ($data['blank_fields'] ?? false)) {
                $this->copyFields($previous, $revision);
            }

            $this->audit->record('form.revision_created', $revision, $user, null, [
                'revision' => $revision->revision,
            ]);

            return $revision->load('fields');
        });
    }

    public function attachFile(ControlledForm $form, ControlledFormRevision $revision, UploadedFile $file): ControlledFormRevision
    {
        if (! $revision->status->isEditable() && $revision->status !== ControlledFormRevisionStatus::Draft) {
            throw ValidationException::withMessages([
                'file' => 'Files can only be replaced on draft revisions.',
            ]);
        }

        $stored = $this->storage->storeUpload($form, $revision, $file);
        $revision->fill($stored);
        $revision->save();

        $this->audit->record('form.uploaded', $revision, request()->user(), null, [
            'original_name' => $revision->original_name,
            'sha256' => $revision->sha256,
        ]);

        return $revision;
    }

    /**
     * @param  list<array<string, mixed>>  $payload
     */
    public function replaceFields(ControlledFormRevision $revision, array $payload, User $user): void
    {
        if (! in_array($revision->status, [
            ControlledFormRevisionStatus::Draft,
            ControlledFormRevisionStatus::ForReview,
            ControlledFormRevisionStatus::ForApproval,
            ControlledFormRevisionStatus::Approved,
            ControlledFormRevisionStatus::Active,
        ], true)) {
            throw ValidationException::withMessages([
                'fields' => 'Field mapping cannot be edited on superseded or archived revisions. Create a new revision instead.',
            ]);
        }

        $allowed = collect(FieldValueResolver::catalog($revision->form->category->value))
            ->pluck('key')
            ->all();

        DB::transaction(function () use ($revision, $payload, $allowed): void {
            $revision->fields()->delete();

            foreach (array_values($payload) as $index => $field) {
                $type = ControlledFormFieldType::tryFrom((string) ($field['field_type'] ?? 'text'))
                    ?? ControlledFormFieldType::Text;
                $source = isset($field['data_source_key']) && is_string($field['data_source_key']) && $field['data_source_key'] !== ''
                    ? $field['data_source_key']
                    : null;

                if ($source !== null && ! FieldValueResolver::isAllowedKey($source) && ! in_array($source, $allowed, true)) {
                    throw ValidationException::withMessages([
                        "fields.{$index}.data_source_key" => 'That data source is not on the approved mapping list.',
                    ]);
                }

                $name = (string) ($field['name'] ?? '');
                if ($name === '') {
                    $name = Str::slug((string) ($field['label'] ?? 'field'), '_').'_'.($index + 1);
                }

                ControlledFormField::query()->create([
                    'controlled_form_revision_id' => $revision->id,
                    'name' => $name,
                    'label' => (string) ($field['label'] ?? $name),
                    'field_type' => $type,
                    'page_number' => max(1, (int) ($field['page_number'] ?? 1)),
                    'x' => (float) ($field['x'] ?? 0),
                    'y' => (float) ($field['y'] ?? 0),
                    'width' => (float) ($field['width'] ?? $type->defaultWidth()),
                    'height' => (float) ($field['height'] ?? $type->defaultHeight()),
                    'font_size' => $field['font_size'] ?? 11,
                    'font_family' => $field['font_family'] ?? 'calibri',
                    'font_color' => $field['font_color'] ?? '#000000',
                    'alignment' => $field['alignment'] ?? 'L',
                    'data_source_key' => $source,
                    'format' => $field['format'] ?? null,
                    'checkbox_true_value' => $field['checkbox_true_value'] ?? null,
                    'options' => $field['options'] ?? null,
                    'table_config' => $field['table_config'] ?? null,
                    'z_order' => (int) ($field['z_order'] ?? $index),
                ]);
            }
        });

        $this->audit->record('field.mapping_changed', $revision, $user, null, [
            'field_count' => count($payload),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function applyBindings(ControlledForm $form, array $data): void
    {
        $packageId = array_key_exists('analysis_package_id', $data)
            ? ($data['analysis_package_id'] !== null && $data['analysis_package_id'] !== ''
                ? (int) $data['analysis_package_id']
                : null)
            : $form->analysis_package_id;

        if ($packageId) {
            $package = AnalysisPackage::query()->find($packageId);
            if ($package) {
                $this->syncBindings($form, $package->orderedTypeIds(), $package->id);

                return;
            }

            $packageId = null;
        }

        if (isset($data['analysis_type_ids']) && is_array($data['analysis_type_ids'])) {
            $this->syncBindings($form, array_map('intval', $data['analysis_type_ids']), null);

            return;
        }

        if (array_key_exists('analysis_package_id', $data)) {
            $this->syncBindings($form, $form->orderedTypeIds(), null);
        }
    }

    /**
     * @param  list<int>  $typeIds
     */
    public function syncBindings(ControlledForm $form, array $typeIds, ?int $packageId = null): void
    {
        $ids = array_values(array_unique(array_map('intval', $typeIds)));

        if ($form->category === ControlledFormCategory::AnalysisResult) {
            $this->assertUniqueResultBinding($form, $packageId, $ids);
        }

        $form->analysisTypes()->detach();

        foreach ($ids as $slot => $typeId) {
            if (! AnalysisType::query()->whereKey($typeId)->exists()) {
                continue;
            }

            $form->analysisTypes()->attach($typeId, ['slot' => $slot + 1]);
        }

        $form->combination_key = $ids === [] ? null : self::combinationKey($ids);
        $form->analysis_package_id = $packageId;
        $form->save();

        if ($packageId) {
            AnalysisPackage::query()->whereKey($packageId)->update([
                'form_code' => $form->form_code,
            ]);
        }
    }

    /**
     * @param  list<int>  $typeIds
     */
    private function assertUniqueResultBinding(ControlledForm $form, ?int $packageId, array $typeIds): void
    {
        if ($packageId) {
            $taken = ControlledForm::query()
                ->where('category', ControlledFormCategory::AnalysisResult)
                ->where('analysis_package_id', $packageId)
                ->where('id', '!=', $form->id)
                ->exists();

            if ($taken) {
                throw ValidationException::withMessages([
                    'analysis_package_id' => 'Another analysis result form is already bound to this package.',
                ]);
            }

            return;
        }

        if ($typeIds === []) {
            return;
        }

        $key = self::combinationKey($typeIds);
        $taken = ControlledForm::query()
            ->where('category', ControlledFormCategory::AnalysisResult)
            ->whereNull('analysis_package_id')
            ->where('combination_key', $key)
            ->where('id', '!=', $form->id)
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages([
                'analysis_type_ids' => 'Another analysis result form is already bound to this exact set of tests.',
            ]);
        }
    }

    public function importRfaBlueprint(ControlledFormRevision $revision): void
    {
        /** @var list<array{name: string, type: string, page: int, x: float, y: float, w: float, h: float, font_size?: float, align?: string}> $fields */
        $fields = config('rfa_form_fields.fields', []);
        $payload = [];

        foreach ($fields as $index => $field) {
            $name = (string) $field['name'];
            $type = ($field['type'] ?? 'text') === 'checkbox'
                ? ControlledFormFieldType::Checkbox->value
                : (($field['type'] ?? '') === 'multiline'
                    ? ControlledFormFieldType::Multiline->value
                    : ControlledFormFieldType::Text->value);

            $payload[] = [
                'name' => $name,
                'label' => $this->labelFromName($name),
                'field_type' => $type,
                'page_number' => (int) ($field['page'] ?? 1),
                'x' => (float) $field['x'],
                'y' => (float) $field['y'],
                'width' => (float) $field['w'],
                'height' => (float) $field['h'],
                'font_size' => $field['font_size'] ?? 11,
                'alignment' => $field['align'] ?? 'L',
                'data_source_key' => $this->rfaDataSource($name),
                'z_order' => $index,
            ];
        }

        // Only apply blueprint page dimensions when the revision has no canonical PDF yet.
        // Once a real PDF is attached, its actual dimensions take precedence.
        if (! $revision->hasCanonicalPdf()) {
            $page = config('rfa_form_fields.page', []);
            if (isset($page['width'])) {
                $revision->page_width_mm = $page['width'];
                $revision->page_height_mm = $page['height'] ?? $revision->page_height_mm;
                $revision->save();
            }
        }

        $this->replaceFields($revision, $payload, $revision->creator ?? User::query()->first());
    }

    private function rfaDataSource(string $name): ?string
    {
        return match (true) {
            $name === 'reference_no' => 'job_orders.reference_no',
            $name === 'customer_name' => 'job_orders.customer_name',
            $name === 'address' => 'job_orders.customer_address',
            $name === 'contact_no' => 'job_orders.customer_contact',
            $name === 'time_date_submitted' => 'job_orders.created_at',
            $name === 'sampling_date' => 'job_orders.sampling_date',
            $name === 'sampling_time' => 'job_orders.sampling_time',
            $name === 'sample_collected_by' => 'job_orders.sample_collected_by',
            $name === 'other_tests' => 'job_orders.other_tests',
            $name === 'billing_total' => 'job_orders.total_cost',
            $name === 'received_date' => 'job_orders.received_at',
            $name === 'reviewed_date' => 'job_orders.reviewed_at',
            $name === 'ownership_private' => 'job_orders.ownership_type:private',
            $name === 'ownership_commercial' => 'job_orders.ownership_type:commercial',
            $name === 'ownership_public' => 'job_orders.ownership_type:public',
            $name === 'class_aqua' => 'job_orders.classification:aqua',
            $name === 'class_potability' => 'job_orders.classification:potability',
            $name === 'class_wastewater' => 'job_orders.classification:wastewater',
            $name === 'class_agriculture' => 'job_orders.classification:agriculture',
            $name === 'class_academic' => 'job_orders.classification:academic',
            $name === 'class_others' => 'job_orders.classification:other',
            $name === 'potability_sterile' => 'job_orders.field_data:sterile_bottle',
            $name === 'ww_local_district' => 'job_orders.wastewater_source:district',
            $name === 'ww_faucet' => 'job_orders.wastewater_source:faucet',
            $name === 'ww_tank' => 'job_orders.wastewater_source:tank',
            $name === 'ww_deepwell' => 'job_orders.wastewater_source:deepwell',
            str_starts_with($name, 'chk_') => 'analyses.selected:'.strtoupper(str_replace('_', '-', substr($name, 4))),
            default => $name,
        };
    }

    private function labelFromName(string $name): string
    {
        return Str::headline(str_replace('_', ' ', $name));
    }

    private function copyFields(ControlledFormRevision $from, ControlledFormRevision $to): void
    {
        $from->loadMissing('fields');

        foreach ($from->fields as $field) {
            $clone = $field->replicate();
            $clone->controlled_form_revision_id = $to->id;
            $clone->save();
        }
    }

    private function copyCanonical(ControlledForm $form, ControlledFormRevision $from, ControlledFormRevision $to): void
    {
        $dir = $this->storage->revisionDirectory($form, $to);
        $canonicalDir = $dir.'/canonical';
        $originalDir = $dir.'/original';
        Storage::disk('local')->makeDirectory($canonicalDir);
        Storage::disk('local')->makeDirectory($originalDir);

        $filename = basename((string) $from->canonical_pdf_path);
        $newCanonical = $canonicalDir.'/'.$filename;
        Storage::disk('local')->copy($from->canonical_pdf_path, $newCanonical);

        $newOriginal = $from->original_path
            ? $originalDir.'/'.basename($from->original_path)
            : null;
        if ($from->original_path && $newOriginal) {
            Storage::disk('local')->copy($from->original_path, $newOriginal);
        }

        $to->fill([
            'original_name' => $from->original_name,
            'original_path' => $newOriginal,
            'canonical_pdf_path' => $newCanonical,
            'original_mime' => $from->original_mime,
            'page_count' => $from->page_count,
            'page_width_mm' => $from->page_width_mm,
            'page_height_mm' => $from->page_height_mm,
            'sha256' => $from->sha256,
        ]);
        $to->save();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function revisionToCopyFrom(ControlledForm $form, array $data): ?ControlledFormRevision
    {
        if (! array_key_exists('copy_from_revision_id', $data) || $data['copy_from_revision_id'] === null || $data['copy_from_revision_id'] === '') {
            return $form->currentRevision ?? $form->revisions()->first();
        }

        $source = $form->revisions()->whereKey((int) $data['copy_from_revision_id'])->first();

        if (! $source) {
            throw ValidationException::withMessages([
                'copy_from_revision_id' => 'That revision does not belong to this form.',
            ]);
        }

        return $source;
    }

    public function nextRevisionNumber(ControlledForm $form): string
    {
        $max = $form->revisions()
            ->pluck('revision')
            ->map(fn ($rev) => (int) preg_replace('/\D+/', '', (string) $rev))
            ->max() ?: 0;

        return str_pad((string) ($max + 1), 2, '0', STR_PAD_LEFT);
    }

    private function normalizeRevision(string $revision): string
    {
        $trimmed = trim($revision);
        if ($trimmed === '') {
            return '01';
        }

        if (ctype_digit($trimmed)) {
            return str_pad($trimmed, 2, '0', STR_PAD_LEFT);
        }

        return $trimmed;
    }

    /**
     * Canonical combination key for a set of analysis-type IDs.
     * IDs are sorted ascending so order of selection does not matter.
     *
     * @param  int[]  $ids
     */
    public static function combinationKey(array $ids): string
    {
        $sorted = array_values(array_unique(array_map('intval', $ids)));
        sort($sorted);

        return implode(',', $sorted);
    }
}

<?php

namespace App\Console\Commands;

use App\Enums\ControlledFormFieldType;
use App\Models\ControlledForm;
use App\Models\ControlledFormField;
use Illuminate\Console\Command;

/**
 * FO2 / FO2A / FO2B share the same Issue 18 wastewater PDF.
 * Copy FO2's known-good header + matrix box geometry onto FO2A and FO2B.
 */
class SyncFo2HeaderGeometryCommand extends Command
{
    protected $signature = 'controlled-forms:sync-fo2-header-geometry
                            {--dry-run : Show what would change without saving}';

    protected $description = 'Copy FO2 header/matrix field positions onto FO2A and FO2B (same official PDF).';

    /** @var list<string> */
    private const HEADER_KEYS = [
        'results.customer',
        'results.address',
        'results.sample_description',
        'job_orders.sampling_site',
        'results.control_no',
        'results.collected_by',
        'results.collection_datetime',
        'results.receipt_at',
        'results.analysis_datetime',
        'results.report_date',
        'results.ref_no',
    ];

    public function handle(): int
    {
        $sourceForm = ControlledForm::query()->where('form_code', 'LSP-7.8-FO2')->first();
        if (! $sourceForm) {
            $this->error('LSP-7.8-FO2 not found.');

            return self::FAILURE;
        }

        $sourceRev = $sourceForm->currentRevision ?? $sourceForm->activeRevision();
        if (! $sourceRev) {
            $this->error('FO2 has no current/active revision.');

            return self::FAILURE;
        }

        $sourceFields = $sourceRev->fields()->get()->keyBy(
            fn (ControlledFormField $field) => (string) ($field->data_source_key ?: $field->name),
        );

        $sourceMatrix = $sourceRev->fields()
            ->where('field_type', ControlledFormFieldType::DynamicTestMatrix)
            ->first();

        if ($sourceMatrix === null) {
            $this->error('FO2 has no dynamic_test_matrix field.');

            return self::FAILURE;
        }

        foreach (['LSP-7.8-FO2A', 'LSP-7.8-FO2B'] as $code) {
            $form = ControlledForm::query()->where('form_code', $code)->first();
            if (! $form) {
                $this->warn("{$code} missing — skipped.");

                continue;
            }

            $rev = $form->currentRevision ?? $form->activeRevision();
            if (! $rev) {
                $this->warn("{$code} has no revision — skipped.");

                continue;
            }

            $this->info("{$code} revision {$rev->revision} (id {$rev->id})");

            foreach (self::HEADER_KEYS as $key) {
                /** @var ControlledFormField|null $from */
                $from = $sourceFields->get($key);
                if (! $from) {
                    $this->warn("  FO2 missing source key {$key}");

                    continue;
                }

                $to = $rev->fields()
                    ->where(function ($q) use ($key) {
                        $q->where('data_source_key', $key)->orWhere('name', $key);
                    })
                    ->first();

                if (! $to) {
                    $this->warn("  {$code} missing field {$key}");

                    continue;
                }

                $this->line(sprintf(
                    '  %s  (%.3f,%.3f)->(%.3f,%.3f)',
                    $key,
                    (float) $to->x,
                    (float) $to->y,
                    (float) $from->x,
                    (float) $from->y,
                ));

                if (! $this->option('dry-run')) {
                    $to->fill([
                        'x' => $from->x,
                        'y' => $from->y,
                        'width' => $from->width,
                        'height' => $from->height,
                        'font_size' => $from->font_size,
                        'font_family' => $from->font_family,
                        'alignment' => $from->alignment,
                    ]);
                    $to->save();
                }
            }

            $targetMatrix = $rev->fields()
                ->where('field_type', ControlledFormFieldType::DynamicTestMatrix)
                ->first();

            if ($targetMatrix) {
                $this->line(sprintf(
                    '  matrix  (%.3f,%.3f %.3fx%.3f)->(%.3f,%.3f %.3fx%.3f)',
                    (float) $targetMatrix->x,
                    (float) $targetMatrix->y,
                    (float) $targetMatrix->width,
                    (float) $targetMatrix->height,
                    (float) $sourceMatrix->x,
                    (float) $sourceMatrix->y,
                    (float) $sourceMatrix->width,
                    (float) $sourceMatrix->height,
                ));

                if (! $this->option('dry-run')) {
                    $targetMatrix->fill([
                        'x' => $sourceMatrix->x,
                        'y' => $sourceMatrix->y,
                        'width' => $sourceMatrix->width,
                        'height' => $sourceMatrix->height,
                    ]);
                    $targetMatrix->save();
                }
            }
        }

        if ($this->option('dry-run')) {
            $this->comment('Dry run only — nothing saved.');
        } else {
            $this->info('Done. Hard-refresh Form Designer for FO2A/FO2B, then Preview.');
        }

        return self::SUCCESS;
    }
}

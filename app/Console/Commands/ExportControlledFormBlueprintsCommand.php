<?php

namespace App\Console\Commands;

use App\Models\ControlledForm;
use App\Models\ControlledFormRevision;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ExportControlledFormBlueprintsCommand extends Command
{
    protected $signature = 'controlled-forms:export-blueprints
                            {--form=* : Limit to form_code(s); default = deploy default set}
                            {--pdfs : Also copy ACTIVE canonical PDFs into resources/forms/official}';

    protected $description = 'Export ACTIVE Form Designer fields into config/*_form_fields.php blueprints.';

    /**
     * @var array<string, array{config: string, pdf?: string}>
     */
    public const TARGETS = [
        ControlledForm::RFA_FORM_CODE => [
            'config' => 'rfa_form_fields',
            'pdf' => 'JOB ORDER Issue 11 09012026.pdf',
        ],
        ControlledForm::RFA_AQUA_FORM_CODE => [
            'config' => 'rfa_aqua_form_fields',
            'pdf' => 'aqua job order issue 3 2026.pdf',
        ],
        'LSP-7.8-FO2' => [
            'config' => 'result_ww_physico_form_fields',
            'pdf' => 'PC Wastewater result Form Issue 18 09012026 blank.pdf',
        ],
        'LSP-7.8-FO3' => [
            'config' => 'result_dw_old_fo3_form_fields',
            'pdf' => 'PC OLD Drinking Water Test Result Form Issue 11 blank.pdf',
        ],
        'LSP-7.8-FO37' => [
            'config' => 'result_dw_physico_form_fields',
            'pdf' => 'PC Drinking Water Test Result Form Issue 7 09012026.pdf',
        ],
        'LSP-7.8-FO4' => [
            'config' => 'result_fo4_form_fields',
            'pdf' => 'lsp-7.8-fo4-micro-non-drinking-water.pdf',
        ],
        'LSP-7.8-FO5' => [
            'config' => 'result_fo5_form_fields',
            'pdf' => 'lsp-7.8-fo5-micro-drinking-water.pdf',
        ],
        'LSP-7.8-F016-PROX' => [
            'config' => 'result_proximate_form_fields',
            'pdf' => 'Proximate Analysis Result Form.pdf',
        ],
        'LSP-7.8-FO26' => [
            'config' => 'result_fo26_form_fields',
            'pdf' => 'micro food test result form issue4 07152026.pdf',
        ],
        'LSP-7.8-FO27' => [
            'config' => 'result_fo27_form_fields',
            'pdf' => 'FOOD MICRO SUGAR Test Result form Issue4 07152026.pdf',
        ],
        'LSP-7.8-F016-MILK' => [
            'config' => 'result_milk_form_fields',
            'pdf' => 'Milk Sample Test Result Form.pdf',
        ],
        'LSP-7.8-F016-WA' => [
            'config' => 'result_water_activity_form_fields',
            'pdf' => 'water activity test result form - blank.pdf',
        ],
        'LSP-7.8-F016-CAP' => [
            'config' => 'result_chloramphenicol_form_fields',
            'pdf' => 'Chloramphenicol Test Result Form-blank.pdf',
        ],
        'LSP-7.8-F016-NO2' => [
            'config' => 'result_nitrite_form_fields',
            'pdf' => 'Nitrite Test Result Form-blank.pdf',
        ],
    ];

    public function handle(): int
    {
        $requested = array_values(array_filter($this->option('form')));
        $targets = $requested === []
            ? self::TARGETS
            : array_intersect_key(self::TARGETS, array_flip($requested));

        if ($targets === []) {
            $this->error('No matching form codes in the deploy default set.');

            return self::FAILURE;
        }

        $exported = 0;

        foreach ($targets as $formCode => $meta) {
            $form = ControlledForm::query()->where('form_code', $formCode)->first();
            if (! $form) {
                $this->warn("{$formCode}: form missing — skipped");

                continue;
            }

            $revision = $form->activeRevision() ?? $form->revisions()->latest('id')->first();
            if (! $revision instanceof ControlledFormRevision) {
                $this->warn("{$formCode}: no revision — skipped");

                continue;
            }

            $revision->load('fields');
            if ($revision->fields->isEmpty()) {
                $this->warn("{$formCode}: revision {$revision->revision} has no fields — skipped");

                continue;
            }

            $path = config_path($meta['config'].'.php');
            File::put($path, $this->renderBlueprintFile($formCode, $revision));
            $this->info("{$formCode}: wrote {$path} ({$revision->fields->count()} fields from rev {$revision->revision})");
            $exported++;

            if ($this->option('pdfs') && isset($meta['pdf']) && $revision->hasCanonicalPdf()) {
                $dest = resource_path('forms/official/'.$meta['pdf']);
                File::ensureDirectoryExists(dirname($dest));
                File::copy($revision->canonicalAbsolutePath(), $dest);
                $this->info("{$formCode}: copied PDF → {$meta['pdf']}");
            }
        }

        $this->components->info("Exported {$exported} blueprint(s).");

        return self::SUCCESS;
    }

    private function renderBlueprintFile(string $formCode, ControlledFormRevision $revision): string
    {
        $page = $revision->page();
        $fields = [];

        foreach ($revision->fields as $field) {
            $row = [
                'name' => $field->name,
                'label' => $field->label,
                'type' => $field->field_type->value,
                'page' => (int) $field->page_number,
                'x' => round((float) $field->x, 3),
                'y' => round((float) $field->y, 3),
                'w' => round((float) $field->width, 3),
                'h' => round((float) $field->height, 3),
                'font_size' => $field->font_size !== null ? (float) $field->font_size : 11.0,
                'font_family' => $field->font_family ?: 'calibri',
                'font_color' => $field->font_color ?: '#000000',
                'align' => $field->alignment ?: 'L',
            ];

            if (is_string($field->data_source_key) && $field->data_source_key !== '') {
                $row['data_source_key'] = $field->data_source_key;
            }

            if (is_string($field->format) && $field->format !== '') {
                $row['format'] = $field->format;
            }

            if (is_string($field->checkbox_true_value) && $field->checkbox_true_value !== '') {
                $row['checkbox_true_value'] = $field->checkbox_true_value;
            }

            if (is_array($field->options) && $field->options !== []) {
                $row['options'] = $field->options;
            }

            if (is_array($field->table_config) && $field->table_config !== []) {
                $row['table_config'] = $field->table_config;
            }

            $fields[] = $row;
        }

        $payload = [
            'page' => [
                'width' => (float) $page['width'],
                'height' => (float) $page['height'],
                'unit' => 'mm',
            ],
            'fields' => $fields,
        ];

        $exported = var_export($payload, true);
        $header = <<<PHP
<?php

/**
 * Field blueprint for {$formCode}.
 * Captured from Form Designer ACTIVE revision {$revision->revision}
 * via `php artisan controlled-forms:export-blueprints`.
 */

return {$exported};

PHP;

        return $header;
    }
}

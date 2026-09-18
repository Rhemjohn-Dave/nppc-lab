<?php

use App\Enums\ControlledFormFieldType;
use App\Enums\JobOrderVariant;
use App\Models\ControlledForm;
use App\Models\ControlledFormField;
use App\Services\FieldValueResolver;
use Illuminate\Database\Migrations\Migration;

/**
 * General JO sample grid: expand to 16 slots (1–8 left, 9–16 right).
 * Upserts slots 8–16 from blueprint (remaps old right 8/9 → 9/10).
 * Leaves slots 1–7 alone when already present.
 */
return new class extends Migration
{
    public function up(): void
    {
        $form = ControlledForm::query()
            ->where('form_code', ControlledForm::RFA_FORM_CODE)
            ->where(function ($query): void {
                $query->whereNull('job_order_variant')
                    ->orWhere('job_order_variant', JobOrderVariant::General);
            })
            ->first();

        if (! $form) {
            return;
        }

        $config = require config_path('rfa_form_fields.php');
        $fields = is_array($config['fields'] ?? null) ? $config['fields'] : [];
        $byName = collect($fields)
            ->filter(fn ($field) => is_array($field) && isset($field['name']))
            ->keyBy(fn (array $field) => (string) $field['name']);

        foreach ($form->revisions as $revision) {
            $z = (int) $revision->fields()->max('z_order');

            for ($i = 8; $i <= FieldValueResolver::RFA_SAMPLE_TOTAL_SLOTS; $i++) {
                foreach (['sample_code', 'control_number'] as $kind) {
                    $name = "{$kind}_{$i}";
                    $meta = $byName->get($name);
                    if (! is_array($meta)) {
                        continue;
                    }

                    $existing = $revision->fields()->where('name', $name)->first();
                    $payload = [
                        'label' => $kind === 'sample_code'
                            ? "RFA sample line {$i}"
                            : "RFA control number line {$i}",
                        'field_type' => ControlledFormFieldType::Text,
                        'page_number' => (int) ($meta['page'] ?? 1),
                        'x' => (float) $meta['x'],
                        'y' => (float) $meta['y'],
                        'width' => (float) $meta['w'],
                        'height' => (float) $meta['h'],
                        'font_size' => $meta['font_size'] ?? 10,
                        'font_family' => $meta['font_family'] ?? 'calibri',
                        'font_color' => '#000000',
                        'alignment' => $meta['align'] ?? 'L',
                        'data_source_key' => $name,
                    ];

                    if ($existing) {
                        $existing->fill($payload)->save();
                    } else {
                        $z++;
                        ControlledFormField::query()->create(array_merge($payload, [
                            'controlled_form_revision_id' => $revision->id,
                            'name' => $name,
                            'z_order' => $z,
                        ]));
                    }
                }
            }

            $revision->touch();
        }
    }

    public function down(): void
    {
        $form = ControlledForm::query()
            ->where('form_code', ControlledForm::RFA_FORM_CODE)
            ->first();

        if (! $form) {
            return;
        }

        $names = [];
        for ($i = 10; $i <= FieldValueResolver::RFA_SAMPLE_TOTAL_SLOTS; $i++) {
            $names[] = "sample_code_{$i}";
            $names[] = "control_number_{$i}";
        }

        ControlledFormField::query()
            ->whereIn('name', $names)
            ->whereIn('controlled_form_revision_id', $form->revisions()->pluck('id'))
            ->delete();
    }
};

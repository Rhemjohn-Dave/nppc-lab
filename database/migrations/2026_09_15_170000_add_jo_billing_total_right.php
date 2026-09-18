<?php

use App\Enums\ControlledFormFieldType;
use App\Enums\JobOrderVariant;
use App\Models\ControlledForm;
use App\Models\ControlledFormField;
use Illuminate\Database\Migrations\Migration;

/**
 * General JO: dual billing totals — left when ≤10 lines, right when spill.
 * Adds billing_total_right overlay (transparent; no cover fill).
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

        foreach ($form->revisions as $revision) {
            $left = $revision->fields()->where('name', 'billing_total')->first();
            if ($left) {
                $options = is_array($left->options) ? $left->options : [];
                unset($options['cover']);
                $left->options = $options === [] ? null : $options;
                $left->data_source_key = 'billing_total';
                $left->save();
            }

            $right = $revision->fields()->where('name', 'billing_total_right')->first();
            if ($right) {
                $right->fill([
                    'label' => 'Billing total (right)',
                    'field_type' => ControlledFormFieldType::Text,
                    'page_number' => 1,
                    'x' => 160.0,
                    'y' => 273.0,
                    'width' => 38.0,
                    'height' => 3.8,
                    'font_size' => 10,
                    'font_family' => 'calibri',
                    'font_color' => '#000000',
                    'alignment' => 'C',
                    'data_source_key' => 'billing_total_right',
                    'options' => null,
                ])->save();
            } else {
                $z = (int) $revision->fields()->max('z_order') + 1;
                ControlledFormField::query()->create([
                    'controlled_form_revision_id' => $revision->id,
                    'name' => 'billing_total_right',
                    'label' => 'Billing total (right)',
                    'field_type' => ControlledFormFieldType::Text,
                    'page_number' => 1,
                    'x' => 160.0,
                    'y' => 273.0,
                    'width' => 38.0,
                    'height' => 3.8,
                    'font_size' => 10,
                    'font_family' => 'calibri',
                    'font_color' => '#000000',
                    'alignment' => 'C',
                    'data_source_key' => 'billing_total_right',
                    'options' => null,
                    'z_order' => $z,
                ]);
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

        ControlledFormField::query()
            ->where('name', 'billing_total_right')
            ->whereIn('controlled_form_revision_id', $form->revisions()->pluck('id'))
            ->delete();
    }
};

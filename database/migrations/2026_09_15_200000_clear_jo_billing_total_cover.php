<?php

use App\Enums\JobOrderVariant;
use App\Models\ControlledForm;
use Illuminate\Database\Migrations\Migration;

/**
 * General JO billing totals: transparent field boxes (no white cover fill).
 * Coordinates are intentionally unchanged.
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
            foreach (['billing_total', 'billing_total_right'] as $name) {
                $field = $revision->fields()->where('name', $name)->first();
                if (! $field) {
                    continue;
                }

                $options = is_array($field->options) ? $field->options : [];
                unset($options['cover']);
                $field->options = $options === [] ? null : $options;
                $field->save();
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

        foreach ($form->revisions as $revision) {
            foreach (['billing_total', 'billing_total_right'] as $name) {
                $field = $revision->fields()->where('name', $name)->first();
                if (! $field) {
                    continue;
                }

                $options = is_array($field->options) ? $field->options : [];
                $options['cover'] = true;
                $field->options = $options;
                $field->save();
            }
        }
    }
};

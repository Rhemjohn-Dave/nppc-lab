<?php

use App\Enums\JobOrderVariant;
use App\Models\ControlledForm;
use Illuminate\Database\Migrations\Migration;

/**
 * Lift General JO billing totals so cover/text sit above the printed TOTAL underline.
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

                $field->y = 273.0;
                $field->height = 3.8;
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

                $field->y = 274.768;
                $field->height = 5.0;
                $field->save();
            }
        }
    }
};

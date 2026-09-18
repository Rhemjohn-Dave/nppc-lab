<?php

use App\Enums\JobOrderVariant;
use App\Models\ControlledForm;
use Illuminate\Database\Migrations\Migration;

/**
 * Re-clear white cover on General JO billing totals for all revisions
 * (Form Designer can reintroduce cover via options round-trip).
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
                if (! array_key_exists('cover', $options)) {
                    continue;
                }

                unset($options['cover']);
                $field->options = $options === [] ? null : $options;
                $field->save();
            }

            $revision->touch();
        }
    }

    public function down(): void
    {
        // Intentionally empty: restoring cover would hide printed underlines again.
    }
};

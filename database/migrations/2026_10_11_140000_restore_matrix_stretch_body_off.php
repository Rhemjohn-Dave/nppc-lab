<?php

use App\Enums\ControlledFormFieldType;
use App\Models\ControlledFormField;
use App\Models\ControlledFormRevision;
use Illuminate\Database\Migrations\Migration;

/**
 * FO26, FO3 and nitrite result tables print with natural row heights.
 * Re-apply stretch_body = false where it was stripped from table_config.
 */
return new class extends Migration
{
    public function up(): void
    {
        $revisionIds = [];

        ControlledFormField::query()
            ->where('field_type', ControlledFormFieldType::DynamicTestMatrix->value)
            ->whereIn('name', ['food_micro_fo26_matrix', 'dw_old_fo3_matrix', 'nitrite_f016_matrix'])
            ->get()
            ->each(function (ControlledFormField $field) use (&$revisionIds): void {
                $config = is_array($field->table_config) ? $field->table_config : [];
                if (($config['stretch_body'] ?? null) === false) {
                    return;
                }

                $config['stretch_body'] = false;
                $field->table_config = $config;
                $field->save();
                $revisionIds[] = $field->controlled_form_revision_id;
            });

        ControlledFormRevision::query()
            ->whereIn('id', array_unique($revisionIds))
            ->get()
            ->each(fn (ControlledFormRevision $revision) => $revision->touch());
    }

    public function down(): void
    {
        // Non-destructive.
    }
};

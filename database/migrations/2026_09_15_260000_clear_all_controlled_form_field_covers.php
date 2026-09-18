<?php

use App\Models\ControlledFormField;
use Illuminate\Database\Migrations\Migration;

/**
 * House rule: controlled-form overlays are transparent by default.
 * Clear white cover fills on all fields so printed PDF labels stay visible.
 */
return new class extends Migration
{
    public function up(): void
    {
        ControlledFormField::query()
            ->whereNotNull('options')
            ->orderBy('id')
            ->each(function (ControlledFormField $field): void {
                $options = is_array($field->options) ? $field->options : [];
                if (! array_key_exists('cover', $options)) {
                    return;
                }

                unset($options['cover']);
                $field->options = $options === [] ? null : $options;
                $field->save();
            });
    }

    public function down(): void
    {
        // Intentionally empty: restoring cover would hide printed labels again.
    }
};

<?php

namespace App\Models;

use App\Enums\ControlledFormFieldType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $controlled_form_revision_id
 * @property string $name
 * @property string $label
 * @property ControlledFormFieldType $field_type
 * @property int $page_number
 * @property string $x
 * @property string $y
 * @property string $width
 * @property string $height
 * @property string|null $font_size
 * @property string|null $font_family
 * @property string|null $font_color
 * @property string|null $alignment
 * @property string|null $data_source_key
 * @property string|null $format
 * @property string|null $checkbox_true_value
 * @property array<int|string, mixed>|null $options
 * @property array<int|string, mixed>|null $table_config
 * @property int $z_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ControlledFormRevision $revision
 */
class ControlledFormField extends Model
{
    protected $fillable = [
        'controlled_form_revision_id',
        'name',
        'label',
        'field_type',
        'page_number',
        'x',
        'y',
        'width',
        'height',
        'font_size',
        'font_family',
        'font_color',
        'alignment',
        'data_source_key',
        'format',
        'checkbox_true_value',
        'options',
        'table_config',
        'z_order',
    ];

    protected function casts(): array
    {
        return [
            'field_type' => ControlledFormFieldType::class,
            'options' => 'array',
            'table_config' => 'array',
        ];
    }

    /** @return BelongsTo<ControlledFormRevision, $this> */
    public function revision(): BelongsTo
    {
        return $this->belongsTo(ControlledFormRevision::class, 'controlled_form_revision_id');
    }

    /**
     * @return array<string, mixed>
     */
    public function toDesignerArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'label' => $this->label,
            'field_type' => $this->field_type->value,
            'page_number' => $this->page_number,
            'x' => (float) $this->x,
            'y' => (float) $this->y,
            'width' => (float) $this->width,
            'height' => (float) $this->height,
            'font_size' => $this->font_size !== null ? (float) $this->font_size : 11.0,
            'font_family' => $this->font_family ?: 'calibri',
            'font_color' => $this->font_color ?: '#000000',
            'alignment' => $this->alignment ?: 'L',
            'data_source_key' => $this->data_source_key,
            'format' => $this->format,
            'checkbox_true_value' => $this->checkbox_true_value,
            'options' => $this->options,
            'table_config' => $this->table_config,
            'z_order' => $this->z_order,
        ];
    }

    /**
     * Shape expected by the FPDI overlay filler.
     *
     * @return array{name: string, type: string, page: int, x: float, y: float, w: float, h: float, font_size: float, align: string, font_family?: string, font_color?: string, table_config?: array<int|string, mixed>|null}
     */
    public function toOverlayArray(): array
    {
        return [
            'name' => $this->name,
            'type' => $this->field_type->value,
            'page' => $this->page_number,
            'x' => (float) $this->x,
            'y' => (float) $this->y,
            'w' => (float) $this->width,
            'h' => (float) $this->height,
            'font_size' => $this->font_size !== null ? (float) $this->font_size : 11.0,
            'align' => $this->alignment ?: 'L',
            'font_family' => $this->font_family ?: 'calibri',
            'font_color' => $this->font_color ?: '#000000',
            'table_config' => $this->table_config,
        ];
    }
}

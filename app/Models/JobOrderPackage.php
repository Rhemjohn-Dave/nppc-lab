<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @property list<int>|null $selected_type_ids
 * @property list<int>|null $waived_type_ids
 */
class JobOrderPackage extends Pivot
{
    protected $table = 'job_order_packages';

    public $incrementing = true;

    protected function casts(): array
    {
        return [
            'selected_type_ids' => 'array',
            'waived_type_ids' => 'array',
        ];
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $year
 * @property int $last_number
 */
class DocumentNumberCounter extends Model
{
    protected $fillable = [
        'year',
        'last_number',
    ];
}

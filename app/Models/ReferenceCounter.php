<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReferenceCounter extends Model
{
    protected $fillable = [
        'year',
        'last_number',
    ];
}

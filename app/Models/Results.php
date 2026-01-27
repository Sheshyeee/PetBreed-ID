<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Results extends Model
{
    protected $fillable = [
        'scan_id',
        'image',
        'breed',
        'confidence',
        'top_predictions',
    ];

    protected $casts = [
        'top_predictions' => 'array',
        'confidence' => 'float',
    ];
}

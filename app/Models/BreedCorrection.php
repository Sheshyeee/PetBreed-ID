<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BreedCorrection extends Model
{
    use HasFactory;

    protected $fillable = [
        'scan_id',
        'image_path',
        'original_breed',
        'corrected_breed',
        'confidence',
        'status'
    ];
}

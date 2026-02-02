<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Results extends Model
{
    use HasFactory;

    protected $fillable = [
        'scan_id',
        'image',
        'breed',
        'confidence',
        'top_predictions',
        'description',
        'origin_history',
        'health_risks',
        'age_simulation',
        'simulation_data',
    ];

    protected $casts = [
        'top_predictions' => 'array',
        'health_risks' => 'array',
        'origin_history' => 'array',
        'simulation_data' => 'array',
        'confidence' => 'float',
    ];

    /**
     * Get the simulation images with full URLs
     */
    public function getSimulationImagesAttribute()
    {
        $simulations = $this->simulation_data ?? [];

        return [
            '2_years' => $simulations['2_years'] ? asset('storage/' . $simulations['2_years']) : null,
            '5_years' => $simulations['5_years'] ? asset('storage/' . $simulations['5_years']) : null,
            '10_years' => $simulations['10_years'] ? asset('storage/' . $simulations['10_years']) : null,
        ];
    }
}

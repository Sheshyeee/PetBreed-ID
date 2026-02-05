<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Results extends Model
{
    use HasFactory;

    protected $fillable = [
        'scan_id',
        'user_id', // Add this
        'image',
        'breed',
        'confidence',
        'pending', // Add this
        'top_predictions',
        'description',
        'origin_history',
        'health_risks',
        'age_simulation',
        'simulation_data',
        'image_hash',
    ];

    protected $casts = [
        'top_predictions' => 'array',
        'health_risks' => 'array',
        'origin_history' => 'array',
        'simulation_data' => 'array',
        'confidence' => 'float',
    ];

    /**
     * Get the user that owns the scan
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if scan is verified
     */
    public function isVerified()
    {
        return $this->pending === 'verified';
    }

    /**
     * Get the simulation images with full URLs
     */
    public function getSimulationImagesAttribute()
    {
        $simulations = $this->simulation_data ?? [];

        return [
            '1_years' => $simulations['1_years'] ? asset('storage/' . $simulations['1_years']) : null,
            '3_years' => $simulations['3_years'] ? asset('storage/' . $simulations['3_years']) : null,
        ];
    }
}

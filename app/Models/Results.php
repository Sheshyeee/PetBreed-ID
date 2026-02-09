<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Results extends Model
{
    use HasFactory;

    protected $fillable = [
        'scan_id',
        'user_id',
        'image',
        'breed',
        'confidence',
        'pending',
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
        $baseUrl = config('filesystems.disks.object-storage.url');

        return [
            '1_years' => $simulations['1_years']
                ? $baseUrl . '/' . $simulations['1_years']
                : null,
            '3_years' => $simulations['3_years']
                ? $baseUrl . '/' . $simulations['3_years']
                : null,
        ];
    }

    public function getImageUrlAttribute()
    {
        $baseUrl = config('filesystems.disks.object-storage.url');
        return $baseUrl . '/' . $this->image;
    }
}

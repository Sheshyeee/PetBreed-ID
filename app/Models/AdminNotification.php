<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminNotification extends Model
{
    protected $fillable = [
        'type',            // 'appointment_accepted' | 'appointment_rejected'
        'message',
        'breed',
        'scan_id',
        'appointment_id',
        'appointment_date',
        'appointment_time',
        'vet_name',
        'rejection_reason',
        'is_read',
    ];

    protected $casts = [
        'is_read' => 'boolean',
    ];
}

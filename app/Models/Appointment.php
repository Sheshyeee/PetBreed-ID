<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Appointment extends Model
{
    protected $fillable = [
        'scan_id',
        'result_id',
        'user_id',
        'created_by',
        'appointment_date',
        'appointment_time',
        'vet_name',
        'reason',
        'notes',
        'status',
        'rejection_reason',
    ];

    protected $casts = [
        'appointment_date' => 'date',
    ];

    public function result(): BelongsTo
    {
        return $this->belongsTo(Results::class, 'result_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────
    public function scopePending($query)    { return $query->where('status', 'pending'); }
    public function scopeAccepted($query)   { return $query->where('status', 'accepted'); }
    public function scopeRejected($query)   { return $query->where('status', 'rejected'); }
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Appointment extends Model
{
    protected $fillable = [
        'patient_id',
        'reference_id',
        'interconsultation_id',
        'professional_id',
        'specialty_id',
        'scheduled_at',
        'duration_minutes',
        'status',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function reference(): BelongsTo
    {
        return $this->belongsTo(Referral::class, 'reference_id');
    }

    public function interconsultation(): BelongsTo
    {
        return $this->belongsTo(Interconsultation::class);
    }

    public function professional(): BelongsTo
    {
        return $this->belongsTo(Professional::class);
    }

    public function specialty(): BelongsTo
    {
        return $this->belongsTo(Specialty::class);
    }

    public function registrar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function attention(): HasOne
    {
        return $this->hasOne(MedicalAttention::class);
    }
}
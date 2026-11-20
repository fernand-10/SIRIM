<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MedicalAttention extends Model
{
    protected $fillable = [
        'patient_id',
        'reference_id',
        'interconsultation_id',
        'appointment_id',
        'professional_id',
        'specialty_id',
        'attended_at',
        'reason',
        'clinical_notes',
        'outcome',
        'plan',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'attended_at' => 'datetime',
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

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
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
}
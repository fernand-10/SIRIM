<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Referral extends Model
{
    protected $fillable = [
        'code',
        'patient_id',
        'origin_facility_id',
        'receiving_facility_id',
        'requested_specialty_id',
        'source_professional_name',
        'registered_by',
        'referred_at',
        'reason',
        'clinical_summary',
        'priority',
        'status',
        'received_at',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'referred_at' => 'datetime',
            'received_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    // Paciente relacionado con la referencia
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    // Establecimiento de origen
    public function originFacility(): BelongsTo
    {
        return $this->belongsTo(HealthFacility::class, 'origin_facility_id');
    }

    // Hospital receptor
    public function receivingFacility(): BelongsTo
    {
        return $this->belongsTo(HealthFacility::class, 'receiving_facility_id');
    }

    // Especialidad solicitada
    public function specialty(): BelongsTo
    {
        return $this->belongsTo(Specialty::class, 'requested_specialty_id');
    }

    // Usuario que registró la referencia
    public function registrar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    // Interconsultas vinculadas a la referencia
    public function interconsultations(): HasMany
    {
        return $this->hasMany(Interconsultation::class, 'reference_id');
    }

    // Citas relacionadas
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class, 'reference_id');
    }

    // Atenciones relacionadas
    public function attentions(): HasMany
    {
        return $this->hasMany(MedicalAttention::class, 'reference_id');
    }

    // Historial de cambios de estado
    public function statusHistories(): HasMany
    {
        return $this->hasMany(StatusHistory::class, 'entity_id')
            ->where('entity_type', 'referencia');
    }

    // Nombre descriptivo del estado
    public function statusLabel(): string
    {
        return [
            'recibida' => 'Recibida',
            'en_revision' => 'En revisión',
            'programada' => 'Programada',
            'en_atencion' => 'En atención',
            'cerrada' => 'Cerrada',
            'rechazada' => 'Rechazada',
        ][$this->status] ?? $this->status;
    }
}
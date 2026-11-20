<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

class Interconsultation extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'reference_id',
        'patient_id',
        'parent_interconsultation_id',
        'originating_attention_id',
        'requested_by',
        'from_specialty_id',
        'to_specialty_id',
        'assigned_professional_id',
        'reason',
        'clinical_summary',
        'priority',
        'status',
        'requested_at',
        'scheduled_at',
        'answered_at',
        'response_summary',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'requested_at' => 'datetime',
            'scheduled_at' => 'datetime',
            'answered_at' => 'datetime',
            'closed_at' => 'datetime',
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

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /** Perfil profesional de quien solicita, cuando el usuario es médico. */
    public function requestingProfessional(): HasOneThrough
    {
        return $this->hasOneThrough(
            Professional::class,
            User::class,
            'id',
            'user_id',
            'requested_by',
            'id'
        );
    }

    public function fromSpecialty(): BelongsTo
    {
        return $this->belongsTo(Specialty::class, 'from_specialty_id');
    }

    public function toSpecialty(): BelongsTo
    {
        return $this->belongsTo(Specialty::class, 'to_specialty_id');
    }

    /** Alias conservado porque algunas vistas usan specialty. */
    public function specialty(): BelongsTo
    {
        return $this->toSpecialty();
    }

    public function assignedProfessional(): BelongsTo
    {
        return $this->belongsTo(Professional::class, 'assigned_professional_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_interconsultation_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_interconsultation_id');
    }

    public function originatingAttention(): BelongsTo
    {
        return $this->belongsTo(MedicalAttention::class, 'originating_attention_id');
    }

    public function attentions(): HasMany
    {
        return $this->hasMany(MedicalAttention::class, 'interconsultation_id');
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class, 'interconsultation_id');
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(StatusHistory::class, 'entity_id')
            ->where('entity_type', 'interconsulta');
    }

    public function getCodeDisplayAttribute(): string
    {
        return $this->code ?: 'INT-' . str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'pendiente' => 'Pendiente',
            'programada' => 'Programada',
            'atendida' => 'Atendida',
            'cancelada' => 'Cancelada',
            default => ucfirst((string) ($this->status ?? 'Desconocido')),
        };
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProfessionalSchedule extends Model
{
    protected $fillable = [
        'professional_id', 'day_of_week', 'start_time', 'end_time',
        'slot_duration', 'is_active',
    ];

    protected function casts(): array
    {
        return ['day_of_week' => 'integer', 'slot_duration' => 'integer', 'is_active' => 'boolean'];
    }

    public function professional(): BelongsTo
    {
        return $this->belongsTo(Professional::class);
    }
}

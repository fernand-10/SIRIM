<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'role'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function professional(): HasOne
    {
        return $this->hasOne(Professional::class);
    }

    public function roleLabel(): string
    {
        return match ($this->role) {
            'admin' => 'Administrador',
            'admission', 'admissionist' => 'Personal de Admisión',
            'doctor' => 'Médico / Profesional',
            'coordinator' => 'Coordinación',
            default => $this->role ?? 'Sin rol',
        };
    }

    public function hasRole(...$roles): bool
    {
        if (in_array('admissionist', $roles, true)) {
            $roles[] = 'admission';
        }

        if (in_array('admission', $roles, true)) {
            $roles[] = 'admissionist';
        }

        return in_array($this->role, $roles, true);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isDoctor(): bool
    {
        return $this->role === 'doctor';
    }

    public function isAdmissionist(): bool
    {
        return in_array($this->role, ['admission', 'admissionist'], true);
    }

    public function isCoordinator(): bool
    {
        return $this->role === 'coordinator';
    }

    public function isPatient(): bool
    {
        // SIRIM es de uso interno; se conserva el método por compatibilidad,
        // pero no se habilitan cuentas de pacientes.
        return false;
    }
}

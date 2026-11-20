<?php

namespace App\Policies;

use App\Models\Appointment;
use App\Models\User;

class AppointmentPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasRole('admission', 'doctor', 'coordinator');
    }

    public function view(User $user, Appointment $appointment): bool
    {
        if ($user->isAdmissionist() || $user->isCoordinator()) {
            return true;
        }

        return $user->isDoctor()
            && (int) $user->professional?->id === (int) $appointment->professional_id;
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admission', 'doctor', 'coordinator')
            && (!$user->isDoctor() || $user->professional()->exists());
    }

    public function update(User $user, Appointment $appointment): bool
    {
        if ($user->isAdmissionist() || $user->isCoordinator()) {
            return true;
        }

        return $user->isDoctor()
            && (int) $user->professional?->id === (int) $appointment->professional_id;
    }
}

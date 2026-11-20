<?php

namespace App\Policies;

use App\Models\Interconsultation;
use App\Models\User;

class InterconsultationPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasRole('admission', 'doctor', 'coordinator');
    }

    public function view(User $user, Interconsultation $interconsultation): bool
    {
        if ($user->isAdmissionist() || $user->isCoordinator()) {
            return true;
        }

        if (!$user->isDoctor() || !$user->professional) {
            return false;
        }

        return (int) $interconsultation->assigned_professional_id === (int) $user->professional->id
            || (int) $interconsultation->requested_by === (int) $user->id;
    }

    public function create(User $user): bool
    {
        return $user->hasRole('doctor', 'coordinator')
            && (!$user->isDoctor() || $user->professional()->exists());
    }

    public function update(User $user, Interconsultation $interconsultation): bool
    {
        if ($user->isCoordinator()) {
            return true;
        }

        return $user->isDoctor()
            && $user->professional
            && (
                (int) $interconsultation->assigned_professional_id === (int) $user->professional->id
                || (int) $interconsultation->requested_by === (int) $user->id
            );
    }
}

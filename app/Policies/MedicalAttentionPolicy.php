<?php

namespace App\Policies;

use App\Models\MedicalAttention;
use App\Models\User;

class MedicalAttentionPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->isDoctor() && $user->professional()->exists();
    }

    public function view(User $user, MedicalAttention $attention): bool
    {
        if (!$user->isDoctor()) {
            return false;
        }

        return (int) $user->professional?->id === (int) $attention->professional_id;
    }

    public function create(User $user): bool
    {
        return $user->isDoctor() && $user->professional()->exists();
    }

    public function update(User $user, MedicalAttention $attention): bool
    {
        // Las atenciones cerradas no se editan silenciosamente.
        return false;
    }

    public function delete(User $user, MedicalAttention $attention): bool
    {
        return false;
    }

    public function restore(User $user, MedicalAttention $attention): bool
    {
        return false;
    }

    public function forceDelete(User $user, MedicalAttention $attention): bool
    {
        return false;
    }
}

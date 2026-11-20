<?php

namespace App\Policies;

use App\Models\Referral;
use App\Models\User;

class ReferralPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasRole('admission', 'doctor', 'coordinator');
    }

    public function view(User $user, Referral $referral): bool
    {
        return $user->hasRole('admission', 'doctor', 'coordinator');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admission', 'doctor', 'coordinator');
    }

    public function update(User $user, Referral $referral): bool
    {
        return $user->hasRole('admission', 'coordinator');
    }

    public function delete(User $user, Referral $referral): bool
    {
        return false;
    }

    public function restore(User $user, Referral $referral): bool
    {
        return false;
    }

    public function forceDelete(User $user, Referral $referral): bool
    {
        return false;
    }
}

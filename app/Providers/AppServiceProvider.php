<?php

namespace App\Providers;

use App\Models\Appointment;
use App\Models\Interconsultation;
use App\Models\MedicalAttention;
use App\Models\Referral;
use App\Models\User;
use App\Policies\AppointmentPolicy;
use App\Policies\InterconsultationPolicy;
use App\Policies\MedicalAttentionPolicy;
use App\Policies\ReferralPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Interceptor global: los administradores tienen permiso total automáticamente
        Gate::before(function (User $user, string $ability) {
            if ($user->isAdmin()) {
                return true;
            }
        });

        // Mapeo explicito de políticas de autorización del sistema SIRIM
        Gate::policy(Appointment::class, AppointmentPolicy::class);
        Gate::policy(MedicalAttention::class, MedicalAttentionPolicy::class);
        Gate::policy(Referral::class, ReferralPolicy::class);
        Gate::policy(Interconsultation::class, InterconsultationPolicy::class);
    }
}
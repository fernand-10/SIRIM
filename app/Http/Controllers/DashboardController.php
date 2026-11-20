<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Interconsultation;
use App\Models\Patient;
use App\Models\Referral;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
        $interconsultationsQuery = Interconsultation::with(['patient', 'fromSpecialty', 'toSpecialty', 'requester']);
        $appointmentsQuery = Appointment::query()->whereDate('scheduled_at', today());

        if ($user->isDoctor() && $user->professional) {
            $interconsultationsQuery->where(function ($q) use ($user) {
                $q->where('requested_by', $user->id)
                    ->orWhere('assigned_professional_id', $user->professional->id);
            });
            $appointmentsQuery->where('professional_id', $user->professional->id);
        }

        return view('dashboard', [
            'totalPatients' => Patient::count(),
            'activeReferrals' => Referral::whereNotIn('status', ['cerrada', 'rechazada'])->count(),
            'pendingInterconsultations' => (clone $interconsultationsQuery)->whereIn('status', ['pendiente', 'programada'])->count(),
            'todayAppointments' => (clone $appointmentsQuery)->whereIn('status', ['programada', 'confirmada'])->count(),
            'recentReferrals' => Referral::with(['patient', 'originFacility', 'specialty'])->latest()->take(6)->get(),
            'recentInterconsultations' => $interconsultationsQuery
                ->whereIn('status', ['pendiente', 'programada'])
                ->orderBy('requested_at')->take(6)->get(),
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Interconsultation;
use App\Models\Referral;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $dates = $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
        ]);
        $startDate = $dates['start_date'] ?? now()->startOfMonth()->toDateString();
        $endDate = $dates['end_date'] ?? now()->endOfMonth()->toDateString();
        if ($startDate > $endDate) {
            throw ValidationException::withMessages(['end_date' => 'La fecha final no puede ser anterior a la fecha inicial.']);
        }

        $from = $startDate . ' 00:00:00';
        $to = $endDate . ' 23:59:59';

        $referralStats = Referral::select('status', DB::raw('COUNT(*) as total'))
            ->whereBetween('created_at', [$from, $to])
            ->groupBy('status')->orderBy('status')->get();

        $specialtyStats = DB::table('interconsultations')
            ->join('specialties', 'interconsultations.to_specialty_id', '=', 'specialties.id')
            ->select('specialties.name', DB::raw('COUNT(*) as total'))
            ->whereBetween('interconsultations.created_at', [$from, $to])
            ->groupBy('specialties.id', 'specialties.name')
            ->orderByDesc('total')->get();

        $pendingInterconsultations = Interconsultation::with([
            'patient', 'fromSpecialty', 'toSpecialty', 'requester', 'reference',
        ])->whereIn('status', ['pendiente', 'programada'])
            ->orderBy('requested_at')->limit(50)->get();

        $totalAppointments = Appointment::whereBetween('scheduled_at', [$from, $to])->count();

        return view('reports.index', compact(
            'referralStats', 'specialtyStats', 'pendingInterconsultations',
            'totalAppointments', 'startDate', 'endDate'
        ));
    }
}

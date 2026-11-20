<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Interconsultation;
use App\Models\Patient;
use App\Models\Professional;
use App\Models\ProfessionalSchedule;
use App\Models\ProfessionalBlock;
use App\Models\Referral;
use App\Models\Specialty;
use App\Models\StatusHistory;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AppointmentController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request)
    {
        $this->authorize('viewAny', Appointment::class);

        $date = $request->query('date', today()->toDateString());
        $request->validate(['date' => ['nullable', 'date']]);
        $status = $request->query('status');
        $query = Appointment::with([
            'patient', 'professional.user', 'specialty', 'reference', 'interconsultation',
        ]);

        if ($request->user()->isDoctor()) {
            $query->where('professional_id', $request->user()->professional->id);
        }

        $appointments = $query
            ->when($date, fn ($q) => $q->whereDate('scheduled_at', $date))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->orderBy('scheduled_at')->paginate(30)->withQueryString();

        return view('appointments.index', compact('appointments', 'date', 'status'));
    }

    public function create(Request $request)
    {
        $this->authorize('create', Appointment::class);

        $professional = $request->user()->professional;
        return view('appointments.form', [
            'patients' => Patient::orderBy('last_name')->orderBy('first_name')->get(),
            'professionals' => Professional::with(['user', 'specialty'])
                ->where('is_active', true)
                ->when($request->user()->isDoctor(), fn ($q) => $q->whereKey($professional->id))
                ->orderBy('id')->get(),
            'specialties' => Specialty::where('is_active', true)
                ->when($request->user()->isDoctor(), fn ($q) => $q->whereKey($professional->specialty_id))
                ->orderBy('name')->get(),
            'references' => Referral::with('patient')
                ->whereNotIn('status', ['cerrada', 'rechazada'])->latest()->get(),
            'interconsultations' => Interconsultation::with(['patient', 'fromSpecialty', 'toSpecialty'])
                ->whereIn('status', ['pendiente', 'programada'])->latest()->get(),
            'prefillPatient' => $request->query('patient_id'),
            'prefillReferral' => $request->query('reference_id'),
            'prefillInterconsultation' => $request->query('interconsultation_id'),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Appointment::class);

        $data = $request->validate([
            'patient_id' => ['required', 'integer', 'exists:patients,id'],
            'reference_id' => ['nullable', 'integer', 'exists:referrals,id'],
            'interconsultation_id' => ['nullable', 'integer', 'exists:interconsultations,id'],
            'professional_id' => ['required', 'integer', 'exists:professionals,id'],
            'specialty_id' => ['required', 'integer', 'exists:specialties,id'],
            'scheduled_at' => ['required', 'date', 'after_or_equal:now'],
            'duration_minutes' => ['required', 'integer', 'min:10', 'max:240'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $professional = Professional::findOrFail($data['professional_id']);
        if (!$professional->is_active || (int) $professional->specialty_id !== (int) $data['specialty_id']) {
            throw ValidationException::withMessages(['professional_id' => 'El profesional no está activo o no pertenece a la especialidad seleccionada.']);
        }

        if ($request->user()->isDoctor() && (int) $request->user()->professional?->id !== (int) $professional->id) {
            abort(403, 'Solo puedes programar citas en tu propia agenda.');
        }

        $patientId = (int) $data['patient_id'];
        if (!empty($data['reference_id'])) {
            $referral = Referral::findOrFail($data['reference_id']);
            if ((int) $referral->patient_id !== $patientId) {
                throw ValidationException::withMessages(['reference_id' => 'La referencia no corresponde al paciente.']);
            }
            if (in_array($referral->status, ['cerrada', 'rechazada'], true)) {
                throw ValidationException::withMessages(['reference_id' => 'No se puede programar con una referencia cerrada o rechazada.']);
            }
        }

        if (!empty($data['interconsultation_id'])) {
            $ic = Interconsultation::findOrFail($data['interconsultation_id']);
            if ((int) $ic->patient_id !== $patientId) {
                throw ValidationException::withMessages(['interconsultation_id' => 'La interconsulta no corresponde al paciente.']);
            }
            if ((int) $ic->to_specialty_id !== (int) $data['specialty_id']) {
                throw ValidationException::withMessages(['specialty_id' => 'La especialidad debe coincidir con el destino de la interconsulta.']);
            }
            if (in_array($ic->status, ['atendida', 'cancelada'], true)) {
                throw ValidationException::withMessages(['interconsultation_id' => 'La interconsulta ya fue atendida o cancelada.']);
            }
            if ($ic->assigned_professional_id && (int) $ic->assigned_professional_id !== (int) $professional->id) {
                throw ValidationException::withMessages(['professional_id' => 'La interconsulta está asignada a otro profesional.']);
            }
            if (empty($data['reference_id'])) {
                $data['reference_id'] = $ic->reference_id;
            }
        }

        $start = Carbon::parse($data['scheduled_at']);
        $end = $start->copy()->addMinutes((int) $data['duration_minutes']);

        // La cita debe quedar completamente dentro de uno de los turnos activos.
        $dayOfWeek = $start->dayOfWeekIso;
        $timeStart = $start->format('H:i:s');
        $timeEnd = $end->format('H:i:s');
        $matchingSchedule = ProfessionalSchedule::where('professional_id', $professional->id)
            ->where('day_of_week', $dayOfWeek)
            ->where('is_active', true)
            ->get()
            ->first(function ($schedule) use ($timeStart, $timeEnd, $data) {
                return (int) $schedule->slot_duration === (int) $data['duration_minutes']
                    && $timeStart >= $schedule->start_time
                    && $timeEnd <= $schedule->end_time;
            });

        if (!$matchingSchedule) {
            throw ValidationException::withMessages([
                'scheduled_at' => 'La cita debe estar dentro de un turno activo y usar la duración configurada para ese turno. Revisa Horarios médicos.',
            ]);
        }

        $blocked = ProfessionalBlock::where('professional_id', $professional->id)
            ->where('starts_at', '<', $end)
            ->where('ends_at', '>', $start)
            ->exists();
        if ($blocked) {
            throw ValidationException::withMessages(['scheduled_at' => 'El profesional tiene un bloqueo o ausencia en ese intervalo.']);
        }

        $conflict = Appointment::query()
            ->where('professional_id', $professional->id)
            ->whereIn('status', ['programada', 'confirmada'])
            ->where('scheduled_at', '<', $end)
            ->whereRaw('DATE_ADD(scheduled_at, INTERVAL duration_minutes MINUTE) > ?', [$start->format('Y-m-d H:i:s')])
            ->exists();

        if ($conflict) {
            throw ValidationException::withMessages(['scheduled_at' => 'El profesional ya tiene una cita que se cruza con el horario elegido.']);
        }

        $patientConflict = Appointment::query()
            ->where('patient_id', $patientId)
            ->whereIn('status', ['programada', 'confirmada'])
            ->where('scheduled_at', '<', $end)
            ->whereRaw('DATE_ADD(scheduled_at, INTERVAL duration_minutes MINUTE) > ?', [$start->format('Y-m-d H:i:s')])
            ->exists();

        if ($patientConflict) {
            throw ValidationException::withMessages(['scheduled_at' => 'El paciente ya tiene otra cita programada que se cruza con ese horario.']);
        }

        $appointment = DB::transaction(function () use ($data, $request, $professional) {
            $data['created_by'] = $request->user()->id;
            $data['status'] = 'programada';
            $record = Appointment::create($data);

            if ($record->interconsultation) {
                $ic = $record->interconsultation;
                $old = $ic->status;
                if ($old === 'pendiente') {
                    $ic->update([
                        'status' => 'programada',
                        'scheduled_at' => $record->scheduled_at,
                        'assigned_professional_id' => $professional->id,
                    ]);
                    StatusHistory::create([
                        'entity_type' => 'interconsulta', 'entity_id' => $ic->id,
                        'old_status' => $old, 'new_status' => 'programada',
                        'observations' => 'Programada mediante cita #' . $record->id . '.',
                        'changed_by' => $request->user()->id, 'created_at' => now(),
                    ]);
                }
            }

            if ($record->reference && in_array($record->reference->status, ['recibida', 'en_revision'], true)) {
                $ref = $record->reference;
                $old = $ref->status;
                $ref->update(['status' => 'programada']);
                StatusHistory::create([
                    'entity_type' => 'referencia', 'entity_id' => $ref->id,
                    'old_status' => $old, 'new_status' => 'programada',
                    'observations' => 'Se programó una cita relacionada.',
                    'changed_by' => $request->user()->id, 'created_at' => now(),
                ]);
            }

            return $record;
        });

        return redirect()->route('citas.index')->with('success', 'Cita interna programada correctamente.');
    }

    public function updateStatus(Request $request, Appointment $appointment)
    {
        $this->authorize('update', $appointment);
        $data = $request->validate([
            'status' => ['required', Rule::in(['programada', 'confirmada', 'atendida', 'cancelada'])],
        ]);

        $transitions = [
            'programada' => ['confirmada', 'cancelada'],
            'confirmada' => ['cancelada'],
            'atendida' => [],
            'cancelada' => [],
        ];

        if ($data['status'] !== $appointment->status
            && !in_array($data['status'], $transitions[$appointment->status] ?? [], true)) {
            throw ValidationException::withMessages(['status' => 'El cambio de estado solicitado no está permitido. Las citas atendidas o canceladas son estados finales.']);
        }
        if ($data['status'] === 'atendida' && !$appointment->attention()->exists()) {
            throw ValidationException::withMessages(['status' => 'Registra primero la atención médica para marcar esta cita como atendida.']);
        }

        DB::transaction(function () use ($request, $data, $appointment) {
            $old = $appointment->status;
            $appointment->update(['status' => $data['status']]);
            if ($old !== $appointment->status) {
                StatusHistory::create([
                    'entity_type' => 'cita', 'entity_id' => $appointment->id,
                    'old_status' => $old, 'new_status' => $appointment->status,
                    'observations' => 'Estado de cita actualizado desde la agenda.',
                    'changed_by' => $request->user()->id, 'created_at' => now(),
                ]);
            }

            if ($appointment->status === 'cancelada') {
                if ($appointment->interconsultation_id) {
                    $hasOtherActive = Appointment::where('interconsultation_id', $appointment->interconsultation_id)
                        ->whereIn('status', ['programada', 'confirmada'])->exists();
                    $ic = $appointment->interconsultation;
                    if (!$hasOtherActive && $ic && $ic->status === 'programada') {
                        $ic->update(['status' => 'pendiente', 'scheduled_at' => null, 'assigned_professional_id' => null]);
                        StatusHistory::create([
                            'entity_type' => 'interconsulta', 'entity_id' => $ic->id,
                            'old_status' => 'programada', 'new_status' => 'pendiente',
                            'observations' => 'La cita vinculada fue cancelada; la solicitud volvió a pendiente.',
                            'changed_by' => $request->user()->id, 'created_at' => now(),
                        ]);
                    }
                }

                if ($appointment->reference_id) {
                    $hasOtherActive = Appointment::where('reference_id', $appointment->reference_id)
                        ->whereIn('status', ['programada', 'confirmada'])->exists();
                    $ref = $appointment->reference;
                    if (!$hasOtherActive && $ref && $ref->status === 'programada') {
                        $ref->update(['status' => 'recibida']);
                        StatusHistory::create([
                            'entity_type' => 'referencia', 'entity_id' => $ref->id,
                            'old_status' => 'programada', 'new_status' => 'recibida',
                            'observations' => 'La cita relacionada fue cancelada; la referencia volvió a recibida.',
                            'changed_by' => $request->user()->id, 'created_at' => now(),
                        ]);
                    }
                }
            }
        });

        return back()->with('success', 'Estado de cita actualizado.');
    }
}

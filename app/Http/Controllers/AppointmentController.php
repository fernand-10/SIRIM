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

    public function availability(Request $request, Professional $professional)
    {
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'patient_id' => ['nullable', 'integer', 'exists:patients,id'],
        ]);

        if (!$professional->is_active) {
            return response()->json(['slots' => []]);
        }

        if (
            $request->user()->isDoctor()
            && (int) $request->user()->professional?->id !== (int) $professional->id
        ) {
            abort(403);
        }

        $date = $data['date'];
        $dayOfWeek = Carbon::parse($date)->dayOfWeekIso;

        $schedules = ProfessionalSchedule::where('professional_id', $professional->id)
            ->where('day_of_week', $dayOfWeek)
            ->where('is_active', true)
            ->orderBy('start_time')
            ->get();

        $slots = [];

        foreach ($schedules as $schedule) {
            $start = Carbon::parse($date . ' ' . $schedule->start_time);
            $shiftEnd = Carbon::parse($date . ' ' . $schedule->end_time);
            $duration = (int) $schedule->slot_duration;

            if ($duration < 1) {
                continue;
            }

            while ($start->copy()->addMinutes($duration)->lte($shiftEnd)) {
                $end = $start->copy()->addMinutes($duration);

                if ($start->gte(now())) {
                    $blocked = ProfessionalBlock::where('professional_id', $professional->id)
                        ->where('starts_at', '<', $end)
                        ->where('ends_at', '>', $start)
                        ->exists();

                    $professionalConflict = Appointment::where('professional_id', $professional->id)
                        ->whereIn('status', ['programada', 'confirmada'])
                        ->where('scheduled_at', '<', $end)
                        ->whereRaw(
                            'DATE_ADD(scheduled_at, INTERVAL duration_minutes MINUTE) > ?',
                            [$start->format('Y-m-d H:i:s')]
                        )
                        ->exists();

                    $patientConflict = false;

                    if (!empty($data['patient_id'])) {
                        $patientConflict = Appointment::where('patient_id', $data['patient_id'])
                            ->whereIn('status', ['programada', 'confirmada'])
                            ->where('scheduled_at', '<', $end)
                            ->whereRaw(
                                'DATE_ADD(scheduled_at, INTERVAL duration_minutes MINUTE) > ?',
                                [$start->format('Y-m-d H:i:s')]
                            )
                            ->exists();
                    }

                    if (!$blocked && !$professionalConflict && !$patientConflict) {
                        $slots[] = [
                            'value' => $start->format('Y-m-d\TH:i'),
                            'label' => $start->format('H:i'),
                            'duration_minutes' => $duration,
                        ];
                    }
                }

                $start->addMinutes($duration);
            }
        }

        return response()->json(['slots' => $slots]);
    }




    /**
     * Devuelve en una sola consulta los horarios disponibles de todos
     * los profesionales activos de una especialidad para una fecha.
     */
    public function specialtyAvailability(Request $request, int $specialtyId)
    {
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'duration_minutes' => ['required', 'integer', 'min:10', 'max:240'],
            'patient_id' => ['nullable', 'integer', 'exists:patients,id'],
        ]);

        $specialty = Specialty::query()
            ->where('is_active', true)
            ->findOrFail($specialtyId);

        $date = $data['date'];
        $duration = (int) $data['duration_minutes'];
        $dayStart = Carbon::createFromFormat('Y-m-d', $date)->startOfDay();
        $dayEnd = $dayStart->copy()->addDay();

        // Personal de la especialidad. Un médico solo puede consultar su propia agenda.
        $professionalQuery = Professional::with('user:id,name')
            ->where('specialty_id', $specialty->id)
            ->where('is_active', true);

        if ($request->user()->isDoctor()) {
            $professionalQuery->whereKey($request->user()->professional?->id ?? 0);
        }

        $professionals = $professionalQuery->orderBy('id')->get();
        $professionalIds = $professionals->modelKeys();

        if (empty($professionalIds)) {
            return response()->json([
                'date' => $date,
                'specialty_id' => $specialty->id,
                'duration_minutes' => $duration,
                'slots' => [],
            ]);
        }

        // Turnos activos del día cuya duración coincide con la elegida en el formulario.
        $schedules = ProfessionalSchedule::query()
            ->whereIn('professional_id', $professionalIds)
            ->where('day_of_week', $dayStart->dayOfWeekIso)
            ->where('slot_duration', $duration)
            ->where('is_active', true)
            ->orderBy('start_time')
            ->get()
            ->groupBy('professional_id');

        // Ausencias y bloqueos que se cruzan con el día consultado.
        $blocks = ProfessionalBlock::query()
            ->whereIn('professional_id', $professionalIds)
            ->where('starts_at', '<', $dayEnd)
            ->where('ends_at', '>', $dayStart)
            ->get(['professional_id', 'starts_at', 'ends_at'])
            ->groupBy('professional_id');

        // Citas activas que ocupan intervalos de los profesionales.
        $appointments = Appointment::query()
            ->whereIn('professional_id', $professionalIds)
            ->whereIn('status', ['programada', 'confirmada'])
            ->where('scheduled_at', '<', $dayEnd)
            ->whereRaw(
                'DATE_ADD(scheduled_at, INTERVAL duration_minutes MINUTE) > ?',
                [$dayStart->toDateTimeString()]
            )
            ->get(['professional_id', 'scheduled_at', 'duration_minutes'])
            ->groupBy('professional_id');

        // Evita ofrecer al mismo paciente otra cita que se cruce con ese horario.
        $patientAppointments = collect();
        if (!empty($data['patient_id'])) {
            $patientAppointments = Appointment::query()
                ->where('patient_id', $data['patient_id'])
                ->whereIn('status', ['programada', 'confirmada'])
                ->where('scheduled_at', '<', $dayEnd)
                ->whereRaw(
                    'DATE_ADD(scheduled_at, INTERVAL duration_minutes MINUTE) > ?',
                    [$dayStart->toDateTimeString()]
                )
                ->get(['scheduled_at', 'duration_minutes']);
        }

        $slots = [];

        foreach ($professionals as $professional) {
            foreach ($schedules->get($professional->id, collect()) as $schedule) {
                $scheduleStart = Carbon::parse($date . ' ' . $schedule->start_time);
                $scheduleEnd = Carbon::parse($date . ' ' . $schedule->end_time);
                $cursor = $scheduleStart->copy();

                while ($cursor->copy()->addMinutes($duration)->lte($scheduleEnd)) {
                    $slotStart = $cursor->copy();
                    $slotEnd = $slotStart->copy()->addMinutes($duration);
                    $cursor->addMinutes($duration);

                    // No mostrar horas anteriores a la hora actual.
                    if ($slotStart->lt(now())) {
                        continue;
                    }

                    $hasBlock = $blocks
                        ->get($professional->id, collect())
                        ->contains(function ($block) use ($slotStart, $slotEnd) {
                            return $block->starts_at->lt($slotEnd)
                                && $block->ends_at->gt($slotStart);
                        });

                    $hasProfessionalConflict = $appointments
                        ->get($professional->id, collect())
                        ->contains(function ($appointment) use ($slotStart, $slotEnd) {
                            $appointmentStart = $appointment->scheduled_at;
                            $appointmentEnd = $appointmentStart->copy()
                                ->addMinutes((int) $appointment->duration_minutes);

                            return $appointmentStart->lt($slotEnd)
                                && $appointmentEnd->gt($slotStart);
                        });

                    $hasPatientConflict = $patientAppointments->contains(
                        function ($appointment) use ($slotStart, $slotEnd) {
                            $appointmentStart = $appointment->scheduled_at;
                            $appointmentEnd = $appointmentStart->copy()
                                ->addMinutes((int) $appointment->duration_minutes);

                            return $appointmentStart->lt($slotEnd)
                                && $appointmentEnd->gt($slotStart);
                        }
                    );

                    if ($hasBlock || $hasProfessionalConflict || $hasPatientConflict) {
                        continue;
                    }

                    $slots[] = [
                        'professional_id' => $professional->id,
                        'professional_name' => $professional->user?->name
                            ?? 'Profesional #' . $professional->id,
                        'value' => $slotStart->format('Y-m-d\\TH:i'),
                        'scheduled_at' => $slotStart->format('Y-m-d\\TH:i'),
                        'time' => $slotStart->format('H:i'),
                        'label' => $slotStart->format('H:i'),
                        'duration_minutes' => $duration,
                    ];
                }
            }
        }

        // Ordenar por hora y luego por nombre del profesional.
        usort($slots, function ($a, $b) {
            return strcmp($a['scheduled_at'], $b['scheduled_at'])
                ?: strcmp($a['professional_name'], $b['professional_name']);
        });

        return response()->json([
            'date' => $date,
            'specialty_id' => $specialty->id,
            'duration_minutes' => $duration,
            'slots' => $slots,
        ]);
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

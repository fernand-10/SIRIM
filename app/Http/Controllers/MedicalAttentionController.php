<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Interconsultation;
use App\Models\MedicalAttention;
use App\Models\Patient;
use App\Models\Professional;
use App\Models\Referral;
use App\Models\Specialty;
use App\Models\StatusHistory;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MedicalAttentionController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request)
    {
        $this->authorize('viewAny', MedicalAttention::class);

        $query = MedicalAttention::with(['patient', 'professional.user', 'specialty']);
        if ($request->user()->isDoctor()) {
            $query->where('professional_id', $request->user()->professional->id);
        }

        $attentions = $query->latest('attended_at')->paginate(20);
        return view('attentions.index', compact('attentions'));
    }

    public function create(Request $request)
    {
        $this->authorize('create', MedicalAttention::class);

        $appointment = null;
        $requestedInterconsultation = null;
        if ($request->filled('interconsultation')) {
            $requestedInterconsultation = Interconsultation::findOrFail($request->query('interconsultation'));
            $this->authorize('view', $requestedInterconsultation);
        }

        if ($request->filled('appointment')) {
            $appointment = Appointment::with(['patient', 'interconsultation'])->findOrFail($request->query('appointment'));
            $this->authorize('view', $appointment);
            if ($appointment->status === 'cancelada' || $appointment->attention()->exists()) {
                abort(422, 'La cita está cancelada o ya tiene una atención registrada.');
            }
        }

        $doctor = $request->user()->isDoctor();
        $ownProfessional = $request->user()->professional;
        $prefillPatientId = $appointment?->patient_id ?? $requestedInterconsultation?->patient_id ?? $request->query('patient_id');

        return view('attentions.form', [
            'appointment' => $appointment,
            'requestedInterconsultation' => $requestedInterconsultation,
            'patients' => Patient::when($prefillPatientId, fn ($q) => $q->whereKey($prefillPatientId))
                ->orderBy('last_name')->get(),
            'professionals' => Professional::with(['user', 'specialty'])
                ->where('is_active', true)
                ->when($doctor, fn ($q) => $q->whereKey($ownProfessional->id))
                ->get(),
            'specialties' => Specialty::where('is_active', true)
                ->when($doctor, fn ($q) => $q->whereKey($ownProfessional->specialty_id))
                ->orderBy('name')->get(),
            'references' => Referral::with('patient')
                ->whereNotIn('status', ['cerrada', 'rechazada'])
                ->when($prefillPatientId, fn ($q) => $q->where('patient_id', $prefillPatientId))
                ->latest()->get(),
            'interconsultations' => Interconsultation::with(['patient', 'toSpecialty'])
                ->whereIn('status', ['pendiente', 'programada'])
                ->when($prefillPatientId, fn ($q) => $q->where('patient_id', $prefillPatientId))
                ->when($requestedInterconsultation, fn ($q) => $q->whereKey($requestedInterconsultation->id))
                ->when($doctor, fn ($q) => $q->where(function ($sub) use ($ownProfessional) {
                    $sub->whereNull('assigned_professional_id')->orWhere('assigned_professional_id', $ownProfessional->id);
                }))
                ->latest()->get(),
            'appointments' => Appointment::with(['patient', 'specialty'])
                ->whereIn('status', ['programada', 'confirmada'])
                ->whereDoesntHave('attention')
                ->when($appointment, fn ($q) => $q->whereKey($appointment->id))
                ->when($doctor, fn ($q) => $q->where('professional_id', $ownProfessional->id))
                ->latest('scheduled_at')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', MedicalAttention::class);

        $validated = $request->validate([
            'patient_id' => ['required', 'integer', 'exists:patients,id'],
            'professional_id' => ['required', 'integer', 'exists:professionals,id'],
            'specialty_id' => ['required', 'integer', 'exists:specialties,id'],
            'reference_id' => ['nullable', 'integer', 'exists:referrals,id'],
            'interconsultation_id' => ['nullable', 'integer', 'exists:interconsultations,id'],
            'appointment_id' => ['nullable', 'integer', 'exists:appointments,id'],
            'attended_at' => ['required', 'date', 'before_or_equal:now'],
            'reason' => ['required', 'string', 'max:2000'],
            'clinical_notes' => ['nullable', 'string', 'max:10000'],
            'outcome' => ['required', 'string', 'max:5000'],
            'plan' => ['nullable', 'string', 'max:5000'],
        ]);

        $professional = Professional::with('user')->findOrFail($validated['professional_id']);
        if (!$professional->is_active || (int) $professional->specialty_id !== (int) $validated['specialty_id']) {
            throw ValidationException::withMessages(['professional_id' => 'El profesional no está activo o no corresponde a la especialidad seleccionada.']);
        }

        if ($request->user()->isDoctor() && (int) $request->user()->professional?->id !== (int) $professional->id) {
            abort(403, 'Solo puedes registrar atenciones con tu perfil profesional.');
        }

        $patientId = (int) $validated['patient_id'];
        if (!empty($validated['reference_id']) && (int) Referral::findOrFail($validated['reference_id'])->patient_id !== $patientId) {
            throw ValidationException::withMessages(['reference_id' => 'La referencia no pertenece al paciente seleccionado.']);
        }

        $interconsultation = null;
        if (!empty($validated['interconsultation_id'])) {
            $interconsultation = Interconsultation::findOrFail($validated['interconsultation_id']);
            if ((int) $interconsultation->patient_id !== $patientId) {
                throw ValidationException::withMessages(['interconsultation_id' => 'La interconsulta no pertenece al paciente seleccionado.']);
            }
            if ((int) $interconsultation->to_specialty_id !== (int) $validated['specialty_id']) {
                throw ValidationException::withMessages(['specialty_id' => 'La especialidad de atención debe coincidir con la especialidad destinataria de la interconsulta.']);
            }
            if ($interconsultation->status === 'cancelada' || $interconsultation->status === 'atendida') {
                throw ValidationException::withMessages(['interconsultation_id' => 'La interconsulta ya fue atendida o cancelada.']);
            }
            if ($interconsultation->assigned_professional_id && (int) $interconsultation->assigned_professional_id !== (int) $professional->id) {
                throw ValidationException::withMessages(['professional_id' => 'La interconsulta está asignada a otro profesional.']);
            }
        }

        $appointment = null;
        if (!empty($validated['appointment_id'])) {
            $appointment = Appointment::findOrFail($validated['appointment_id']);
            if ((int) $appointment->patient_id !== $patientId) {
                throw ValidationException::withMessages(['appointment_id' => 'La cita no pertenece al paciente seleccionado.']);
            }
            if ((int) $appointment->professional_id !== (int) $professional->id || (int) $appointment->specialty_id !== (int) $validated['specialty_id']) {
                throw ValidationException::withMessages(['appointment_id' => 'La cita no corresponde al profesional y especialidad seleccionados.']);
            }
            if ($appointment->reference_id && !empty($validated['reference_id']) && (int) $appointment->reference_id !== (int) $validated['reference_id']) {
                throw ValidationException::withMessages(['reference_id' => 'La referencia seleccionada no coincide con la referencia de la cita.']);
            }
            if ($appointment->interconsultation_id && !empty($validated['interconsultation_id']) && (int) $appointment->interconsultation_id !== (int) $validated['interconsultation_id']) {
                throw ValidationException::withMessages(['interconsultation_id' => 'La interconsulta seleccionada no coincide con la interconsulta de la cita.']);
            }
            if ($appointment->status === 'cancelada' || $appointment->attention()->exists()) {
                throw ValidationException::withMessages(['appointment_id' => 'La cita está cancelada o ya tiene una atención registrada.']);
            }
            if (empty($validated['reference_id'])) {
                $validated['reference_id'] = $appointment->reference_id;
            }
            if (empty($validated['interconsultation_id'])) {
                $validated['interconsultation_id'] = $appointment->interconsultation_id;
                if ($validated['interconsultation_id']) {
                    $interconsultation = Interconsultation::findOrFail($validated['interconsultation_id']);
                    if ((int) $interconsultation->patient_id !== $patientId || (int) $interconsultation->to_specialty_id !== (int) $validated['specialty_id']) {
                        throw ValidationException::withMessages(['appointment_id' => 'La interconsulta vinculada a la cita no coincide con el paciente o especialidad.']);
                    }
                }
            }
        }

        $validated['created_by'] = $request->user()->id;

        $attention = DB::transaction(function () use ($validated, $request, $appointment, $interconsultation) {
            $record = MedicalAttention::create($validated);

            if ($appointment) {
                $oldStatus = $appointment->status;
                $appointment->update(['status' => 'atendida']);
                if ($oldStatus !== 'atendida') {
                    StatusHistory::create([
                        'entity_type' => 'cita', 'entity_id' => $appointment->id,
                        'old_status' => $oldStatus, 'new_status' => 'atendida',
                        'observations' => 'Atención médica registrada.',
                        'changed_by' => $request->user()->id, 'created_at' => now(),
                    ]);
                }
            }

            if ($interconsultation) {
                $oldStatus = $interconsultation->status;
                $interconsultation->update([
                    'status' => 'atendida',
                    'assigned_professional_id' => $record->professional_id,
                    'answered_at' => $record->attended_at,
                    'response_summary' => $record->outcome,
                    'closed_at' => now(),
                ]);
                if ($oldStatus !== 'atendida') {
                    StatusHistory::create([
                        'entity_type' => 'interconsulta', 'entity_id' => $interconsultation->id,
                        'old_status' => $oldStatus, 'new_status' => 'atendida',
                        'observations' => 'Interconsulta respondida desde la atención médica #' . $record->id . '.',
                        'changed_by' => $request->user()->id, 'created_at' => now(),
                    ]);
                }
            }

            if ($record->reference) {
                $reference = $record->reference;
                if (in_array($reference->status, ['recibida', 'en_revision', 'programada'], true)) {
                    $oldStatus = $reference->status;
                    $reference->update(['status' => 'en_atencion']);
                    StatusHistory::create([
                        'entity_type' => 'referencia', 'entity_id' => $reference->id,
                        'old_status' => $oldStatus, 'new_status' => 'en_atencion',
                        'observations' => 'Se registró una atención médica vinculada (#' . $record->id . ').',
                        'changed_by' => $request->user()->id, 'created_at' => now(),
                    ]);
                }
            }

            return $record;
        });

        return redirect()->route('atenciones.show', $attention)
            ->with('success', 'Atención médica registrada correctamente.');
    }

    public function show(MedicalAttention $attention)
    {
        $this->authorize('view', $attention);
        $attention->load([
            'patient', 'professional.user', 'specialty', 'reference',
            'interconsultation', 'appointment', 'registrar',
        ]);

        return view('attentions.show', compact('attention'));
    }
}

<?php

namespace App\Http\Controllers;

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
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InterconsultationController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request)
    {
        $this->authorize('viewAny', Interconsultation::class);

        $q = trim((string) $request->query('q', ''));
        $status = $request->query('status');
        $query = Interconsultation::with([
            'patient', 'fromSpecialty', 'toSpecialty', 'requester',
            'requestingProfessional.specialty', 'assignedProfessional.user', 'reference',
        ]);

        if ($request->user()->isDoctor()) {
            $professionalId = $request->user()->professional?->id;
            $query->where(function ($sub) use ($request, $professionalId) {
                $sub->where('requested_by', $request->user()->id)
                    ->orWhere('assigned_professional_id', $professionalId);
            });
        }

        $interconsultations = $query
            ->when($status, fn ($builder) => $builder->where('status', $status))
            ->when($q, function ($builder) use ($q) {
                $builder->where(function ($sub) use ($q) {
                    $sub->where('code', 'like', "%{$q}%")
                        ->orWhereHas('patient', function ($patientQuery) use ($q) {
                            $patientQuery->where('document_number', 'like', "%{$q}%")
                                ->orWhere('first_name', 'like', "%{$q}%")
                                ->orWhere('last_name', 'like', "%{$q}%");
                        });
                });
            })
            ->latest('requested_at')
            ->paginate(20)
            ->withQueryString();

        return view('interconsultations.index', compact('interconsultations', 'status', 'q'));
    }

    public function create(Request $request)
    {
        $this->authorize('create', Interconsultation::class);

        $parentId = $request->query('parent') ?? $request->query('parent_id') ?? $request->query('parent_interconsultation_id');
        $parentRecord = $parentId ? Interconsultation::with('patient')->findOrFail($parentId) : null;
        $patientId = $parentRecord?->patient_id ?? $request->query('patient_id');

        $professional = $request->user()->professional;
        $specialties = Specialty::where('is_active', true)->orderBy('name')->get();

        return view('interconsultations.form', [
            'patients' => Patient::orderBy('last_name')->orderBy('first_name')->get(),
            'specialties' => $specialties,
            'professionals' => Professional::with(['user', 'specialty'])
                ->where('is_active', true)
                ->when($request->user()->isDoctor(), fn ($q) => $q->where('specialty_id', $professional?->specialty_id))
                ->get(),
            'references' => Referral::with('patient')
                ->whereNotIn('status', ['cerrada', 'rechazada'])
                ->when($patientId, fn ($q) => $q->where('patient_id', $patientId))
                ->latest()->get(),
            'parents' => Interconsultation::with(['patient', 'fromSpecialty', 'toSpecialty'])
                ->when($patientId, fn ($q) => $q->where('patient_id', $patientId))
                ->latest()->get(),
            'parentRecord' => $parentRecord,
            'attentions' => MedicalAttention::with(['patient', 'specialty'])
                ->when($patientId, fn ($q) => $q->where('patient_id', $patientId))
                ->latest('attended_at')->limit(50)->get(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Interconsultation::class);

        $data = $request->validate([
            'patient_id' => ['required', 'integer', 'exists:patients,id'],
            'reference_id' => ['nullable', 'integer', 'exists:referrals,id'],
            'parent_interconsultation_id' => ['nullable', 'integer', 'exists:interconsultations,id'],
            'originating_attention_id' => ['nullable', 'integer', 'exists:medical_attentions,id'],
            'from_specialty_id' => ['required', 'integer', 'exists:specialties,id'],
            'to_specialty_id' => ['required', 'integer', 'different:from_specialty_id', 'exists:specialties,id'],
            'assigned_professional_id' => ['nullable', 'integer', 'exists:professionals,id'],
            'priority' => ['required', Rule::in(['normal', 'preferente', 'urgente'])],
            'reason' => ['required', 'string', 'max:2000'],
            'clinical_summary' => ['nullable', 'string', 'max:5000'],
        ]);

        $patientId = (int) $data['patient_id'];

        if (!empty($data['reference_id']) && (int) Referral::findOrFail($data['reference_id'])->patient_id !== $patientId) {
            throw ValidationException::withMessages(['reference_id' => 'La referencia seleccionada no pertenece al paciente.']);
        }

        if (!empty($data['parent_interconsultation_id']) && (int) Interconsultation::findOrFail($data['parent_interconsultation_id'])->patient_id !== $patientId) {
            throw ValidationException::withMessages(['parent_interconsultation_id' => 'La interconsulta anterior no pertenece al paciente.']);
        }

        if (!empty($data['originating_attention_id'])) {
            $attention = MedicalAttention::findOrFail($data['originating_attention_id']);
            if ((int) $attention->patient_id !== $patientId) {
                throw ValidationException::withMessages(['originating_attention_id' => 'La atención seleccionada no pertenece al paciente.']);
            }
            if ((int) $attention->specialty_id !== (int) $data['from_specialty_id']) {
                throw ValidationException::withMessages(['from_specialty_id' => 'La especialidad de origen debe coincidir con la atención seleccionada.']);
            }
        }

        if (!empty($data['assigned_professional_id'])) {
            $assigned = Professional::findOrFail($data['assigned_professional_id']);
            if ((int) $assigned->specialty_id !== (int) $data['to_specialty_id']) {
                throw ValidationException::withMessages(['assigned_professional_id' => 'El profesional no pertenece a la especialidad destinataria.']);
            }
        }

        if ($request->user()->isDoctor()) {
            $ownProfessional = $request->user()->professional;
            if (!$ownProfessional || (int) $ownProfessional->specialty_id !== (int) $data['from_specialty_id']) {
                throw ValidationException::withMessages(['from_specialty_id' => 'La especialidad de origen debe ser la especialidad de su perfil profesional.']);
            }
        }

        $interconsultation = DB::transaction(function () use ($data, $request) {
            $data['requested_by'] = $request->user()->id;
            $data['requested_at'] = now();
            $data['status'] = 'pendiente';

            $record = Interconsultation::create($data);
            $record->update([
                'code' => 'INT-' . now()->format('Y') . '-' . str_pad((string) $record->id, 6, '0', STR_PAD_LEFT),
            ]);

            StatusHistory::create([
                'entity_type' => 'interconsulta',
                'entity_id' => $record->id,
                'old_status' => null,
                'new_status' => 'pendiente',
                'observations' => 'Solicitud de interconsulta creada.',
                'changed_by' => $request->user()->id,
                'created_at' => now(),
            ]);

            return $record;
        });

        return redirect()->route('interconsultas.show', $interconsultation)
            ->with('success', 'Interconsulta registrada. Código: ' . $interconsultation->code);
    }

    public function show(Interconsultation $interconsultation)
    {
        $this->authorize('view', $interconsultation);

        $interconsultation->load([
            'patient', 'fromSpecialty', 'toSpecialty', 'requester',
            'requestingProfessional.specialty', 'assignedProfessional.user',
            'reference', 'parent', 'children.patient', 'originatingAttention',
            'attentions.professional.user', 'statusHistories.user',
        ]);

        return view('interconsultations.show', compact('interconsultation'));
    }

    public function updateStatus(Request $request, Interconsultation $interconsultation)
    {
        $this->authorize('update', $interconsultation);

        $data = $request->validate([
            // "atendida" se establece al guardar la atención clínica, no manualmente.
            'status' => ['required', Rule::in(['cancelada'])],
            'observations' => ['nullable', 'string', 'max:1000'],
        ]);

        if (in_array($interconsultation->status, ['atendida', 'cancelada'], true)) {
            throw ValidationException::withMessages(['status' => 'La interconsulta ya fue atendida o cancelada y no puede modificarse.']);
        }

        DB::transaction(function () use ($request, $data, $interconsultation) {
            $oldStatus = $interconsultation->status;
            $newStatus = $data['status'];
            $interconsultation->update([
                'status' => $newStatus,
                'closed_at' => $newStatus === 'cancelada' ? now() : null,
            ]);

            if ($oldStatus !== $newStatus) {
                StatusHistory::create([
                    'entity_type' => 'interconsulta',
                    'entity_id' => $interconsultation->id,
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus,
                    'observations' => $data['observations'] ?? 'Estado actualizado.',
                    'changed_by' => $request->user()->id,
                    'created_at' => now(),
                ]);
            }
        });

        return back()->with('success', 'Estado de la interconsulta actualizado.');
    }
}

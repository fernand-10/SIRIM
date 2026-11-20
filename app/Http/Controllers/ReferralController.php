<?php

namespace App\Http\Controllers;

use App\Models\HealthFacility;
use App\Models\Patient;
use App\Models\Referral;
use App\Models\Specialty;
use App\Models\StatusHistory;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ReferralController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request)
    {
        $this->authorize('viewAny', Referral::class);
        $q = trim((string) $request->query('q', ''));
        $status = $request->query('status');

        $referrals = Referral::with(['patient', 'originFacility', 'receivingFacility', 'specialty'])
            ->when($q, function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('code', 'like', "%{$q}%")
                        ->orWhereHas('patient', function ($p) use ($q) {
                            $p->where('document_number', 'like', "%{$q}%")
                                ->orWhere('first_name', 'like', "%{$q}%")
                                ->orWhere('last_name', 'like', "%{$q}%");
                        });
                });
            })
            ->when($status, fn ($query) => $query->where('status', $status))
            ->latest('referred_at')->paginate(15)->withQueryString();

        return view('referrals.index', compact('referrals', 'q', 'status'));
    }

    public function create()
    {
        $this->authorize('create', Referral::class);
        return view('referrals.form', $this->formData(new Referral()));
    }

    public function store(Request $request)
    {
        $this->authorize('create', Referral::class);
        $data = $this->validated($request);

        if ((int) $data['origin_facility_id'] === (int) $data['receiving_facility_id']) {
            throw ValidationException::withMessages(['receiving_facility_id' => 'El establecimiento de origen y receptor no pueden ser iguales.']);
        }

        $referral = DB::transaction(function () use ($data, $request) {
            $data['registered_by'] = $request->user()->id;
            $data['received_at'] = now();
            $data['status'] = 'recibida';
            $record = Referral::create($data);
            $record->update([
                'code' => 'REF-' . now()->format('Y') . '-' . str_pad((string) $record->id, 6, '0', STR_PAD_LEFT),
            ]);

            StatusHistory::create([
                'entity_type' => 'referencia', 'entity_id' => $record->id,
                'old_status' => null, 'new_status' => 'recibida',
                'observations' => 'Referencia recibida y registrada en el hospital.',
                'changed_by' => $request->user()->id, 'created_at' => now(),
            ]);
            return $record;
        });

        return redirect()->route('referencias.show', $referral)
            ->with('success', 'Referencia registrada correctamente. Código: ' . $referral->code);
    }

    public function show(Referral $referral)
    {
        $this->authorize('view', $referral);
        $referral->load([
            'patient', 'originFacility', 'receivingFacility', 'specialty', 'registrar',
            'interconsultations.fromSpecialty', 'interconsultations.toSpecialty', 'interconsultations.requester',
            'appointments.professional.user', 'appointments.specialty',
            'attentions.professional.user', 'attentions.specialty', 'statusHistories.user',
        ]);
        return view('referrals.show', compact('referral'));
    }

    public function edit(Referral $referral)
    {
        $this->authorize('update', $referral);
        return view('referrals.form', $this->formData($referral));
    }

    public function update(Request $request, Referral $referral)
    {
        $this->authorize('update', $referral);
        $data = $this->validated($request, true);

        if ((int) $data['origin_facility_id'] === (int) $data['receiving_facility_id']) {
            throw ValidationException::withMessages(['receiving_facility_id' => 'El establecimiento de origen y receptor no pueden ser iguales.']);
        }

        DB::transaction(function () use ($data, $request, $referral) {
            $oldStatus = $referral->status;
            $newStatus = $data['status'];
            $referral->update($data + [
                'closed_at' => $newStatus === 'cerrada' ? ($referral->closed_at ?? now()) : null,
            ]);

            if ($oldStatus !== $newStatus) {
                StatusHistory::create([
                    'entity_type' => 'referencia', 'entity_id' => $referral->id,
                    'old_status' => $oldStatus, 'new_status' => $newStatus,
                    'observations' => 'Estado actualizado desde el módulo de referencias.',
                    'changed_by' => $request->user()->id, 'created_at' => now(),
                ]);
            }
        });

        return redirect()->route('referencias.show', $referral)->with('success', 'Referencia actualizada.');
    }

    private function formData(Referral $referral): array
    {
        return [
            'referral' => $referral,
            'patients' => Patient::orderBy('last_name')->orderBy('first_name')->get(),
            'facilities' => HealthFacility::where('is_active', true)->orderBy('name')->get(),
            'specialties' => Specialty::where('is_active', true)->orderBy('name')->get(),
        ];
    }

    private function validated(Request $request, bool $updating = false): array
    {
        return $request->validate([
            'patient_id' => ['required', 'integer', 'exists:patients,id'],
            'origin_facility_id' => ['required', 'integer', 'exists:health_facilities,id'],
            'receiving_facility_id' => ['required', 'integer', 'exists:health_facilities,id'],
            'requested_specialty_id' => ['nullable', 'integer', 'exists:specialties,id'],
            'source_professional_name' => ['nullable', 'string', 'max:150'],
            'referred_at' => ['required', 'date', 'before_or_equal:now'],
            'reason' => ['required', 'string', 'max:2000'],
            'clinical_summary' => ['nullable', 'string', 'max:5000'],
            'priority' => ['required', Rule::in(['normal', 'preferente', 'urgente'])],
            'status' => [$updating ? 'required' : 'nullable', Rule::in(['recibida', 'en_revision', 'programada', 'en_atencion', 'cerrada', 'rechazada'])],
        ]);
    }
}

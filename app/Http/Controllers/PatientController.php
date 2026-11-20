<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PatientController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $patients = Patient::query()
            ->when($q, function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('document_number', 'like', "%{$q}%")
                        ->orWhere('first_name', 'like', "%{$q}%")
                        ->orWhere('last_name', 'like', "%{$q}%");
                });
            })
            ->orderBy('last_name')->orderBy('first_name')
            ->paginate(15)->withQueryString();

        return view('patients.index', compact('patients', 'q'));
    }

    public function create()
    {
        return view('patients.form', ['patient' => new Patient()]);
    }

    public function store(Request $request)
    {
        $patient = Patient::create($this->validated($request));
        return redirect()->route('pacientes.show', $patient)->with('success', 'Paciente registrado correctamente.');
    }

    public function show(Patient $patient)
    {
        $patient->load([
            'referrals' => fn ($q) => $q->latest()->take(10),
            'interconsultations' => fn ($q) => $q->latest()->take(10),
            'appointments' => fn ($q) => $q->latest()->take(10),
        ]);
        return view('patients.show', compact('patient'));
    }

    public function edit(Patient $patient)
    {
        return view('patients.form', compact('patient'));
    }

    public function update(Request $request, Patient $patient)
    {
        $patient->update($this->validated($request, $patient));
        return redirect()->route('pacientes.show', $patient)->with('success', 'Datos del paciente actualizados.');
    }

    public function destroy(Patient $patient)
    {
        $hasRelatedRecords = $patient->referrals()->exists()
            || $patient->interconsultations()->exists()
            || $patient->appointments()->exists()
            || $patient->attentions()->exists();

        if ($hasRelatedRecords) {
            return back()->with('error', 'No se puede eliminar el paciente porque tiene registros asistenciales relacionados.');
        }

        try {
            $patient->delete();
        } catch (QueryException $exception) {
            report($exception);
            return back()->with('error', 'La base de datos impidió eliminar el paciente por sus relaciones.');
        }

        return redirect()->route('pacientes.index')->with('success', 'Paciente eliminado.');
    }

    private function validated(Request $request, ?Patient $patient = null): array
    {
        $documentType = $request->input('document_type');
        $documentRules = ['required', 'string', 'max:30'];
        if ($documentType === 'DNI') {
            $documentRules[] = 'regex:/^[0-9]{8}$/';
        }

        return $request->validate([
            'document_type' => ['required', Rule::in(['DNI', 'CE', 'Pasaporte', 'Otro'])],
            'document_number' => array_merge($documentRules, [
                Rule::unique('patients', 'document_number')->ignore($patient?->id),
            ]),
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:120'],
            'birth_date' => ['nullable', 'date', 'before_or_equal:today'],
            'sex' => ['nullable', Rule::in(['F', 'M', 'No especifica'])],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
        ]);
    }
}

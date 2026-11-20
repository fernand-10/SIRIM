<?php

namespace App\Http\Controllers;

use App\Models\HealthFacility;
use App\Models\Professional;
use App\Models\Specialty;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminController extends Controller
{
    public function index()
    {
        $users = User::with('professional.specialty')->orderBy('name')->get();
        $specialties = Specialty::orderBy('name')->get();
        $facilities = HealthFacility::orderBy('name')->get();

        return view('admin.index', compact('users', 'specialties', 'facilities'));
    }

    public function storeUser(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'password' => ['required', 'string', 'min:10', 'confirmed'],
            'role' => ['required', Rule::in(['admin', 'admission', 'doctor', 'coordinator'])],
            'specialty_id' => ['required_if:role,doctor', 'nullable', 'exists:specialties,id'],
            'health_facility_id' => ['required_if:role,doctor', 'nullable', 'exists:health_facilities,id'],
            'license_number' => ['nullable', 'string', 'max:80'],
        ]);

        $role = $data['role'];
        $specialty = $data['specialty_id'] ?? null;
        $facility = $data['health_facility_id'] ?? null;
        $license = $data['license_number'] ?? null;
        unset($data['specialty_id'], $data['health_facility_id'], $data['license_number']);

        DB::transaction(function () use ($data, $role, $specialty, $facility, $license) {
            // El cast "hashed" del modelo User aplica el hash automáticamente.
            $user = User::create($data);

            if ($role === 'doctor') {
                Professional::create([
                    'user_id' => $user->id,
                    'specialty_id' => $specialty,
                    'health_facility_id' => $facility,
                    'license_number' => $license,
                    'is_active' => true,
                ]);
            }
        });

        return back()->with('success', 'Usuario creado correctamente.');
    }

    public function storeSpecialty(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', 'unique:specialties,name'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        Specialty::create($data + ['is_active' => true]);
        return back()->with('success', 'Especialidad creada.');
    }

    public function storeFacility(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:180', 'unique:health_facilities,name'],
            'facility_type' => ['required', Rule::in(['Puesto de salud', 'Centro de salud', 'Hospital', 'Clínica', 'Otro'])],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
        ]);

        HealthFacility::create($data + ['is_active' => true]);
        return back()->with('success', 'Establecimiento creado.');
    }
}

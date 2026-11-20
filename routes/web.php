<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InterconsultationController;
use App\Http\Controllers\MedicalAttentionController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\ReferralController;
use App\Http\Controllers\ReniecController;
use App\Http\Controllers\ProfessionalScheduleController;
use App\Http\Controllers\ReportController;
use App\Models\Interconsultation;
use App\Models\Professional;
use App\Models\Referral;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => auth()->check()
    ? redirect()->route('dashboard')
    : redirect()->route('login')
);

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');

    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:8,1')
        ->name('login.store');
});

Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

Route::middleware(['auth', 'role:admin,admission,doctor,coordinator'])->group(function () {

    Route::get('/dashboard', DashboardController::class)
        ->name('dashboard');

    // ==========================================
    // PACIENTES
    // ==========================================

    Route::get('/pacientes', [PatientController::class, 'index'])
        ->name('pacientes.index');

    Route::get('/pacientes/crear', [PatientController::class, 'create'])
        ->middleware('role:admin,admission')
        ->name('pacientes.create');

    Route::post('/pacientes', [PatientController::class, 'store'])
        ->middleware('role:admin,admission')
        ->name('pacientes.store');

    Route::get('/pacientes/{patient}', [PatientController::class, 'show'])
        ->whereNumber('patient')
        ->name('pacientes.show');

    Route::get('/pacientes/{patient}/editar', [PatientController::class, 'edit'])
        ->middleware('role:admin,admission')
        ->name('pacientes.edit');

    Route::put('/pacientes/{patient}', [PatientController::class, 'update'])
        ->middleware('role:admin,admission')
        ->name('pacientes.update');

    Route::delete('/pacientes/{patient}', [PatientController::class, 'destroy'])
        ->middleware('role:admin')
        ->name('pacientes.destroy');

    // ==========================================
    // REFERENCIAS
    // ==========================================

    Route::resource('referencias', ReferralController::class)
        ->parameters(['referencias' => 'referral'])
        ->except(['destroy']);

    // ==========================================
    // INTERCONSULTAS
    // ==========================================

    Route::get('/interconsultas', [InterconsultationController::class, 'index'])
        ->name('interconsultas.index');

    Route::get('/interconsultas/create', [InterconsultationController::class, 'create'])
        ->middleware('role:admin,doctor,coordinator')
        ->name('interconsultas.create');

    Route::post('/interconsultas', [InterconsultationController::class, 'store'])
        ->middleware('role:admin,doctor,coordinator')
        ->name('interconsultas.store');

    Route::get('/interconsultas/{interconsultation}', [InterconsultationController::class, 'show'])
        ->name('interconsultas.show');

    Route::patch('/interconsultas/{interconsultation}/estado', [InterconsultationController::class, 'updateStatus'])
        ->middleware('role:admin,doctor,coordinator')
        ->name('interconsultas.status');

    // ==========================================
    // CITAS MÉDICAS
    // ==========================================

    Route::get('/citas', [AppointmentController::class, 'index'])
        ->name('citas.index');

    Route::get('/citas/crear', [AppointmentController::class, 'create'])
        ->middleware('role:admin,admission,coordinator,doctor')
        ->name('citas.create');

    Route::post('/citas', [AppointmentController::class, 'store'])
        ->middleware('role:admin,admission,coordinator,doctor')
        ->name('citas.store');

    Route::patch('/citas/{appointment}/estado', [AppointmentController::class, 'updateStatus'])
        ->name('citas.status');

    // ==========================================
    // ATENCIONES MÉDICAS
    // ==========================================

    Route::get('/atenciones', [MedicalAttentionController::class, 'index'])
        ->middleware('role:admin,doctor')
        ->name('atenciones.index');

    Route::get('/atenciones/crear', [MedicalAttentionController::class, 'create'])
        ->middleware('role:admin,doctor')
        ->name('atenciones.create');

    Route::post('/atenciones', [MedicalAttentionController::class, 'store'])
        ->middleware('role:admin,doctor')
        ->name('atenciones.store');

    Route::get('/atenciones/{attention}', [MedicalAttentionController::class, 'show'])
        ->middleware('role:admin,doctor')
        ->name('atenciones.show');

    // ==========================================
    // REPORTES
    // ==========================================

    Route::get('/reportes', [ReportController::class, 'index'])
        ->middleware('role:admin,coordinator,admission')
        ->name('reportes.index');

    // ==========================================
    // CONSULTA RENIEC
    // ==========================================

    Route::post('/reniec/consultar-dni', [ReniecController::class, 'consultar'])
        ->middleware(['role:admin,admission,coordinator', 'throttle:10,1'])
        ->name('reniec.consultar');

    // ==========================================
    // API INTERNA
    // ==========================================

    Route::prefix('api')->name('api.')->group(function () {

        // Profesionales de una especialidad.
        Route::get('/especialidades/{specialtyId}/profesionales', function (int $specialtyId) {

            return Professional::with(['user:id,name'])
                ->where('specialty_id', $specialtyId)
                ->where('is_active', true)
                ->orderBy('id')
                ->get(['id', 'user_id', 'specialty_id']);

        })->whereNumber('specialtyId')
            ->name('especialidades.profesionales');

        // Disponibilidad conjunta de todos los profesionales
        // pertenecientes a una especialidad.
        Route::get(
            '/especialidades/{specialtyId}/disponibilidad',
            [AppointmentController::class, 'specialtyAvailability']
        )->whereNumber('specialtyId')
            ->name('especialidades.disponibilidad');

        // Disponibilidad individual de un profesional.
        Route::get(
            '/profesionales/{professional}/disponibilidad',
            [AppointmentController::class, 'availability']
        )->whereNumber('professional')
            ->name('profesionales.disponibilidad');

        // Referencias de un paciente.
        Route::get('/pacientes/{patientId}/referencias', function (int $patientId) {

            return Referral::where('patient_id', $patientId)
                ->whereNotIn('status', ['cerrada', 'rechazada'])
                ->latest()
                ->get(['id', 'patient_id', 'code', 'status']);

        })->whereNumber('patientId')
            ->name('pacientes.referencias');

        // Interconsultas de un paciente.
        Route::get('/pacientes/{patientId}/interconsultas', function (int $patientId) {

            return Interconsultation::with([
                'fromSpecialty:id,name',
                'toSpecialty:id,name'
            ])
                ->where('patient_id', $patientId)
                ->whereIn('status', ['pendiente', 'programada'])
                ->latest()
                ->get();

        })->whereNumber('patientId')
            ->name('pacientes.interconsultas');

    });

    // ==========================================
    // HORARIOS MÉDICOS
    // ==========================================

    Route::prefix('horarios')
        ->name('horarios.')
        ->middleware('role:admin,coordinator')
        ->group(function () {

            Route::get('/', [ProfessionalScheduleController::class, 'index'])
                ->name('index');

            Route::post('/', [ProfessionalScheduleController::class, 'storeSchedule'])
                ->name('store');

            Route::delete('/{schedule}', [ProfessionalScheduleController::class, 'destroySchedule'])
                ->name('destroy');

            Route::post('/bloqueos', [ProfessionalScheduleController::class, 'storeBlock'])
                ->name('blocks.store');

            Route::delete('/bloqueos/{block}', [ProfessionalScheduleController::class, 'destroyBlock'])
                ->name('blocks.destroy');

        });

    // ==========================================
    // ADMINISTRACIÓN
    // ==========================================

    Route::prefix('administracion')
        ->name('admin.')
        ->middleware('role:admin')
        ->group(function () {

            Route::get('/', [AdminController::class, 'index'])
                ->name('index');

            Route::post('/usuarios', [AdminController::class, 'storeUser'])
                ->name('users.store');

            Route::post('/especialidades', [AdminController::class, 'storeSpecialty'])
                ->name('specialties.store');

            Route::post('/establecimientos', [AdminController::class, 'storeFacility'])
                ->name('facilities.store');

        });

});

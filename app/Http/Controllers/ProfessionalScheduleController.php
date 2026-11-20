<?php

namespace App\Http\Controllers;

use App\Models\Professional;
use App\Models\ProfessionalBlock;
use App\Models\ProfessionalSchedule;
use App\Models\Appointment;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProfessionalScheduleController extends Controller
{
    public function index()
    {
        $professionals = Professional::with(['user', 'specialty', 'healthFacility', 'schedules'])
            ->where('is_active', true)->orderBy('id')->get();
        $blocks = ProfessionalBlock::with(['professional.user', 'creator'])
            ->where('ends_at', '>=', now())->orderBy('starts_at')->paginate(25);

        return view('schedules.index', compact('professionals', 'blocks'));
    }

    public function storeSchedule(Request $request)
    {
        $data = $request->validate([
            'professional_id' => ['required', 'integer', 'exists:professionals,id'],
            'day_of_week' => ['required', 'integer', 'between:1,7'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'slot_duration' => ['required', 'integer', Rule::in([10, 15, 20, 30, 45, 60, 90, 120])],
        ]);

        $professional = Professional::findOrFail($data['professional_id']);
        if (!$professional->is_active) {
            throw ValidationException::withMessages(['professional_id' => 'El profesional está inactivo.']);
        }

        $overlap = ProfessionalSchedule::where('professional_id', $data['professional_id'])
            ->where('day_of_week', $data['day_of_week'])
            ->where('is_active', true)
            ->where('start_time', '<', $data['end_time'])
            ->where('end_time', '>', $data['start_time'])
            ->exists();
        if ($overlap) {
            throw ValidationException::withMessages(['start_time' => 'El nuevo horario se cruza con otro turno del mismo día.']);
        }

        ProfessionalSchedule::create($data + ['is_active' => true]);
        return back()->with('success', 'Horario agregado correctamente.');
    }

    public function destroySchedule(ProfessionalSchedule $schedule)
    {
        // MySQL DAYOFWEEK(): domingo=1, lunes=2 ... sábado=7.
        $mysqlDay = $schedule->day_of_week === 7 ? 1 : $schedule->day_of_week + 1;
        $hasAppointments = Appointment::query()
            ->where('professional_id', $schedule->professional_id)
            ->whereIn('status', ['programada', 'confirmada'])
            ->where('scheduled_at', '>=', now())
            ->whereRaw('DAYOFWEEK(scheduled_at) = ?', [$mysqlDay])
            ->whereRaw('TIME(scheduled_at) < ?', [$schedule->end_time])
            ->whereRaw('ADDTIME(scheduled_at, SEC_TO_TIME(duration_minutes * 60)) > ?', [$schedule->start_time])
            ->exists();

        if ($hasAppointments) {
            throw ValidationException::withMessages([
                'schedule' => 'No se puede eliminar este horario porque tiene citas futuras programadas. Reasigna o cancela esas citas primero.',
            ]);
        }

        $schedule->delete();
        return back()->with('success', 'Horario eliminado.');
    }

    public function storeBlock(Request $request)
    {
        $data = $request->validate([
            'professional_id' => ['required', 'integer', 'exists:professionals,id'],
            'starts_at' => ['required', 'date', 'after:now'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $overlap = ProfessionalBlock::where('professional_id', $data['professional_id'])
            ->where('starts_at', '<', $data['ends_at'])
            ->where('ends_at', '>', $data['starts_at'])
            ->exists();

        if ($overlap) {
            throw ValidationException::withMessages(['starts_at' => 'Ya existe un bloqueo que se cruza con ese intervalo.']);
        }

        $appointmentConflict = Appointment::where('professional_id', $data['professional_id'])
            ->whereIn('status', ['programada', 'confirmada'])
            ->where('scheduled_at', '<', $data['ends_at'])
            ->whereRaw('DATE_ADD(scheduled_at, INTERVAL duration_minutes MINUTE) > ?', [$data['starts_at']])
            ->exists();
        if ($appointmentConflict) {
            throw ValidationException::withMessages(['starts_at' => 'No se puede bloquear el intervalo porque ya tiene citas programadas.']);
        }

        ProfessionalBlock::create($data + ['created_by' => $request->user()->id]);
        return back()->with('success', 'Bloqueo de agenda registrado.');
    }

    public function destroyBlock(ProfessionalBlock $block)
    {
        $block->delete();
        return back()->with('success', 'Bloqueo eliminado.');
    }
}

@extends('layouts.app')
@section('title', 'Horarios médicos')
@section('content')
<div class="pagehead"><div><h1>Horarios y bloqueos médicos</h1><div class="muted">Configura los turnos semanales y los periodos en los que un profesional no estará disponible.</div></div></div>
<div class="grid">
    <section class="card">
        <h2 class="sectiontitle">Agregar horario semanal</h2>
        <form method="POST" action="{{ route('horarios.store') }}">
            @csrf
            <div class="field"><label>Profesional *</label><select name="professional_id" required><option value="">Seleccionar</option>@foreach($professionals as $p)<option value="{{ $p->id }}">{{ $p->user?->name }} · {{ $p->specialty?->name }}</option>@endforeach</select></div>
            <div class="field" style="margin-top:10px"><label>Día *</label><select name="day_of_week" required>@foreach([1=>'Lunes',2=>'Martes',3=>'Miércoles',4=>'Jueves',5=>'Viernes',6=>'Sábado',7=>'Domingo'] as $day=>$label)<option value="{{ $day }}">{{ $label }}</option>@endforeach</select></div>
            <div class="field" style="margin-top:10px"><label>Hora de inicio *</label><input type="time" name="start_time" required></div>
            <div class="field" style="margin-top:10px"><label>Hora de fin *</label><input type="time" name="end_time" required></div>
            <div class="field" style="margin-top:10px"><label>Duración de cada cita *</label><select name="slot_duration" required>@foreach([10,15,20,30,45,60,90,120] as $n)<option value="{{ $n }}" @selected($n===20)>{{ $n }} minutos</option>@endforeach</select></div>
            <button class="btn" style="margin-top:12px">Guardar horario</button>
        </form>
    </section>
    <section class="card">
        <h2 class="sectiontitle">Registrar bloqueo / ausencia</h2>
        <form method="POST" action="{{ route('horarios.blocks.store') }}">
            @csrf
            <div class="field"><label>Profesional *</label><select name="professional_id" required><option value="">Seleccionar</option>@foreach($professionals as $p)<option value="{{ $p->id }}">{{ $p->user?->name }} · {{ $p->specialty?->name }}</option>@endforeach</select></div>
            <div class="field" style="margin-top:10px"><label>Inicio *</label><input type="datetime-local" name="starts_at" min="{{ now()->format('Y-m-d\TH:i') }}" required></div>
            <div class="field" style="margin-top:10px"><label>Fin *</label><input type="datetime-local" name="ends_at" required></div>
            <div class="field" style="margin-top:10px"><label>Motivo *</label><input name="reason" maxlength="255" required placeholder="Vacaciones, reunión, permiso..."></div>
            <button class="btn" style="margin-top:12px">Guardar bloqueo</button>
        </form>
    </section>
</div>
@php($dayNames = [1=>'Lunes',2=>'Martes',3=>'Miércoles',4=>'Jueves',5=>'Viernes',6=>'Sábado',7=>'Domingo'])
<div class="card tablewrap">
    <h2 class="sectiontitle">Horarios registrados</h2>
    <table><thead><tr><th>Profesional</th><th>Especialidad</th><th>Día</th><th>Turno</th><th>Duración</th><th></th></tr></thead><tbody>
    @forelse($professionals as $professional)
        @foreach($professional->schedules->sortBy('day_of_week') as $schedule)
            <tr><td>{{ $professional->user?->name }}</td><td>{{ $professional->specialty?->name }}</td><td>{{ $dayNames[$schedule->day_of_week] ?? $schedule->day_of_week }}</td><td>{{ substr($schedule->start_time,0,5) }} – {{ substr($schedule->end_time,0,5) }}</td><td>{{ $schedule->slot_duration }} min</td><td><form method="POST" action="{{ route('horarios.destroy',$schedule) }}" onsubmit="return confirm('¿Eliminar este horario?')">@csrf @method('DELETE')<button class="btn danger small">Eliminar</button></form></td></tr>
        @endforeach
    @empty
        <tr><td colspan="6" class="empty">No hay profesionales activos.</td></tr>
    @endforelse
    </tbody></table>
</div>
<div class="card tablewrap">
    <h2 class="sectiontitle">Bloqueos próximos</h2>
    <table><thead><tr><th>Profesional</th><th>Inicio</th><th>Fin</th><th>Motivo</th><th>Registrado por</th><th></th></tr></thead><tbody>
    @forelse($blocks as $block)
        <tr><td>{{ $block->professional?->user?->name }}</td><td>{{ $block->starts_at->format('d/m/Y H:i') }}</td><td>{{ $block->ends_at->format('d/m/Y H:i') }}</td><td>{{ $block->reason }}</td><td>{{ $block->creator?->name }}</td><td><form method="POST" action="{{ route('horarios.blocks.destroy',$block) }}" onsubmit="return confirm('¿Eliminar este bloqueo?')">@csrf @method('DELETE')<button class="btn danger small">Eliminar</button></form></td></tr>
    @empty <tr><td colspan="6" class="empty">No hay bloqueos futuros.</td></tr> @endforelse
    </tbody></table>
    {{ $blocks->links() }}
</div>
@endsection

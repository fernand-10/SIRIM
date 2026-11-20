@extends('layouts.app')
@section('title', 'Reportes y Estadísticas')

@section('content')
<div class="pagehead">
    <div>
        <h1>Reportes del Sistema</h1>
        <div class="muted">
            Bienvenido, {{ auth()->user()?->name ?? 'Usuario' }} ({{ auth()->user()?->roleLabel() ?? 'Sin Rol' }})
        </div>
    </div>
</div>

<form class="searchrow" method="GET" action="{{ route('reportes.index') }}">
    <div class="field">
        <label>Fecha inicio</label>
        <input type="date" name="start_date" value="{{ $startDate ?? '' }}">
    </div>
    <div class="field">
        <label>Fecha fin</label>
        <input type="date" name="end_date" value="{{ $endDate ?? '' }}">
    </div>
    <button class="btn" type="submit">Filtrar</button>
</form>

<div class="grid cards3 gap-4 my-4">
    <div class="card">
        <h3>Citas Totales</h3>
        <p class="stat-number">{{ $totalAppointments ?? 0 }}</p>
    </div>
    <div class="card">
        <h3>Interconsultas Pendientes</h3>
        <p class="stat-number">{{ count($pendingInterconsultations ?? []) }}</p>
    </div>
</div>

{{-- Tabla de Interconsultas Pendientes --}}
<div class="card tablewrap">
    <h3>Interconsultas por Atender</h3>
    <table>
        <thead>
            <tr>
                <th>Código</th>
                <th>Paciente</th>
                <th>Especialidad Destino</th>
                <th>Prioridad</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            @forelse($pendingInterconsultations ?? [] as $item)
                <tr>
                    <td><strong>{{ $item->code_display ?? ('INT-' . $item->id) }}</strong></td>
                    <td>
                        {{ $item->patient?->full_name ?? trim(($item->patient?->first_name ?? '') . ' ' . ($item->patient?->last_name ?? '')) }}
                        @if($item->patient?->document_number)
                            <br><small class="muted">Documento: {{ $item->patient->document_number }}</small>
                        @endif
                    </td>
                    <td>{{ $item->specialty?->name ?? $item->toSpecialty?->name ?? 'General' }}</td>
                    <td>{{ ucfirst($item->priority ?? 'normal') }}</td>
                    <td>
                        <span class="badge {{ $item->status==='pendiente'?'amber':'' }}">
                            {{ $item->statusLabel() }}
                        </span>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="empty">No hay interconsultas pendientes en este rango de fechas.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
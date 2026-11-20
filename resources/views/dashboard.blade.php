@extends('layouts.app')
@section('title', 'Panel principal')
@section('content')
<div class="pagehead">
    <div><h1>Panel principal</h1><div class="muted">Resumen operativo de SIRIM · {{ now()->format('d/m/Y') }}</div></div>
</div>
<div class="grid-4">
    <div class="stat"><div class="label">Pacientes registrados</div><div class="num">{{ $totalPatients }}</div></div>
    <div class="stat"><div class="label">Referencias activas</div><div class="num">{{ $activeReferrals }}</div></div>
    <div class="stat"><div class="label">Interconsultas pendientes</div><div class="num">{{ $pendingInterconsultations }}</div></div>
    <div class="stat"><div class="label">Citas para hoy</div><div class="num">{{ $todayAppointments }}</div></div>
</div>
<div class="grid" style="margin-top:16px">
    <section class="card tablewrap">
        <h2 class="sectiontitle">Últimas referencias</h2>
        <table><thead><tr><th>Paciente</th><th>Especialidad</th><th>Estado</th></tr></thead><tbody>
        @forelse($recentReferrals as $referral)
            <tr><td><a href="{{ route('referencias.show',$referral) }}">{{ $referral->patient?->full_name ?? 'Paciente no disponible' }}</a></td><td>{{ $referral->specialty?->name ?? 'Sin especialidad' }}</td><td>{{ $referral->statusLabel() }}</td></tr>
        @empty <tr><td colspan="3" class="empty">No hay referencias recientes.</td></tr> @endforelse
        </tbody></table>
    </section>
    <section class="card tablewrap">
        <h2 class="sectiontitle">Interconsultas prioritarias</h2>
        <table><thead><tr><th>Paciente</th><th>Recorrido</th><th>Prioridad / estado</th></tr></thead><tbody>
        @forelse($recentInterconsultations as $item)
            <tr><td><a href="{{ route('interconsultas.show',$item) }}">{{ $item->patient?->full_name ?? 'Paciente no disponible' }}</a></td><td>{{ $item->fromSpecialty?->name }} → {{ $item->toSpecialty?->name }}</td><td>{{ ucfirst($item->priority) }} · {{ $item->statusLabel() }}</td></tr>
        @empty <tr><td colspan="3" class="empty">No hay interconsultas pendientes.</td></tr> @endforelse
        </tbody></table>
    </section>
</div>
@endsection

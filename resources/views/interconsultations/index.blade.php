@extends('layouts.app')
@section('title','Interconsultas')

@section('content')
<div class="pagehead">
    <div>
        <h1>Interconsultas</h1>
        <div class="muted">Solicitudes entre especialidades, incluidas las derivaciones sucesivas.</div>
    </div>
    @can('create', \App\Models\Interconsultation::class)<a class="btn" href="{{ route('interconsultas.create') }}">+ Generar interconsulta</a>@endcan
</div>

<form class="searchrow" method="GET">
    <input name="q" value="{{ $q ?? '' }}" placeholder="Código, DNI o paciente">
    <select name="status">
        <option value="">Todos los estados</option>
        @foreach(['pendiente'=>'Pendiente','programada'=>'Programada','atendida'=>'Atendida','cancelada'=>'Cancelada'] as $v=>$label)
            <option value="{{ $v }}" @selected(($status ?? '')===$v)>{{ $label }}</option>
        @endforeach
    </select>
    <button class="btn">Filtrar</button>
    <a class="btn secondary" href="{{ route('interconsultas.index') }}">Limpiar</a>
</form>

<div class="card tablewrap">
    <table>
        <thead>
            <tr>
                <th>Código</th>
                <th>Paciente</th>
                <th>Origen → Destino</th>
                <th>Referencia</th>
                <th>Prioridad</th>
                <th>Estado</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse($interconsultations as $i)
                <tr>
                    <td>
                        <strong><a href="{{ route('interconsultas.show', $i) }}">{{ $i->code_display }}</a></strong><br>
                        <span class="muted">{{ $i->requested_at?->format('d/m/Y H:i') ?? $i->created_at?->format('d/m/Y H:i') }}</span>
                    </td>
                    <td>
                        {{ $i->patient?->first_name }} {{ $i->patient?->last_name }}
                        @if($i->patient?->document_number)
                            <br><small class="muted">Documento: {{ $i->patient->document_number }}</small>
                        @endif
                    </td>
                    <td>
                        {{ $i->fromSpecialty?->name ?? 'General' }}
                        →
                        {{ $i->specialty?->name ?? $i->toSpecialty?->name ?? 'Sin Especialidad' }}
                    </td>
                    <td>{{ $i->reference?->code ?? '—' }}</td>
                    <td>{{ ucfirst($i->priority ?? 'normal') }}</td>
                    <td>
                        <span class="badge {{ $i->status==='atendida'?'green':($i->status==='pendiente'?'amber':'') }}">
                            {{ $i->statusLabel() }}
                        </span>
                    </td>
                    <td><a class="btn secondary small" href="{{ route('interconsultas.show', $i) }}">Detalle</a></td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="empty">No se encontraron interconsultas.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
    {{ $interconsultations->links() }}
</div>
@endsection
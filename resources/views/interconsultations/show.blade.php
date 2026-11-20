@extends('layouts.app')

@section('title', 'Interconsulta ' . $interconsultation->code)

@section('content')

    {{-- ENCABEZADO --}}
    <div class="pagehead">
        <div>
            <h1>Interconsulta {{ $interconsultation->code }}</h1>

            <div class="muted">
                {{ $interconsultation->patient->full_name }}
                ·
                {{ $interconsultation->patient->document_number }}
            </div>
        </div>

        <div class="actions">
            @if(
                !in_array($interconsultation->status, ['atendida', 'cancelada'])
            )
                <a
                    class="btn"
                    href="{{ route('citas.create', [
                        'patient_id' => $interconsultation->patient_id,
                        'reference_id' => $interconsultation->reference_id,
                        'interconsultation_id' => $interconsultation->id
                    ]) }}"
                >
                    + Programar cita
                </a>

                @if(auth()->user()->isAdmin() || auth()->user()->isDoctor())
                    <a class="btn secondary" href="{{ route('atenciones.create', ['patient_id' => $interconsultation->patient_id, 'interconsultation' => $interconsultation->id]) }}">Registrar atención</a>
                @endif
            @endif

            @can('create', \App\Models\Interconsultation::class)
                <a class="btn secondary" href="{{ route('interconsultas.create', ['parent' => $interconsultation->id]) }}">+ Interconsulta sucesiva</a>
            @endcan
            @can('update', $interconsultation)
                @if(!in_array($interconsultation->status, ['atendida', 'cancelada']))
                    <form method="POST" action="{{ route('interconsultas.status', $interconsultation) }}" onsubmit="return confirm('¿Cancelar esta interconsulta?')">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="status" value="cancelada">
                        <input type="hidden" name="observations" value="Cancelada por usuario autorizado.">
                        <button class="btn danger" type="submit">Cancelar interconsulta</button>
                    </form>
                @endif
            @endcan
        </div>
    </div>

    {{-- RESUMEN DE LA INTERCONSULTA --}}
    <div class="grid-3">

        <div class="card">
            <span class="muted">Recorrido asistencial</span>

            <h3>
                {{ $interconsultation->fromSpecialty->name }}
                →
                {{ $interconsultation->toSpecialty->name }}
            </h3>

            <span class="badge
                {{ $interconsultation->status === 'atendida'
                    ? 'green'
                    : ($interconsultation->status === 'pendiente'
                        ? 'amber'
                        : '') }}">
                {{ $interconsultation->statusLabel() }}
            </span>
        </div>

        <div class="card">
            <span class="muted">Referencia de origen</span>

            <h3>
                @if($interconsultation->reference)
                    <a href="{{ route(
                        'referencias.show',
                        $interconsultation->reference
                    ) }}">
                        {{ $interconsultation->reference->code }}
                    </a>
                @else
                    No vinculada
                @endif
            </h3>

            <span class="muted">
                Prioridad:
                {{ ucfirst($interconsultation->priority) }}
            </span>
        </div>

        <div class="card">
            <span class="muted">Profesional asignado</span>

            <h3>
                {{
                    $interconsultation->assignedProfessional?->user?->name
                    ?? 'Pendiente de asignación'
                }}
            </h3>

            <span class="muted">
                Solicitada por
                {{ $interconsultation->requester->name }}
            </span>
        </div>

    </div>

    {{-- SOLICITUD Y RESPUESTA --}}
    <div class="split">

        {{-- DATOS DE LA SOLICITUD --}}
        <div class="card">
            <h2 class="sectiontitle">Solicitud médica</h2>

            <p>
                <strong>Motivo de la interconsulta</strong><br>
                {{ $interconsultation->reason }}
            </p>

            <p>
                <strong>Resumen de la evaluación previa</strong><br>

                {{ $interconsultation->clinical_summary
                    ?: 'No se registró un resumen clínico.' }}
            </p>

            <p class="muted">
                Fecha de solicitud:
                {{ $interconsultation->requested_at?->format('d/m/Y H:i')
                    ?? 'No registrada' }}
            </p>

            {{-- INTERCONSULTA ANTERIOR --}}
            @if($interconsultation->parent)
                <div class="divider"></div>

                <p>
                    <strong>Interconsulta anterior:</strong>

                    <a href="{{ route(
                        'interconsultas.show',
                        $interconsultation->parent
                    ) }}">
                        {{ $interconsultation->parent->code }}
                    </a>
                </p>
            @endif

            {{-- ATENCIÓN QUE ORIGINÓ LA SOLICITUD --}}
            @if($interconsultation->originatingAttention)
                <p>
                    <strong>Atención que origina:</strong>

                    <a href="{{ route(
                        'atenciones.show',
                        $interconsultation->originatingAttention
                    ) }}">
                        Atención #{{ $interconsultation->originatingAttention->id }}
                    </a>

                    ·
                    {{
                        $interconsultation
                            ->originatingAttention
                            ->specialty?->name
                        ?? 'Especialidad no registrada'
                    }}
                </p>
            @endif

        </div>

        {{-- RESPUESTA DEL ESPECIALISTA --}}
        <div class="card">
            <h2 class="sectiontitle">Respuesta del especialista</h2>

            @if($interconsultation->response_summary)
                <p>
                    {{ $interconsultation->response_summary }}
                </p>

                <p class="muted">
                    Fecha de respuesta:
                    {{ $interconsultation->answered_at?->format('d/m/Y H:i')
                        ?? 'No registrada' }}
                </p>
            @else
                <p class="muted">
                    Todavía no se ha registrado la respuesta del especialista.
                </p>
            @endif

            {{-- INTERCONSULTAS SUCESIVAS --}}
            @if($interconsultation->children->isNotEmpty())
                <div class="divider"></div>

                <h3 class="sectiontitle">
                    Interconsultas sucesivas
                </h3>

                <div class="timeline">
                    @foreach($interconsultation->children as $child)
                        <div class="event">
                            <p>
                                <a href="{{ route(
                                    'interconsultas.show',
                                    $child
                                ) }}">
                                    <strong>{{ $child->code }}</strong>
                                </a>
                            </p>

                            <p>
                                {{ $child->fromSpecialty->name }}
                                →
                                {{ $child->toSpecialty->name }}
                            </p>

                            <span class="badge">
                                {{ $child->statusLabel() }}
                            </span>
                        </div>
                    @endforeach
                </div>
            @endif

        </div>

    </div>

    {{-- ATENCIONES MÉDICAS VINCULADAS --}}
    <div class="card">
        <h2 class="sectiontitle">Atenciones vinculadas</h2>

        <div class="tablewrap">
            <table>
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Profesional</th>
                        <th>Resultado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($interconsultation->attentions as $attention)
                        <tr>
                            <td>
                                {{ $attention->attended_at?->format('d/m/Y H:i')
                                    ?? 'Sin fecha' }}
                            </td>

                            <td>
                                {{
                                    $attention->professional?->user?->name
                                    ?? 'No asignado'
                                }}
                            </td>

                            <td>
                                {{
                                    \Illuminate\Support\Str::limit(
                                        $attention->outcome ?? '',
                                        130
                                    )
                                }}
                            </td>

                            <td>
                                @if(in_array(
                                    auth()->user()->role,
                                    ['admin', 'doctor']
                                ))
                                    <a
                                        class="btn secondary small"
                                        href="{{ route(
                                            'atenciones.show',
                                            $attention
                                        ) }}"
                                    >
                                        Abrir
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="empty">
                                No hay atenciones médicas registradas
                                para esta interconsulta.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- REGRESAR --}}
    <div class="actions" style="margin-bottom: 20px;">
        <a
            class="btn secondary"
            href="{{ route('interconsultas.index') }}"
        >
            ← Volver a interconsultas
        </a>
    </div>

@endsection
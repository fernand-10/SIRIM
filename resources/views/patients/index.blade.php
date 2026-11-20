
@extends('layouts.app')

@section('title', 'Pacientes')

@section('content')

<div class="pagehead">
    <div>
        <h1>Pacientes</h1>
        <div class="muted">
            Registro y consulta de fichas administrativas.
        </div>
    </div>

    @if(auth()->user()->hasRole('admin','admission'))<a class="btn" href="{{ route('pacientes.create') }}">+ Registrar paciente</a>@endif
</div>

{{-- BÚSQUEDA --}}
<form
    class="searchrow"
    method="GET"
    action="{{ route('pacientes.index') }}"
>
    <input
        type="search"
        name="q"
        value="{{ $q }}"
        placeholder="Buscar por DNI, nombres o apellidos"
    >

    <button class="btn" type="submit">
        Buscar
    </button>

    <a
        class="btn secondary"
        href="{{ route('pacientes.index') }}"
    >
        Limpiar
    </a>
</form>

{{-- MENSAJE DE RESULTADOS --}}
<div class="card tablewrap">
    <table>
        <thead>
            <tr>
                <th>Documento</th>
                <th>Paciente</th>
                <th>Sexo</th>
                <th>Teléfono</th>
                <th>Registrado</th>
                <th>Acciones</th>
            </tr>
        </thead>

        <tbody>
            @forelse($patients as $p)
                <tr>
                    <td>
                        {{ $p->document_type }}
                        {{ $p->document_number }}
                    </td>

                    <td>
                        <a href="{{ route('pacientes.show', $p) }}">
                            <strong>{{ $p->full_name }}</strong>
                        </a>
                    </td>

                    <td>{{ $p->sex ?? '—' }}</td>

                    <td>{{ $p->phone ?? '—' }}</td>

                    <td>
                        {{ $p->created_at->format('d/m/Y') }}
                    </td>

                    <td>
                        <div class="actions">

                            {{-- CONSULTAR --}}
                            <a
                                class="btn secondary small"
                                href="{{ route('pacientes.show', $p) }}"
                            >
                                Ver
                            </a>

                            {{-- EDITAR --}}
                            @if(auth()->user()->hasRole('admin','admission'))<a class="btn small" href="{{ route('pacientes.edit', $p) }}">Editar</a>@endif

                            {{-- ELIMINAR: SOLO ADMINISTRADOR --}}
                            @if(auth()->user()->role === 'admin')
                                <form
                                    method="POST"
                                    action="{{ route('pacientes.destroy', $p) }}"
                                    onsubmit="return confirm('¿Eliminar definitivamente a {{ $p->full_name }}? Esta acción no se puede deshacer. Si tiene registros relacionados, el sistema bloqueará la eliminación.');"
                                >
                                    @csrf
                                    @method('DELETE')

                                    <button
                                        type="submit"
                                        class="btn danger small"
                                    >
                                        Eliminar
                                    </button>
                                </form>
                            @endif

                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="empty">
                        No se encontraron pacientes.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="pagination">
        {{ $patients->links() }}
    </div>
</div>

@endsection

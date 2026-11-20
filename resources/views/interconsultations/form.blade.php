@extends('layouts.app')
@section('title', 'Nueva interconsulta')

@section('content')
<div class="pagehead">
    <div>
        <h1>Generar interconsulta</h1>
        <div class="muted">La solicitud debe basarse en el criterio del profesional responsable.</div>
    </div>
    <a class="btn secondary" href="{{ route('interconsultas.index') }}">Volver</a>
</div>

<div class="card">
    <form method="POST" action="{{ route('interconsultas.store') }}">
        @csrf
        <div class="formgrid">
            {{-- Paciente --}}
            <div class="field">
                <label>Paciente *</label>
                <select name="patient_id" required>
                    <option value="">Seleccionar paciente</option>
                    @foreach($patients ?? [] as $p)
                        <option value="{{ $p->id }}" @selected((string)old('patient_id', $parentRecord?->patient_id ?? request('patient_id')) === (string)$p->id)>
                            {{ $p->dni ?? $p->document_number ?? 'S/D' }} · {{ $p->full_name ?? ($p->first_name . ' ' . $p->last_name) }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Referencia relacionada --}}
            <div class="field">
                <label>Referencia relacionada</label>
                <select name="reference_id">
                    <option value="">Sin referencia vinculada</option>
                    @foreach($references ?? [] as $r)
                        <option value="{{ $r->id }}" @selected((string)old('reference_id', $parentRecord?->reference_id ?? request('reference_id')) === (string)$r->id)>
                            {{ $r->code }} · {{ $r->patient?->full_name ?? ($r->patient?->first_name . ' ' . $r->patient?->last_name) }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Interconsulta anterior (cadena) --}}
            <div class="field">
                <label>Interconsulta anterior (si es sucesiva)</label>
                <select name="parent_interconsultation_id">
                    <option value="">Es la primera de la cadena</option>
                    @foreach($parents ?? [] as $p)
                        <option value="{{ $p->id }}" @selected((string)old('parent_interconsultation_id', $parentRecord?->id) === (string)$p->id)>
                            {{ $p->code ?? ('INT-' . $p->id) }} · {{ $p->patient?->full_name ?? ($p->patient?->first_name . ' ' . $p->patient?->last_name) }} · {{ $p->fromSpecialty?->name ?? 'General' }} → {{ $p->toSpecialty?->name ?? $p->specialty?->name }}
                        </option>
                    @endforeach
                </select>
                <span class="hint">La anterior debe pertenecer al mismo paciente.</span>
            </div>

            {{-- Especialidad destinataria --}}
            <div class="field">
                <label>Especialidad destinataria *</label>
                <select name="to_specialty_id" id="to_specialty_id" required>
                    <option value="">Seleccionar</option>
                    @foreach($specialties ?? [] as $s)
                        <option value="{{ $s->id }}" @selected((string)old('to_specialty_id') === (string)$s->id)>{{ $s->name }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Profesional destinatario --}}
            <div class="field">
                <label>Profesional destinatario (opcional)</label>
                <select name="assigned_professional_id" id="assigned_professional_id">
                    <option value="">Asignar después</option>
                    @foreach($professionals ?? [] as $p)
                        <option value="{{ $p->id }}" @selected((string)old('assigned_professional_id') === (string)$p->id)>
                            {{ $p->user?->name ?? $p->name }} · {{ $p->specialty?->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Atención previa --}}
            <div class="field">
                <label>Atención que origina la solicitud (opcional)</label>
                <select name="originating_attention_id">
                    <option value="">Sin atención previa vinculada</option>
                    @foreach($attentions ?? [] as $a)
                        <option value="{{ $a->id }}" @selected((string)old('originating_attention_id') === (string)$a->id)>
                            {{ $a->attended_at?->format('d/m/Y H:i') ?? $a->created_at?->format('d/m/Y H:i') }} · {{ $a->patient?->full_name ?? ($a->patient?->first_name . ' ' . $a->patient?->last_name) }} · {{ $a->specialty?->name ?? 'General' }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Especialidad solicitante --}}
            <div class="field">
                <label>Especialidad que solicita *</label>
                <select name="from_specialty_id" required>
                    <option value="">Seleccionar</option>
                    @foreach($specialties ?? [] as $s)
                        <option value="{{ $s->id }}" @selected((string)old('from_specialty_id', auth()->user()->professional?->specialty_id) === (string)$s->id)>{{ $s->name }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Prioridad --}}
            <div class="field">
                <label>Prioridad *</label>
                <select name="priority" required>
                    @foreach(['normal' => 'Normal', 'preferente' => 'Preferente', 'urgente' => 'Urgente'] as $v => $label)
                        <option value="{{ $v }}" @selected(old('priority', 'normal') === $v)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Motivo clínico --}}
            <div class="field full">
                <label>Motivo clínico de la interconsulta *</label>
                <textarea name="reason" required maxlength="2000">{{ old('reason') }}</textarea>
            </div>

            {{-- Resumen de evaluación --}}
            <div class="field full">
                <label>Resumen de evaluación previa</label>
                <textarea name="clinical_summary" maxlength="5000">{{ old('clinical_summary') }}</textarea>
            </div>
        </div>
        <div class="divider"></div>
        <button class="btn" type="submit">Registrar interconsulta</button>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const specialtySelect = document.getElementById('to_specialty_id');
    const professionalSelect = document.getElementById('assigned_professional_id');

    if (specialtySelect && professionalSelect) {
        specialtySelect.addEventListener('change', function () {
            const specialtyId = this.value;

            professionalSelect.innerHTML = '<option value="">Cargando profesionales...</option>';

            if (!specialtyId) {
                professionalSelect.innerHTML = '<option value="">Asignar después</option>';
                return;
            }

            fetch(`/api/especialidades/${specialtyId}/profesionales`)
                .then(response => response.json())
                .then(data => {
                    professionalSelect.innerHTML = '<option value="">Asignar después</option>';

                    if (!data || data.length === 0) {
                        const option = document.createElement('option');
                        option.textContent = 'No hay profesionales en esta especialidad';
                        option.disabled = true;
                        professionalSelect.appendChild(option);
                        return;
                    }

                    data.forEach(professional => {
                        const option = document.createElement('option');
                        option.value = professional.id;
                        const profName = professional.name || (professional.user ? professional.user.name : 'Profesional');
                        option.textContent = profName;
                        professionalSelect.appendChild(option);
                    });
                })
                .catch(error => {
                    console.error('Error al filtrar profesionales:', error);
                    professionalSelect.innerHTML = '<option value="">Asignar después</option>';
                });
        });
    }
});
</script>
@endsection
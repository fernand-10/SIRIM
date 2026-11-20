@extends('layouts.app')
@section('title', 'Registrar atención')
@section('content')
<div class="pagehead">
    <div><h1>Registrar atención médica</h1><div class="muted">Registra la evaluación y el resultado de la atención.</div></div>
    <a class="btn secondary" href="{{ route('atenciones.index') }}">Volver</a>
</div>
<div class="card">
    <form method="POST" action="{{ route('atenciones.store') }}">
        @csrf
        <div class="formgrid">
            <div class="field">
                <label>Paciente *</label>
                <select name="patient_id" required>
                    <option value="">Seleccionar paciente</option>
                    @foreach($patients as $p)
                        <option value="{{ $p->id }}" @selected((string)old('patient_id',$appointment?->patient_id ?? request('patient_id')) === (string)$p->id)>
                            {{ $p->document_number }} · {{ $p->full_name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label>Fecha y hora de atención *</label>
                <input type="datetime-local" name="attended_at" max="{{ now()->format('Y-m-d\TH:i') }}"
                    value="{{ old('attended_at', now()->format('Y-m-d\TH:i')) }}" required>
            </div>
            <div class="field">
                <label>Profesional responsable *</label>
                <select name="professional_id" required>
                    <option value="">Seleccionar</option>
                    @foreach($professionals as $p)
                        <option value="{{ $p->id }}" @selected((string)old('professional_id',auth()->user()->professional?->id)===(string)$p->id)>
                            {{ $p->user?->name }} · {{ $p->specialty?->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label>Especialidad *</label>
                <select name="specialty_id" required>
                    <option value="">Seleccionar</option>
                    @foreach($specialties as $s)
                        <option value="{{ $s->id }}" @selected((string)old('specialty_id',$appointment?->specialty_id ?? auth()->user()->professional?->specialty_id)===(string)$s->id)>{{ $s->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label>Referencia</label>
                <select name="reference_id">
                    <option value="">Ninguna</option>
                    @foreach($references as $r)
                        <option value="{{ $r->id }}" @selected((string)old('reference_id',$appointment?->reference_id)===(string)$r->id)>{{ $r->code }} · {{ $r->patient?->full_name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label>Interconsulta respondida</label>
                <select name="interconsultation_id">
                    <option value="">Ninguna</option>
                    @foreach($interconsultations as $i)
                        <option value="{{ $i->id }}" @selected((string)old('interconsultation_id',$appointment?->interconsultation_id ?? $requestedInterconsultation?->id)===(string)$i->id)>{{ $i->code_display }} · {{ $i->patient?->full_name }} · {{ $i->toSpecialty?->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label>Cita relacionada</label>
                <select name="appointment_id">
                    <option value="">Sin cita vinculada</option>
                    @foreach($appointments as $a)
                        <option value="{{ $a->id }}" @selected((string)old('appointment_id',$appointment?->id)===(string)$a->id)>{{ $a->scheduled_at->format('d/m/Y H:i') }} · {{ $a->patient?->full_name }} · {{ $a->specialty?->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field full">
                <label>Motivo de atención *</label>
                <input name="reason" value="{{ old('reason',$appointment?->interconsultation?->reason) }}" required maxlength="2000">
            </div>
            <div class="field full">
                <label>Notas clínicas</label>
                <textarea name="clinical_notes" maxlength="10000">{{ old('clinical_notes') }}</textarea>
            </div>
            <div class="field full">
                <label>Resultado / respuesta del especialista *</label>
                <textarea name="outcome" required maxlength="5000">{{ old('outcome') }}</textarea>
                <span class="hint">Al vincular una interconsulta, este resultado se registra como su respuesta.</span>
            </div>
            <div class="field full">
                <label>Plan / indicaciones de continuidad</label>
                <textarea name="plan" maxlength="5000">{{ old('plan') }}</textarea>
            </div>
        </div>
        <div class="divider"></div>
        <button class="btn" type="submit">Guardar atención</button>
    </form>
</div>
@endsection

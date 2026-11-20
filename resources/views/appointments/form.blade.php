@extends('layouts.app')
@section('title', 'Programar cita')
@section('content')
<div class="pagehead">
    <div>
        <h1>Programar cita médica</h1>
        <div class="muted">La programación es realizada por personal autorizado del hospital.</div>
    </div>
    <a class="btn secondary" href="{{ route('citas.index') }}">Volver a agenda</a>
</div>

<div class="card">
    <form method="POST" action="{{ route('citas.store') }}">
        @csrf
        <div class="formgrid">
            <div class="field">
                <label for="patient_id">Paciente *</label>
                <select name="patient_id" id="patient_id" required>
                    <option value="">Seleccionar paciente</option>
                    @foreach($patients as $patient)
                        <option value="{{ $patient->id }}" @selected((string)old('patient_id', $prefillPatient) === (string)$patient->id)>
                            {{ $patient->document_number }} · {{ $patient->full_name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label for="specialty_id">Especialidad *</label>
                <select name="specialty_id" id="specialty_id" required>
                    <option value="">Seleccionar especialidad</option>
                    @foreach($specialties as $specialty)
                        <option value="{{ $specialty->id }}" @selected((string)old('specialty_id', auth()->user()->isDoctor() ? auth()->user()->professional?->specialty_id : '') === (string)$specialty->id)>
                            {{ $specialty->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label for="professional_id">Profesional *</label>
                <select name="professional_id" id="professional_id" required>
                    <option value="">Seleccionar profesional</option>
                    @foreach($professionals as $professional)
                        <option value="{{ $professional->id }}" data-specialty="{{ $professional->specialty_id }}"
                            @selected((string)old('professional_id', auth()->user()->isDoctor() ? auth()->user()->professional?->id : '') === (string)$professional->id)>
                            {{ $professional->user?->name ?? 'Profesional #' . $professional->id }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label for="scheduled_at">Fecha y hora *</label>
                <input type="datetime-local" name="scheduled_at" id="scheduled_at"
                    min="{{ now()->format('Y-m-d\TH:i') }}"
                    value="{{ old('scheduled_at', now()->addHour()->format('Y-m-d\TH:i')) }}" required>
            </div>

            <div class="field">
                <label for="duration_minutes">Duración *</label>
                <select name="duration_minutes" id="duration_minutes" required>
                    @foreach([10,15,20,30,45,60,90,120] as $minutes)
                        <option value="{{ $minutes }}" @selected((int)old('duration_minutes',20) === $minutes)>{{ $minutes }} minutos</option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label for="reference_id">Referencia relacionada</label>
                <select name="reference_id" id="reference_id">
                    <option value="">Sin referencia</option>
                    @foreach($references as $reference)
                        <option value="{{ $reference->id }}" data-patient="{{ $reference->patient_id }}"
                            @selected((string)old('reference_id',$prefillReferral)===(string)$reference->id)>
                            {{ $reference->code }} · {{ $reference->patient?->full_name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label for="interconsultation_id">Interconsulta relacionada</label>
                <select name="interconsultation_id" id="interconsultation_id">
                    <option value="">Sin interconsulta</option>
                    @foreach($interconsultations as $interconsultation)
                        <option value="{{ $interconsultation->id }}"
                            data-patient="{{ $interconsultation->patient_id }}"
                            data-specialty="{{ $interconsultation->to_specialty_id }}"
                            @selected((string)old('interconsultation_id',$prefillInterconsultation)===(string)$interconsultation->id)>
                            {{ $interconsultation->code_display }} · {{ $interconsultation->patient?->full_name }} · {{ $interconsultation->toSpecialty?->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="field full">
                <label for="notes">Observaciones</label>
                <textarea name="notes" id="notes" maxlength="2000">{{ old('notes') }}</textarea>
            </div>
        </div>
        <div class="divider"></div>
        <button class="btn" type="submit">Guardar cita</button>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const patient = document.getElementById('patient_id');
    const specialty = document.getElementById('specialty_id');
    const professional = document.getElementById('professional_id');
    const reference = document.getElementById('reference_id');
    const interconsultation = document.getElementById('interconsultation_id');

    function filterOptions(select, predicate) {
        if (!select) return;
        [...select.options].forEach((option, index) => {
            if (index === 0) return;
            option.hidden = !predicate(option);
            if (option.selected && option.hidden) select.value = '';
        });
    }

    function filterProfessionals() {
        const specialtyId = specialty.value;
        filterOptions(professional, option => !specialtyId || option.dataset.specialty === specialtyId);
    }

    function filterPatientRelations() {
        const patientId = patient.value;
        filterOptions(reference, option => !patientId || option.dataset.patient === patientId);
        filterOptions(interconsultation, option => {
            const samePatient = !patientId || option.dataset.patient === patientId;
            const sameSpecialty = !specialty.value || option.dataset.specialty === specialty.value;
            return samePatient && sameSpecialty;
        });
    }

    specialty.addEventListener('change', () => {
        filterProfessionals();
        filterPatientRelations();
    });
    patient.addEventListener('change', filterPatientRelations);
    filterProfessionals();
    filterPatientRelations();
});
</script>
@endsection

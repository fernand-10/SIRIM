@extends('layouts.app')
@section('title', 'Programar cita')
@section('content')

@php
    $previousScheduledAt = old('scheduled_at');

    $initialDate = $previousScheduledAt
        ? \Illuminate\Support\Carbon::parse($previousScheduledAt)->format('Y-m-d')
        : now()->toDateString();

    $initialDuration = (int) old('duration_minutes', 20);
@endphp

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
                        <option value="{{ $patient->id }}"
                            @selected((string)old('patient_id', $prefillPatient) === (string)$patient->id)>
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
                        <option value="{{ $specialty->id }}"
                            @selected((string)old('specialty_id', auth()->user()->isDoctor() ? auth()->user()->professional?->specialty_id : '') === (string)$specialty->id)>
                            {{ $specialty->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label for="appointment_date">Fecha de la cita *</label>
                <input
                    type="date"
                    id="appointment_date"
                    min="{{ now()->toDateString() }}"
                    value="{{ old('appointment_date', $initialDate) }}"
                    required
                >
            </div>

            <div class="field">
                <label for="duration_minutes">Duración de la cita *</label>
                <select name="duration_minutes" id="duration_minutes" required>
                    @foreach([10, 15, 20, 30, 45, 60, 90, 120] as $minutes)
                        <option value="{{ $minutes }}" @selected($initialDuration === $minutes)>
                            {{ $minutes }} minutos
                        </option>
                    @endforeach
                </select>
                <div class="muted">Se mostrarán los turnos que tengan esta duración configurada.</div>
            </div>

            <div class="field full">
                <label for="available_slot">Horarios disponibles de la especialidad *</label>
                <select id="available_slot" required disabled>
                    <option value="">Selecciona una especialidad y una fecha</option>
                </select>
                <div class="muted" id="availability_message" role="status" aria-live="polite">
                    Selecciona una especialidad y una fecha para consultar todos sus profesionales.
                </div>
            </div>

            <div class="field">
                <label for="selected_professional">Profesional seleccionado</label>
                <input type="text" id="selected_professional" value="Aún no has seleccionado un horario" readonly>
                <input
                    type="hidden"
                    name="professional_id"
                    id="professional_id"
                    value="{{ old('professional_id') }}"
                >
            </div>

            <input
                type="hidden"
                name="scheduled_at"
                id="scheduled_at"
                value="{{ old('scheduled_at') }}"
            >

            <div class="field">
                <label for="reference_id">Referencia relacionada</label>
                <select name="reference_id" id="reference_id">
                    <option value="">Sin referencia</option>
                    @foreach($references as $reference)
                        <option value="{{ $reference->id }}"
                            data-patient="{{ $reference->patient_id }}"
                            @selected((string)old('reference_id', $prefillReferral) === (string)$reference->id)>
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
                            data-professional="{{ $interconsultation->assigned_professional_id }}"
                            @selected((string)old('interconsultation_id', $prefillInterconsultation) === (string)$interconsultation->id)>
                            {{ $interconsultation->code_display }}
                            · {{ $interconsultation->patient?->full_name }}
                            · {{ $interconsultation->toSpecialty?->name }}
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

        <button class="btn" id="submit_appointment" type="submit" disabled>
            Guardar cita
        </button>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const patient = document.getElementById('patient_id');
    const specialty = document.getElementById('specialty_id');
    const reference = document.getElementById('reference_id');
    const interconsultation = document.getElementById('interconsultation_id');

    const appointmentDate = document.getElementById('appointment_date');
    const durationSelect = document.getElementById('duration_minutes');
    const availableSlot = document.getElementById('available_slot');
    const availabilityMessage = document.getElementById('availability_message');

    const professionalId = document.getElementById('professional_id');
    const selectedProfessional = document.getElementById('selected_professional');
    const scheduledAt = document.getElementById('scheduled_at');
    const submitButton = document.getElementById('submit_appointment');

    const availabilityUrlTemplate = @json(
        route('api.especialidades.disponibilidad', ['specialtyId' => 'SPECIALTY_ID'])
    );

    let availabilityController = null;
    let preferredScheduledAt = @json(old('scheduled_at'));
    let preferredProfessionalId = @json(old('professional_id'));

    function filterOptions(select, predicate) {
        if (!select) return;

        [...select.options].forEach((option, index) => {
            if (index === 0) return;

            option.hidden = !predicate(option);

            if (option.selected && option.hidden) {
                select.value = '';
            }
        });
    }

    function filterPatientRelations() {
        const patientId = patient.value;
        const specialtyId = specialty.value;

        filterOptions(reference, option => {
            return !patientId || option.dataset.patient === patientId;
        });

        filterOptions(interconsultation, option => {
            const samePatient = !patientId || option.dataset.patient === patientId;
            const sameSpecialty = !specialtyId || option.dataset.specialty === specialtyId;
            return samePatient && sameSpecialty;
        });
    }

    function clearAvailability(message) {
        availableSlot.innerHTML = '';

        const option = document.createElement('option');
        option.value = '';
        option.textContent = 'No hay horarios disponibles';

        availableSlot.appendChild(option);
        availableSlot.disabled = true;

        professionalId.value = '';
        scheduledAt.value = '';
        selectedProfessional.value = 'Aún no has seleccionado un horario';
        submitButton.disabled = true;

        availabilityMessage.textContent = message;
    }

    function updateSelectedSlot() {
        const option = availableSlot.options[availableSlot.selectedIndex];

        if (!option || !availableSlot.value) {
            professionalId.value = '';
            scheduledAt.value = '';
            selectedProfessional.value = 'Aún no has seleccionado un horario';
            submitButton.disabled = true;
            return;
        }

        professionalId.value = option.dataset.professionalId || '';
        scheduledAt.value = option.value;
        durationSelect.value = option.dataset.duration || durationSelect.value;
        selectedProfessional.value = option.dataset.professionalName || 'Profesional seleccionado';

        preferredScheduledAt = option.value;
        preferredProfessionalId = professionalId.value;

        submitButton.disabled = !professionalId.value || !scheduledAt.value;
    }

    async function loadAvailability() {
        const specialtyId = specialty.value;
        const currentDate = appointmentDate.value;
        const duration = durationSelect.value;
        const previousSelection = scheduledAt.value || preferredScheduledAt;
        const previousProfessional = professionalId.value || preferredProfessionalId;

        if (availabilityController) {
            availabilityController.abort();
        }

        clearAvailability('Consultando los horarios de todos los profesionales...');

        if (!specialtyId || !currentDate || !duration) {
            clearAvailability('Selecciona una especialidad, una fecha y la duración de la cita.');
            return;
        }

        const controller = new AbortController();
        availabilityController = controller;

        try {
            const url = availabilityUrlTemplate.replace(
                'SPECIALTY_ID',
                encodeURIComponent(specialtyId)
            );

            const params = new URLSearchParams();
            params.set('date', currentDate);
            params.set('duration_minutes', duration);

            if (patient.value) {
                params.set('patient_id', patient.value);
            }

            const response = await fetch(url + '?' + params.toString(), {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin',
                signal: controller.signal
            });

            const result = await response.json().catch(() => ({}));

            if (controller !== availabilityController) return;

            if (!response.ok) {
                const validationMessage = result.errors
                    ? Object.values(result.errors).flat()[0]
                    : null;

                throw new Error(
                    validationMessage
                    || result.message
                    || 'No se pudo consultar la disponibilidad de la especialidad.'
                );
            }

            const slots = Array.isArray(result.slots) ? result.slots : [];

            // Si la interconsulta está asignada a un profesional,
            // respetar esa asignación.
            const selectedInterconsultation =
                interconsultation.options[interconsultation.selectedIndex];

            const assignedProfessional = selectedInterconsultation
                ? selectedInterconsultation.dataset.professional
                : '';

            const filteredSlots = slots.filter(slot => {
                return !assignedProfessional
                    || String(slot.professional_id) === String(assignedProfessional);
            });

            availableSlot.innerHTML = '';

            if (filteredSlots.length === 0) {
                clearAvailability(
                    slots.length === 0
                        ? 'No hay horarios disponibles para esa especialidad, fecha y duración.'
                        : 'La interconsulta está asignada a otro profesional o no tiene horarios disponibles.'
                );
                return;
            }

            const placeholder = document.createElement('option');
            placeholder.value = '';
            placeholder.textContent = 'Seleccionar horario y profesional';
            availableSlot.appendChild(placeholder);

            filteredSlots.forEach(slot => {
                const option = document.createElement('option');

                option.value = slot.scheduled_at;
                option.textContent = slot.time + ' — ' + slot.professional_name;
                option.dataset.professionalId = slot.professional_id;
                option.dataset.professionalName = slot.professional_name;
                option.dataset.duration = slot.duration_minutes;

                availableSlot.appendChild(option);
            });

            availableSlot.disabled = false;

            // Recuperar la selección anterior solo cuando siga disponible.
            const previousOption = [...availableSlot.options].find(option => {
                return option.value === previousSelection
                    && String(option.dataset.professionalId) === String(previousProfessional);
            });

            if (previousOption) {
                availableSlot.value = previousOption.value;
                updateSelectedSlot();
            } else {
                availableSlot.value = '';
                updateSelectedSlot();
            }

            availabilityMessage.textContent =
                'Se encontraron ' + filteredSlots.length
                + ' horarios disponibles entre los profesionales de esta especialidad.';

        } catch (error) {
            if (error.name === 'AbortError') return;
            if (controller !== availabilityController) return;

            clearAvailability(error.message);
        }
    }

    specialty.addEventListener('change', () => {
        filterPatientRelations();
        preferredScheduledAt = '';
        preferredProfessionalId = '';
        loadAvailability();
    });

    patient.addEventListener('change', () => {
        filterPatientRelations();
        preferredScheduledAt = '';
        preferredProfessionalId = '';
        loadAvailability();
    });

    appointmentDate.addEventListener('change', () => {
        preferredScheduledAt = '';
        preferredProfessionalId = '';
        loadAvailability();
    });

    durationSelect.addEventListener('change', () => {
        preferredScheduledAt = '';
        preferredProfessionalId = '';
        loadAvailability();
    });

    interconsultation.addEventListener('change', () => {
        const selected = interconsultation.options[interconsultation.selectedIndex];

        if (selected && selected.dataset.specialty) {
            specialty.value = selected.dataset.specialty;
        }

        filterPatientRelations();
        preferredScheduledAt = '';
        preferredProfessionalId = '';
        loadAvailability();
    });

    availableSlot.addEventListener('change', updateSelectedSlot);

    filterPatientRelations();

    // Cargar horarios automáticamente si ya hay especialidad y fecha.
    loadAvailability();
});
</script>

@endsection

@extends('layouts.app')
@section('title', $patient->exists ? 'Editar paciente' : 'Registrar paciente')
@section('content')
<div class="pagehead">
    <div>
        <h1>{{ $patient->exists ? 'Editar paciente' : 'Registrar paciente' }}</h1>
        <div class="muted">Registro administrativo para uso interno del personal autorizado.</div>
    </div>
    <a class="btn secondary" href="{{ $patient->exists ? route('pacientes.show',$patient) : route('pacientes.index') }}">Volver</a>
</div>
<div class="card">
    <form method="POST" action="{{ $patient->exists ? route('pacientes.update',$patient) : route('pacientes.store') }}">
        @csrf
        @if($patient->exists) @method('PUT') @endif
        <div class="formgrid">
            <div class="field">
                <label>Tipo de documento *</label>
                <select name="document_type" id="document_type" required>
                    @foreach(['DNI','CE','Pasaporte','Otro'] as $value)
                        <option value="{{ $value }}" @selected(old('document_type',$patient->document_type ?? 'DNI') === $value)>{{ $value }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label>Número de documento *</label>
                <div class="actions" style="align-items:stretch">
                    <input style="flex:1;min-width:180px" name="document_number" id="document_number"
                        value="{{ old('document_number',$patient->document_number) }}" maxlength="30" required>
                    <button class="btn secondary" type="button" id="consultar-dni">Consultar DNI</button>
                </div>
                <span class="hint" id="reniec-message">La consulta requiere que el hospital configure su acceso autorizado a RENIEC. También puedes registrar los datos manualmente.</span>
            </div>
            <div class="field"><label>Nombres *</label><input name="first_name" id="first_name" value="{{ old('first_name',$patient->first_name) }}" maxlength="100" required></div>
            <div class="field"><label>Apellidos *</label><input name="last_name" id="last_name" value="{{ old('last_name',$patient->last_name) }}" maxlength="120" required></div>
            <div class="field"><label>Fecha de nacimiento</label><input type="date" name="birth_date" id="birth_date" max="{{ today()->toDateString() }}" value="{{ old('birth_date',$patient->birth_date?->format('Y-m-d')) }}"></div>
            <div class="field"><label>Sexo</label><select name="sex" id="sex"><option value="">No especifica</option><option value="F" @selected(old('sex',$patient->sex)==='F')>Femenino</option><option value="M" @selected(old('sex',$patient->sex)==='M')>Masculino</option><option value="No especifica" @selected(old('sex',$patient->sex)==='No especifica')>No especifica</option></select></div>
            <div class="field"><label>Teléfono</label><input name="phone" value="{{ old('phone',$patient->phone) }}" maxlength="30"></div>
            <div class="field"><label>Dirección</label><input name="address" value="{{ old('address',$patient->address) }}" maxlength="255"></div>
        </div>
        <div class="divider"></div>
        <button class="btn" type="submit">{{ $patient->exists ? 'Guardar cambios' : 'Registrar paciente' }}</button>
    </form>
</div>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const type = document.getElementById('document_type');
    const dni = document.getElementById('document_number');
    const button = document.getElementById('consultar-dni');
    const message = document.getElementById('reniec-message');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || @json(csrf_token());
    const endpoint = @json(route('reniec.consultar'));

    function toggleButton() {
        const isDni = type.value === 'DNI';
        button.disabled = !isDni;
        button.style.opacity = isDni ? '1' : '.5';
    }
    type.addEventListener('change', toggleButton);
    toggleButton();

    button.addEventListener('click', async () => {
        const value = dni.value.trim();
        if (!/^[0-9]{8}$/.test(value)) {
            message.textContent = 'Escribe un DNI válido de 8 dígitos.';
            return;
        }
        button.disabled = true;
        message.textContent = 'Consultando el servicio autorizado...';
        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
                body: JSON.stringify({ dni: value }),
            });
            const result = await response.json();
            if (!response.ok || !result.success) {
                throw new Error(result.message || 'No se pudo consultar el DNI.');
            }
            const data = result.data;
            document.getElementById('first_name').value = data.first_name || '';
            document.getElementById('last_name').value = data.last_name || '';
            document.getElementById('birth_date').value = data.birth_date || '';
            document.getElementById('sex').value = data.sex || '';
            message.textContent = 'Datos recuperados. Verifica la información antes de guardar.';
        } catch (error) {
            message.textContent = error.message + ' Puedes continuar con el registro manual.';
        } finally {
            button.disabled = false;
        }
    });
});
</script>
@endsection

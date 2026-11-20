<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Adaptador REST configurable para el proveedor autorizado por la institución.
 * La URL, autenticación y nombres exactos de campos deben corresponder al contrato
 * técnico entregado por RENIEC al hospital. No se inventa un endpoint oficial.
 */
class ReniecService
{
    public function consultarDni(string $dni): array
    {
        $url = (string) config('services.reniec.url');
        if ($url === '') {
            throw new RuntimeException('La integración RENIEC no está configurada.');
        }

        $method = strtoupper((string) config('services.reniec.method', 'POST'));
        $dniField = (string) config('services.reniec.dni_field', 'dni');
        $timeout = (int) config('services.reniec.timeout', 15);
        $token = (string) config('services.reniec.token', '');

        $client = Http::acceptJson()->timeout($timeout);
        if ($token !== '') {
            $client = $client->withToken($token);
        }

        $payload = [$dniField => $dni];
        $response = match ($method) {
            'GET' => $client->get($url, $payload),
            'POST' => $client->post($url, $payload),
            default => throw new RuntimeException('Método HTTP RENIEC no permitido. Use GET o POST.'),
        };

        if (!$response->successful()) {
            throw new RuntimeException('El proveedor de identidad respondió con HTTP ' . $response->status() . '.');
        }

        $body = $response->json();
        if (!is_array($body)) {
            throw new RuntimeException('La respuesta del proveedor no tiene formato JSON válido.');
        }

        $data = $body['data'] ?? $body['persona'] ?? $body['resultado'] ?? $body;
        if (!is_array($data)) {
            throw new RuntimeException('La respuesta no contiene datos de identidad.');
        }

        $firstName = $this->firstValue($data, ['nombres', 'nombres_completos', 'first_name', 'name']);
        $paternal = $this->firstValue($data, ['apellido_paterno', 'apepat', 'paternal_surname']);
        $maternal = $this->firstValue($data, ['apellido_materno', 'apemat', 'maternal_surname']);
        $lastName = $this->firstValue($data, ['apellidos', 'last_name', 'surname']);

        if ($lastName === '') {
            $lastName = trim($paternal . ' ' . $maternal);
        }

        if ($firstName === '' || $lastName === '') {
            throw new RuntimeException('La respuesta no contiene nombres y apellidos reconocibles. Se requiere adaptar el mapeo al contrato técnico del servicio.');
        }

        $birthDate = $this->firstValue($data, ['fecha_nacimiento', 'fechaNacimiento', 'birth_date']);
        if ($birthDate !== '' && preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $birthDate)) {
            [$day, $month, $year] = explode('/', $birthDate);
            $birthDate = $year . '-' . $month . '-' . $day;
        }

        $sexRaw = strtoupper($this->firstValue($data, ['sexo', 'sex', 'genero']));
        $sex = match ($sexRaw) {
            '1', 'M', 'MASCULINO', 'HOMBRE' => 'M',
            '2', 'F', 'FEMENINO', 'MUJER' => 'F',
            default => null,
        };

        return [
            'document_type' => 'DNI',
            'document_number' => $dni,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'birth_date' => $birthDate ?: null,
            'sex' => $sex,
        ];
    }

    private function firstValue(array $data, array $keys): string
    {
        foreach ($keys as $key) {
            if (isset($data[$key]) && is_scalar($data[$key])) {
                $value = trim((string) $data[$key]);
                if ($value !== '') {
                    return $value;
                }
            }
        }

        return '';
    }
}

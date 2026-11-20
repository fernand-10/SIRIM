<?php

namespace Database\Seeders;

use App\Models\HealthFacility;
use App\Models\Professional;
use App\Models\ProfessionalSchedule;
use App\Models\Specialty;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Datos de demostración para SIRIM.
 * Crea 3 profesionales activos por cada una de las 9 especialidades existentes.
 * Los nombres, correos y turnos son ficticios y NO representan al personal real
 * ni la programación oficial del Hospital Regional Hermilio Valdizán.
 *
 * Seguro para volver a ejecutar: usa firstOrCreate y no elimina ni modifica
 * el profesional ni los horarios preexistentes del médico de demostración.
 */
class DemoDoctorsSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
        $hospital = HealthFacility::where('name', 'Hospital Regional Hermilio Valdizán')->first();

        if (!$hospital) {
            throw new RuntimeException(
                'No se encontró el establecimiento Hospital Regional Hermilio Valdizán. No se insertó ningún profesional.'
            );
        }

        $specialtyNames = [
            'Cardiología',
            'Cirugía General',
            'Dermatología',
            'Ginecología',
            'Medicina Interna',
            'Neurología',
            'Pediatría',
            'Psicología',
            'Traumatología',
        ];

        $specialties = [];
        foreach ($specialtyNames as $name) {
            $specialties[$name] = Specialty::where('name', $name)->first();

            if (!$specialties[$name]) {
                throw new RuntimeException(
                    "No se encontró la especialidad '{$name}'. No se insertó ningún profesional."
                );
            }
        }

        // Cardiología: el primer profesional ya existe (Dr. Médico Demostración).
        // Se agregan dos adicionales para completar tres.
        $professionalsToCreate = [
            ['specialty' => 'Cardiología', 'name' => 'Dra. Valeria Rojas [DEMO]', 'email' => 'demo.cardiologia.02@sirim.test', 'license' => 'DEMO-0002', 'pattern' => 'afternoon'],
            ['specialty' => 'Cardiología', 'name' => 'Dr. Andrés Paredes [DEMO]', 'email' => 'demo.cardiologia.03@sirim.test', 'license' => 'DEMO-0003', 'pattern' => 'rotating'],

            ['specialty' => 'Cirugía General', 'name' => 'Dr. Carlos Medina [DEMO]', 'email' => 'demo.cirugia.01@sirim.test', 'license' => 'DEMO-0004', 'pattern' => 'morning'],
            ['specialty' => 'Cirugía General', 'name' => 'Dr. Javier Ponce [DEMO]', 'email' => 'demo.cirugia.02@sirim.test', 'license' => 'DEMO-0005', 'pattern' => 'afternoon'],
            ['specialty' => 'Cirugía General', 'name' => 'Dra. Elena Vargas [DEMO]', 'email' => 'demo.cirugia.03@sirim.test', 'license' => 'DEMO-0006', 'pattern' => 'rotating'],

            ['specialty' => 'Dermatología', 'name' => 'Dra. Lorena Silva [DEMO]', 'email' => 'demo.dermatologia.01@sirim.test', 'license' => 'DEMO-0007', 'pattern' => 'morning'],
            ['specialty' => 'Dermatología', 'name' => 'Dr. Mateo Rivas [DEMO]', 'email' => 'demo.dermatologia.02@sirim.test', 'license' => 'DEMO-0008', 'pattern' => 'afternoon'],
            ['specialty' => 'Dermatología', 'name' => 'Dra. Karina Cabrera [DEMO]', 'email' => 'demo.dermatologia.03@sirim.test', 'license' => 'DEMO-0009', 'pattern' => 'rotating'],

            ['specialty' => 'Ginecología', 'name' => 'Dra. Claudia Fernández [DEMO]', 'email' => 'demo.ginecologia.01@sirim.test', 'license' => 'DEMO-0010', 'pattern' => 'morning'],
            ['specialty' => 'Ginecología', 'name' => 'Dra. Rosa Espinoza [DEMO]', 'email' => 'demo.ginecologia.02@sirim.test', 'license' => 'DEMO-0011', 'pattern' => 'afternoon'],
            ['specialty' => 'Ginecología', 'name' => 'Dr. Marco Alarcón [DEMO]', 'email' => 'demo.ginecologia.03@sirim.test', 'license' => 'DEMO-0012', 'pattern' => 'rotating'],

            ['specialty' => 'Medicina Interna', 'name' => 'Dra. Mariana Torres [DEMO]', 'email' => 'demo.medicinainterna.01@sirim.test', 'license' => 'DEMO-0013', 'pattern' => 'morning'],
            ['specialty' => 'Medicina Interna', 'name' => 'Dr. Luis Cárdenas [DEMO]', 'email' => 'demo.medicinainterna.02@sirim.test', 'license' => 'DEMO-0014', 'pattern' => 'afternoon'],
            ['specialty' => 'Medicina Interna', 'name' => 'Dra. Fiorella Campos [DEMO]', 'email' => 'demo.medicinainterna.03@sirim.test', 'license' => 'DEMO-0015', 'pattern' => 'rotating'],

            ['specialty' => 'Neurología', 'name' => 'Dra. Mónica Castillo [DEMO]', 'email' => 'demo.neurologia.01@sirim.test', 'license' => 'DEMO-0016', 'pattern' => 'morning'],
            ['specialty' => 'Neurología', 'name' => 'Dr. Álvaro Sánchez [DEMO]', 'email' => 'demo.neurologia.02@sirim.test', 'license' => 'DEMO-0017', 'pattern' => 'afternoon'],
            ['specialty' => 'Neurología', 'name' => 'Dr. Raúl Benites [DEMO]', 'email' => 'demo.neurologia.03@sirim.test', 'license' => 'DEMO-0018', 'pattern' => 'rotating'],

            ['specialty' => 'Pediatría', 'name' => 'Dra. Gabriela Huamán [DEMO]', 'email' => 'demo.pediatria.01@sirim.test', 'license' => 'DEMO-0019', 'pattern' => 'morning'],
            ['specialty' => 'Pediatría', 'name' => 'Dr. Renato Salas [DEMO]', 'email' => 'demo.pediatria.02@sirim.test', 'license' => 'DEMO-0020', 'pattern' => 'afternoon'],
            ['specialty' => 'Pediatría', 'name' => 'Dra. Patricia Mendoza [DEMO]', 'email' => 'demo.pediatria.03@sirim.test', 'license' => 'DEMO-0021', 'pattern' => 'rotating'],

            // Psicología tendrá profesionales de Psicología, no médicos especialistas.
            ['specialty' => 'Psicología', 'name' => 'Lic. Natalia Herrera [DEMO]', 'email' => 'demo.psicologia.01@sirim.test', 'license' => 'DEMO-0022', 'pattern' => 'morning'],
            ['specialty' => 'Psicología', 'name' => 'Lic. Daniel Flores [DEMO]', 'email' => 'demo.psicologia.02@sirim.test', 'license' => 'DEMO-0023', 'pattern' => 'afternoon'],
            ['specialty' => 'Psicología', 'name' => 'Lic. Paola Núñez [DEMO]', 'email' => 'demo.psicologia.03@sirim.test', 'license' => 'DEMO-0024', 'pattern' => 'rotating'],

            ['specialty' => 'Traumatología', 'name' => 'Dr. Diego Ramírez [DEMO]', 'email' => 'demo.traumatologia.01@sirim.test', 'license' => 'DEMO-0025', 'pattern' => 'morning'],
            ['specialty' => 'Traumatología', 'name' => 'Dr. Sergio Chávez [DEMO]', 'email' => 'demo.traumatologia.02@sirim.test', 'license' => 'DEMO-0026', 'pattern' => 'afternoon'],
            ['specialty' => 'Traumatología', 'name' => 'Dra. Andrea León [DEMO]', 'email' => 'demo.traumatologia.03@sirim.test', 'license' => 'DEMO-0027', 'pattern' => 'rotating'],
        ];

        $createdProfessionals = 0;
        $createdSchedules = 0;
        $demoPassword = 'SirimDemo2026!';

        foreach ($professionalsToCreate as $data) {
            $user = User::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'password' => Hash::make($demoPassword),
                    'role' => 'doctor',
                    'email_verified_at' => now(),
                ]
            );

            if ($user->role !== 'doctor') {
                throw new RuntimeException(
                    "El correo {$data['email']} ya existe con un rol distinto de doctor. Se detuvo la carga para evitar modificar esa cuenta."
                );
            }

            $specialty = $specialties[$data['specialty']];

            $professional = Professional::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'specialty_id' => $specialty->id,
                    'health_facility_id' => $hospital->id,
                    'license_number' => $data['license'],
                    'is_active' => true,
                ]
            );

            if ((int) $professional->specialty_id !== (int) $specialty->id) {
                throw new RuntimeException(
                    "El profesional asociado a {$data['email']} ya existe en otra especialidad. No se modificó su registro."
                );
            }

            if (!$professional->is_active) {
                throw new RuntimeException(
                    "El profesional {$data['email']} existe, pero está inactivo. Actívalo o revisa el registro manualmente antes de continuar."
                );
            }

            if ($professional->wasRecentlyCreated) {
                $createdProfessionals++;
            }

            $scheduleRows = $this->scheduleForPattern($data['pattern']);

            foreach ($scheduleRows as $row) {
                $schedule = ProfessionalSchedule::firstOrCreate(
                    [
                        'professional_id' => $professional->id,
                        'day_of_week' => $row['day'],
                        'start_time' => $row['start'],
                        'end_time' => $row['end'],
                    ],
                    [
                        'slot_duration' => 20,
                        'is_active' => true,
                    ]
                );

                if ($schedule->wasRecentlyCreated) {
                    $createdSchedules++;
                }
            }
        }

        // Verifica que haya al menos tres profesionales activos en cada especialidad.
        foreach ($specialties as $name => $specialty) {
            $count = Professional::where('specialty_id', $specialty->id)
                ->where('is_active', true)
                ->count();

            if ($count < 3) {
                throw new RuntimeException(
                    "La especialidad {$name} quedó con {$count} profesionales activos; se esperaban al menos tres."
                );
            }
        }

        $this->command?->info('Carga de profesionales ficticios completada.');
        $this->command?->info('Nuevos profesionales creados: ' . $createdProfessionals);
        $this->command?->info('Nuevos horarios creados: ' . $createdSchedules);
        $this->command?->info('Total esperado: 27 profesionales activos como mínimo (3 por especialidad).');
        $this->command?->warn('Todos los nombres y turnos añadidos son datos ficticios de demostración.');
        $this->command?->info('Contraseña común de las cuentas DEMO nuevas: ' . $demoPassword);
        });
    }

    /**
     * Devuelve turnos de consulta externa de lunes a viernes.
     * 1=lunes ... 5=viernes (ISO-8601).
     * Estos horarios son de prueba, no guardias oficiales ni turnos reales del hospital.
     */
    private function scheduleForPattern(string $pattern): array
    {
        $rows = [];

        $days = match ($pattern) {
            'morning' => [
                1 => ['08:00:00', '12:00:00'],
                2 => ['08:00:00', '12:00:00'],
                3 => ['08:00:00', '12:00:00'],
                4 => ['08:00:00', '12:00:00'],
                5 => ['08:00:00', '12:00:00'],
            ],
            'afternoon' => [
                1 => ['14:00:00', '18:00:00'],
                2 => ['14:00:00', '18:00:00'],
                3 => ['14:00:00', '18:00:00'],
                4 => ['14:00:00', '18:00:00'],
                5 => ['14:00:00', '18:00:00'],
            ],
            'rotating' => [
                1 => ['08:00:00', '12:00:00'],
                2 => ['14:00:00', '18:00:00'],
                3 => ['08:00:00', '12:00:00'],
                4 => ['14:00:00', '18:00:00'],
                5 => ['08:00:00', '12:00:00'],
            ],
            default => throw new RuntimeException("Patrón de horario desconocido: {$pattern}"),
        };

        foreach ($days as $day => [$start, $end]) {
            $rows[] = [
                'day' => $day,
                'start' => $start,
                'end' => $end,
            ];
        }

        return $rows;
    }
}

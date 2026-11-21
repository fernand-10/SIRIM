<?php

namespace Database\Seeders;

use App\Models\HealthFacility;
use App\Models\Professional;
use App\Models\ProfessionalSchedule;
use App\Models\Specialty;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Establecimientos
        $hospital = HealthFacility::firstOrCreate(
            ['name' => 'Hospital Regional Hermilio Valdizán'],
            [
                'facility_type' => 'Hospital',
                'address' => 'Huánuco, Perú',
                'is_active' => true
            ]
        );

        $facility2 = HealthFacility::firstOrCreate(
            ['name' => 'Centro de Salud de Demostración'],
            [
                'facility_type' => 'Centro de salud',
                'address' => 'Huánuco, Perú',
                'is_active' => true
            ]
        );

        // Especialidades
        $specialties = [];

        foreach ([
            'Cardiología',
            'Medicina Interna',
            'Pediatría',
            'Cirugía General',
            'Ginecología',
            'Traumatología',
            'Neurología',
            'Dermatología',
            'Psicología'
        ] as $name) {
            $specialties[$name] = Specialty::firstOrCreate(
                ['name' => $name],
                ['is_active' => true]
            );
        }

        // Usuarios iniciales
        $users = [
            [
                'name' => 'Administrador SIRIM',
                'email' => 'admin@sirim.test',
                'role' => 'admin'
            ],
            [
                'name' => 'Personal de Admisión',
                'email' => 'admision@sirim.test',
                'role' => 'admission'
            ],
            [
                'name' => 'Dr. Médico Demostración',
                'email' => 'medico@sirim.test',
                'role' => 'doctor'
            ],
            [
                'name' => 'Coordinación Médica',
                'email' => 'coordinacion@sirim.test',
                'role' => 'coordinator'
            ],
        ];

        foreach ($users as $u) {
            $user = User::firstOrCreate(
                ['email' => $u['email']],
                [
                    'name' => $u['name'],
                    'role' => $u['role'],
                    'password' => Hash::make('SirimDemo2026!'),
                    'email_verified_at' => now()
                ]
            );

            if ($user->role === 'doctor') {
                $professional = Professional::firstOrCreate(
                    ['user_id' => $user->id],
                    [
                        'specialty_id' => $specialties['Cardiología']->id,
                        'health_facility_id' => $hospital->id,
                        'license_number' => 'DEMO-0001',
                        'is_active' => true
                    ]
                );

                foreach ([1, 2, 3, 4, 5] as $day) {
                    ProfessionalSchedule::firstOrCreate(
                        [
                            'professional_id' => $professional->id,
                            'day_of_week' => $day,
                            'start_time' => '08:00:00',
                            'end_time' => '14:00:00'
                        ],
                        [
                            'slot_duration' => 20,
                            'is_active' => true
                        ]
                    );
                }
            }
        }

        // Cargar los profesionales y horarios ficticios adicionales.
        $this->call(DemoDoctorsSeeder::class);
    }
}

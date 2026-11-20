# CAMBIOS_Y_RUTAS — SIRIM

Este documento acompaña al proyecto completo y resume exactamente qué reemplazar y qué archivos son nuevos.

## Archivos reemplazados

**Modelos**
- `app/Models/User.php`
- `app/Models/Professional.php`
- `app/Models/Interconsultation.php`

**Controladores**
- `app/Http/Controllers/AdminController.php`
- `app/Http/Controllers/AppointmentController.php`
- `app/Http/Controllers/DashboardController.php`
- `app/Http/Controllers/InterconsultationController.php`
- `app/Http/Controllers/MedicalAttentionController.php`
- `app/Http/Controllers/PatientController.php`
- `app/Http/Controllers/ReferralController.php`
- `app/Http/Controllers/ReportController.php`

**Políticas**
- `app/Policies/AppointmentPolicy.php`
- `app/Policies/InterconsultationPolicy.php`
- `app/Policies/MedicalAttentionPolicy.php`
- `app/Policies/ReferralPolicy.php`

**Configuración y rutas**
- `routes/web.php`
- `bootstrap/app.php`
- `config/app.php`
- `config/database.php`
- `config/services.php`
- `.env.example` (plantilla; no reemplaces tu `.env` real)

**Vistas**
- `resources/views/appointments/form.blade.php`
- `resources/views/attentions/form.blade.php`
- `resources/views/patients/form.blade.php`
- `resources/views/dashboard.blade.php`
- `resources/views/interconsultations/index.blade.php`
- `resources/views/interconsultations/show.blade.php`
- `resources/views/referrals/show.blade.php`
- `resources/views/reports/index.blade.php`
- `resources/views/layouts/app.blade.php`

**Datos iniciales**
- `database/seeders/DatabaseSeeder.php`

## Archivos nuevos

- `app/Models/ProfessionalSchedule.php`
- `app/Models/ProfessionalBlock.php`
- `app/Http/Controllers/ProfessionalScheduleController.php`
- `app/Http/Controllers/ReniecController.php`
- `app/Services/ReniecService.php`
- `resources/views/schedules/index.blade.php`
- `database/migrations/2026_11_05_000001_create_professional_schedules_and_blocks.php`

## Pasos seguros para aplicar a una instalación existente

1. Cerrar SIRIM y realizar una copia de la carpeta y un respaldo SQL de la base de datos.
2. Copiar los archivos del ZIP sobre la carpeta del proyecto, permitiendo reemplazar los archivos listados arriba. No copiar un `.env` del paquete: no se distribuye. Conserva el `.env` actual.
3. Revisar el `.env` local: `DB_CONNECTION=mysql`, host, puerto, base, usuario y clave. Ajustar `APP_TIMEZONE=America/Lima`.
4. Ejecutar `composer install` si se incorporó el proyecto completo en una instalación donde no existe `vendor`.
5. Ejecutar `php artisan optimize:clear`.
6. Ejecutar `php artisan migrate` (no `migrate:fresh`).
7. Ejecutar `php artisan route:list` y `php artisan test`.
8. Entrar en SIRIM, crear/validar los horarios semanales de médicos y probar un flujo con datos ficticios.

## Importante sobre el DNI

El formulario y el endpoint interno están preparados. La consulta real requiere que el establecimiento tenga habilitación oficial y que se adapte `app/Services/ReniecService.php` a las especificaciones y credenciales que RENIEC entregue. Las variables `RENIEC_URL`, `RENIEC_TOKEN`, `RENIEC_METHOD`, `RENIEC_DNI_FIELD` y `RENIEC_TIMEOUT` no sustituyen el convenio ni una autenticación por certificado si el servicio lo requiere.

# SIRIM — Sistema interno de referencias e interconsultas médicas

Esta entrega es el proyecto completo basado en el archivo `SIRIM.rar` compartido. Está pensado para el personal del hospital (administración, admisión, médicos y coordinación). **No incluye portal ni cuentas para pacientes.**

## Tecnologías

- Laravel 12 / PHP 8.2 o superior.
- MySQL 8 o MariaDB compatible.
- Blade, CSS y JavaScript.
- Laragon y VS Code en Windows.
- No se necesita Node.js ni ejecutar NPM para la interfaz incluida.

## Qué se corrigió e incorporó

- Relaciones Eloquent alineadas con las columnas reales de las migraciones.
- Controladores de referencias, interconsultas, citas, atenciones, pacientes, reportes y dashboard.
- Formularios completos de programación de citas y registro de atenciones.
- Políticas de acceso para administrador, admisión, médico y coordinación.
- Agenda semanal por profesional, duración de cita y bloqueos de ausencia.
- Prevención de cruces de horario del profesional y del paciente.
- Historial de cambios de estado en referencias, interconsultas y citas.
- Códigos de referencia e interconsulta generados automáticamente.
- Consulta de DNI preparada mediante un adaptador REST configurable.
- Zona horaria predeterminada `America/Lima`.

## Instalación en Laragon

1. **Haz una copia de seguridad de tu proyecto y base de datos actuales.** No reemplaces la carpeta que está funcionando sin respaldo.
2. Descomprime el ZIP en una carpeta temporal para verificar su contenido.
3. Copia el contenido de la carpeta `SIRIM` a `C:\laragon\www\SIRIM`. Conserva el `.env` de tu instalación si ya tiene la clave APP_KEY y los datos correctos de MySQL. El ZIP no incluye `.env` por seguridad.
4. Abre PowerShell en `C:\laragon\www\SIRIM`.
5. Comprueba que MySQL está iniciado en Laragon y que existe la base `sirim`. Si no existe, crea una vez: `CREATE DATABASE sirim CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;`.
6. Revisa estas líneas de tu `.env` (usa la contraseña real de tu MySQL):

```dotenv
APP_NAME=SIRIM
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000
APP_TIMEZONE=America/Lima

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=sirim
DB_USERNAME=root
DB_PASSWORD=
```

7. Ejecuta estos comandos, uno por uno:

```powershell
composer install
php artisan key:generate
php artisan optimize:clear
php artisan migrate
php artisan route:list
php artisan serve
```

Si el `.env` ya tiene una `APP_KEY` válida, puedes omitir `php artisan key:generate`. **No ejecutes `php artisan migrate:fresh` ni `db:wipe`** porque borrarían las tablas y datos. El comando `migrate` solo aplica las migraciones pendientes, incluida la nueva migración de horarios.

Abre `http://127.0.0.1:8000`.

## Cuentas de demostración

El seeder crea estas cuentas locales:

| Rol | Correo | Clave inicial |
|---|---|---|
| Administrador | `admin@sirim.test` | `SirimDemo2026!` |
| Admisión | `admision@sirim.test` | `SirimDemo2026!` |
| Médico | `medico@sirim.test` | `SirimDemo2026!` |
| Coordinación | `coordinacion@sirim.test` | `SirimDemo2026!` |

**Solo si es una instalación nueva sin usuarios**, puedes cargar los datos de demostración con `php artisan db:seed`. No ejecutes el seeder en producción ni en una base con datos reales sin revisar primero sus efectos. Cambia las claves de demostración antes de compartir el entorno.

## Uso de horarios y citas

1. Ingresa como administrador o coordinación.
2. Abre **Horarios médicos** y configura cada turno semanal del profesional.
3. La duración elegida en la cita debe coincidir con la duración configurada en ese turno.
4. Registra bloqueos de vacaciones, permisos o ausencias. No se permite bloquear un intervalo con citas activas.
5. Admisión/coordinación agenda la cita. El servidor valida el turno, bloqueos y cruces.
6. El médico registra la atención. Al guardar, la cita se marca atendida y la interconsulta vinculada se responde automáticamente.

## Configuración de la integración RENIEC

El proyecto incluye `app/Services/ReniecService.php` y `app/Http/Controllers/ReniecController.php`. El formulario de pacientes tiene el botón **Consultar DNI**. Sin embargo, la consulta real no funciona hasta que el hospital obtenga del proveedor/RENIEC el contrato, la documentación técnica y las credenciales.

Variables disponibles en `.env`:

```dotenv
RENIEC_URL=
RENIEC_TOKEN=
RENIEC_METHOD=POST
RENIEC_DNI_FIELD=dni
RENIEC_TIMEOUT=15
```

El adaptador de este paquete acepta un servicio **REST que responda JSON** y normaliza claves frecuentes de nombres, apellidos, fecha de nacimiento y sexo. Si el Web Service concedido al hospital usa SOAP, certificado cliente, otro mecanismo de autenticación o un formato distinto, se debe adaptar `ReniecService.php` exactamente al manual entregado por RENIEC. No se ha inventado ni activado un endpoint oficial. No uses APIs de terceros no autorizadas para consultar datos personales.

## Archivos clave modificados o añadidos

### Modelos y relaciones

- `app/Models/User.php` — relación usuario/profesional y roles.
- `app/Models/Professional.php` — relaciones de turnos y bloqueos.
- `app/Models/Interconsultation.php` — relaciones alineadas con `requested_by`, `from_specialty_id`, `to_specialty_id`, etc.
- `app/Models/ProfessionalSchedule.php` — nuevo modelo de turnos.
- `app/Models/ProfessionalBlock.php` — nuevo modelo de ausencias/bloqueos.

### Controladores

- `app/Http/Controllers/InterconsultationController.php`
- `app/Http/Controllers/MedicalAttentionController.php`
- `app/Http/Controllers/AppointmentController.php`
- `app/Http/Controllers/ReferralController.php`
- `app/Http/Controllers/PatientController.php`
- `app/Http/Controllers/AdminController.php`
- `app/Http/Controllers/DashboardController.php`
- `app/Http/Controllers/ReportController.php`
- `app/Http/Controllers/ProfessionalScheduleController.php` — nuevo.
- `app/Http/Controllers/ReniecController.php` — nuevo.
- `app/Services/ReniecService.php` — nuevo.

### Permisos y vistas

- `app/Policies/AppointmentPolicy.php`
- `app/Policies/InterconsultationPolicy.php`
- `app/Policies/MedicalAttentionPolicy.php`
- `app/Policies/ReferralPolicy.php`
- `resources/views/appointments/form.blade.php`
- `resources/views/attentions/form.blade.php`
- `resources/views/patients/form.blade.php`
- `resources/views/schedules/index.blade.php` — nueva.
- Vistas de dashboard, referencias e interconsultas ajustadas.

### Configuración y base de datos

- `routes/web.php` — rutas internas, permisos, endpoints auxiliares, RENIEC y agenda.
- `bootstrap/app.php` — middleware `role`.
- `config/app.php` — zona horaria.
- `config/database.php` — MySQL predeterminado.
- `config/services.php` y `.env.example` — variables RENIEC.
- `database/migrations/2026_11_05_000001_create_professional_schedules_and_blocks.php` — nueva migración de horarios y bloqueos.
- `database/seeders/DatabaseSeeder.php` — cuentas/datos ficticios y horario de demostración.

## Verificación

Ejecuta:

```powershell
php artisan optimize:clear
php artisan migrate:status
php artisan route:list
php artisan test
```

Si `php artisan test` informa que falta `ext-dom`, `mbstring`, `xml` u otra extensión, activa las extensiones requeridas en el `php.ini` de Laragon y reinicia la terminal. Para verificar las operaciones reales necesitas MySQL activo, migraciones aplicadas y datos de prueba.

## Advertencia de uso clínico

Esta entrega es una base de desarrollo y pruebas. No es software clínico certificado ni debe ponerse en producción con datos reales sin evaluación de seguridad, privacidad, normativa sanitaria, control de acceso por establecimiento y relación asistencial, auditoría de consulta de historia clínica, respaldos, recuperación ante desastres, HTTPS y validación formal del hospital. Las notas clínicas deben ser registradas y corregidas conforme al protocolo del establecimiento.

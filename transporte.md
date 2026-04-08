# Módulo: Transporte — Diseño Funcional

## Propósito

El módulo de Transporte gestiona los dos activos operativos centrales del TMS: la **flota de vehículos** y los **operadores (conductores)**. Su responsabilidad es mantener el estado real de cada activo, controlar asignaciones entre vehículos y operadores, y proveer alertas sobre documentación próxima a vencer.

Este diseño se integra con el módulo de Administración ya existente (empresas, usuarios, clientes) y sienta las bases para los módulos de Planificación y Seguimiento.

---

## Entidades

### 1. Vehículo (`vehicles`)

Representa cada unidad de la flota.

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | integer | PK autoincremental |
| `company_id` | FK → companies | Empresa propietaria |
| `plate` | string(20), unique | Placa/patente |
| `type` | enum | Tipo de vehículo (ver tabla de tipos) |
| `brand` | string(100) | Marca (Volvo, Mercedes, etc.) |
| `model` | string(100) | Modelo |
| `year` | smallint | Año de fabricación |
| `color` | string(50) | Color |
| `payload_kg` | integer | Capacidad de carga en kilogramos |
| `volume_m3` | decimal(8,2) | Capacidad volumétrica en m³ |
| `fuel_type` | enum | Tipo de combustible (diesel, gasolina, eléctrico, gas) |
| `status` | enum | Estado operativo (ver estados) |
| `current_mileage` | integer | Kilometraje actual |
| `insurance_expiry` | date | Vencimiento del seguro |
| `technical_review_expiry` | date | Vencimiento de revisión técnica |
| `circulation_permit_expiry`| date | Vencimiento del permiso de circulación |
| `notes` | text, nullable | Observaciones internas |
| `is_active` | boolean | Habilitado en el sistema |
| `timestamps` | — | created_at / updated_at |

**Tipos de vehículo (`type`):**
`truck` (camión), `van` (furgoneta), `pickup` (camioneta), `semi` (semirremolque), `refrigerated` (frigorífico), `tanker` (cisterna), `minibus` (minibús)

**Estados del vehículo (`status`):**

```
disponible ──→ en_ruta
disponible ──→ en_mantenimiento
en_ruta    ──→ disponible
en_mantenimiento ──→ disponible
cualquier estado ──→ inactivo  (solo admin)
```

| Estado | Valor | Descripción |
|--------|-------|-------------|
| Disponible | `available` | Libre para ser asignado |
| En ruta | `on_route` | Actualmente en servicio con operador asignado |
| En mantenimiento | `maintenance` | No disponible temporalmente |
| Inactivo | `inactive` | Dado de baja operativa |

---

### 2. Operador (`operators`)

Representa a los conductores/operadores de la flota.

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | integer | PK autoincremental |
| `company_id` | FK → companies | Empresa a la que pertenece |
| `name` | string(255) | Nombre completo |
| `document_number` | string(30), unique | DNI / RUT / cédula |
| `email` | string, nullable | Correo electrónico |
| `phone` | string(30), nullable | Teléfono principal |
| `address` | string, nullable | Domicilio |
| `license_number` | string(50), unique | Número de licencia de conducir |
| `license_type` | enum | Categoría de licencia (A1, A2, B, C, D, E) |
| `license_expiry` | date | Fecha de vencimiento de la licencia |
| `emergency_contact` | string(255), nullable | Nombre del contacto de emergencia |
| `emergency_phone` | string(30), nullable | Teléfono de emergencia |
| `status` | enum | Estado operativo (ver estados) |
| `notes` | text, nullable | Observaciones internas |
| `is_active` | boolean | Habilitado en el sistema |
| `timestamps` | — | created_at / updated_at |

**Estados del operador (`status`):**

| Estado | Valor | Descripción |
|--------|-------|-------------|
| Disponible | `available` | Libre para ser asignado |
| En servicio | `on_duty` | Actualmente asignado a un vehículo en ruta |
| Fuera de turno | `off_duty` | Descansando o fuera de horario |
| Inactivo | `inactive` | No disponible en el sistema |

---

### 3. Asignación Vehículo–Operador (`vehicle_assignments`)

Registro histórico de cada asignación. Una asignación activa (`released_at = null`) indica el estado actual.

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | integer | PK autoincremental |
| `vehicle_id` | FK → vehicles | Vehículo asignado |
| `operator_id` | FK → operators | Operador asignado |
| `assigned_by` | FK → users | Usuario que realizó la asignación |
| `assigned_at` | timestamp | Momento de la asignación |
| `released_at` | timestamp, nullable | Momento de liberación (`null` = activa) |
| `notes` | text, nullable | Motivo o comentario de la asignación |
| `timestamps` | — | created_at / updated_at |

**Restricciones de negocio:**
- Un vehículo solo puede tener **una asignación activa** a la vez
- Un operador solo puede tener **una asignación activa** a la vez
- No se puede asignar un vehículo con `status != available`
- No se puede asignar un operador con `status = inactive` o `is_active = false`
- Al asignar: `vehicle.status → on_route`, `operator.status → on_duty`
- Al liberar: `vehicle.status → available`, `operator.status → available`

---

## Migraciones

### `create_vehicles_table`
```php
Schema::create('vehicles', function (Blueprint $table) {
    $table->id();
    $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
    $table->string('plate', 20)->unique();
    $table->string('type', 30);
    $table->string('brand', 100);
    $table->string('model', 100);
    $table->smallInteger('year');
    $table->string('color', 50)->nullable();
    $table->integer('payload_kg')->nullable();
    $table->decimal('volume_m3', 8, 2)->nullable();
    $table->string('fuel_type', 20)->default('diesel');
    $table->string('status', 20)->default('available');
    $table->integer('current_mileage')->default(0);
    $table->date('insurance_expiry')->nullable();
    $table->date('technical_review_expiry')->nullable();
    $table->date('circulation_permit_expiry')->nullable();
    $table->text('notes')->nullable();
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});
```

### `create_operators_table`
```php
Schema::create('operators', function (Blueprint $table) {
    $table->id();
    $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
    $table->string('name');
    $table->string('document_number', 30)->unique();
    $table->string('email')->nullable();
    $table->string('phone', 30)->nullable();
    $table->string('address')->nullable();
    $table->string('license_number', 50)->unique();
    $table->string('license_type', 10);
    $table->date('license_expiry');
    $table->string('emergency_contact')->nullable();
    $table->string('emergency_phone', 30)->nullable();
    $table->string('status', 20)->default('available');
    $table->text('notes')->nullable();
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});
```

### `create_vehicle_assignments_table`
```php
Schema::create('vehicle_assignments', function (Blueprint $table) {
    $table->id();
    $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
    $table->foreignId('operator_id')->constrained()->cascadeOnDelete();
    $table->foreignId('assigned_by')->constrained('users')->restrictOnDelete();
    $table->timestamp('assigned_at');
    $table->timestamp('released_at')->nullable();
    $table->text('notes')->nullable();
    $table->timestamps();
});
```

---

## API Endpoints

Prefijo base: `/api/transport`  
Requieren: `auth.session` + `profile:admin`

### Vehículos

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| `GET` | `/api/transport/vehicles` | Listar flota (filtros: status, type, company_id) |
| `POST` | `/api/transport/vehicles` | Registrar vehículo |
| `GET` | `/api/transport/vehicles/{id}` | Detalle con asignación activa |
| `PUT` | `/api/transport/vehicles/{id}` | Actualizar datos |
| `DELETE` | `/api/transport/vehicles/{id}` | Eliminar (solo si no tiene asignación activa) |
| `PATCH` | `/api/transport/vehicles/{id}/status` | Cambiar estado operativo |
| `GET` | `/api/transport/vehicles/expiring` | Vehículos con documentos por vencer (≤ 30 días) |

### Operadores

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| `GET` | `/api/transport/operators` | Listar operadores (filtros: status, company_id) |
| `POST` | `/api/transport/operators` | Registrar operador |
| `GET` | `/api/transport/operators/{id}` | Detalle con asignación activa |
| `PUT` | `/api/transport/operators/{id}` | Actualizar datos |
| `DELETE` | `/api/transport/operators/{id}` | Eliminar (solo si no tiene asignación activa) |
| `PATCH` | `/api/transport/operators/{id}/status` | Cambiar estado operativo |
| `GET` | `/api/transport/operators/expiring-licenses` | Operadores con licencia por vencer (≤ 30 días) |

### Asignaciones

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| `POST` | `/api/transport/assignments` | Crear asignación vehículo–operador |
| `PATCH` | `/api/transport/assignments/{id}/release` | Liberar asignación activa |
| `GET` | `/api/transport/assignments` | Historial completo (paginado) |
| `GET` | `/api/transport/assignments/active` | Solo asignaciones activas en este momento |

---

## Contratos de API (ejemplos)

### `POST /api/transport/assignments`
**Request:**
```json
{
  "vehicle_id": 5,
  "operator_id": 3,
  "notes": "Ruta norte — carga refrigerada"
}
```
**Response 201:**
```json
{
  "id": 12,
  "vehicle": { "id": 5, "plate": "ABC-123", "status": "on_route" },
  "operator": { "id": 3, "name": "Juan Pérez", "status": "on_duty" },
  "assigned_by": { "id": 1, "name": "Administrador" },
  "assigned_at": "2026-04-08T14:00:00Z",
  "released_at": null
}
```

### `GET /api/transport/vehicles/expiring`
**Response 200:**
```json
{
  "data": [
    {
      "id": 7,
      "plate": "XYZ-999",
      "brand": "Volvo",
      "model": "FH16",
      "alerts": {
        "insurance": { "expiry": "2026-04-20", "days_remaining": 12 },
        "technical_review": { "expiry": "2026-05-01", "days_remaining": 23 }
      }
    }
  ]
}
```

---

## Reglas de validación

### Vehículo
- `plate`: requerido, único, formato alfanumérico con guiones
- `type`: requerido, debe pertenecer al enum definido
- `year`: requerido, entre 1990 y año actual + 1
- `status`: solo puede ser cambiado a través de `PATCH /status`, con validación de transición permitida
- No se puede eliminar un vehículo con asignación activa

### Operador
- `document_number`: requerido, único por empresa
- `license_number`: requerido, único global
- `license_expiry`: requerido, debe ser fecha futura al momento del registro
- `license_type`: debe ser una categoría válida (A1, A2, B, C, D, E)
- No se puede eliminar un operador con asignación activa

### Asignación
- `vehicle_id`: debe existir y tener `status = available`
- `operator_id`: debe existir, tener `status = available` y `is_active = true`
- No puede existir otra asignación activa para ese vehículo o ese operador

---

## Pantallas del frontend (Vue 3)

### `/transport/vehicles` — Flota de vehículos
- Tabla con columnas: Placa, Tipo, Marca/Modelo, Estado (badge de color), Operador asignado, Acciones
- Filtros superiores: por estado, por tipo, por empresa
- Botón "+ Registrar vehículo" (modal de formulario)
- Fila con alerta visual si algún documento vence en ≤ 30 días (ícono de advertencia)
- Acciones por fila: Ver detalle · Editar · Cambiar estado · Eliminar

### `/transport/vehicles/:id` — Detalle de vehículo
- Tarjeta de datos generales
- Sección de documentación con estado visual (verde/amarillo/rojo según vencimiento)
- Asignación activa actual con nombre del operador y hora de inicio
- Historial de asignaciones previas (tabla paginada)

### `/transport/operators` — Operadores
- Tabla: Nombre, Documento, Licencia (tipo + vencimiento), Estado, Vehículo asignado, Acciones
- Filtros: por estado, por empresa
- Botón "+ Registrar operador"
- Alerta visual si licencia vence en ≤ 30 días
- Acciones: Ver detalle · Editar · Cambiar estado · Eliminar

### `/transport/operators/:id` — Detalle de operador
- Tarjeta con datos personales y de contacto
- Datos de licencia con estado visual según vencimiento
- Asignación activa actual
- Historial de asignaciones

### `/transport/assignments` — Panel de asignaciones
- Vista principal en dos columnas: **Vehículos disponibles** | **Operadores disponibles**
- Botón "Asignar" que abre modal donde se seleccionan vehículo y operador de listas filtradas
- Tabla inferior con asignaciones activas y botón "Liberar" por fila
- Pestaña "Historial" con tabla paginada de todas las asignaciones pasadas

---

## Stores Pinia (frontend)

### `useVehiclesStore`
```
state:  vehicles[], meta (pagination), loading, filters
actions: fetchVehicles(filters), createVehicle, updateVehicle,
         deleteVehicle, changeStatus, fetchExpiring
```

### `useOperatorsStore`
```
state:  operators[], meta (pagination), loading, filters
actions: fetchOperators(filters), createOperator, updateOperator,
         deleteOperator, changeStatus, fetchExpiringLicenses
```

### `useAssignmentsStore`
```
state:  activeAssignments[], history[], loading
actions: fetchActive, fetchHistory, createAssignment, releaseAssignment
```

---

## Alertas y notificaciones

El sistema debe identificar proactivamente situaciones de riesgo:

| Condición | Umbral | Nivel |
|-----------|--------|-------|
| Licencia de conductor por vencer | ≤ 30 días | ⚠️ Advertencia |
| Licencia de conductor vencida | Fecha pasada | 🔴 Crítico |
| Seguro del vehículo por vencer | ≤ 30 días | ⚠️ Advertencia |
| Revisión técnica por vencer | ≤ 30 días | ⚠️ Advertencia |
| Permiso de circulación por vencer | ≤ 30 días | ⚠️ Advertencia |
| Vehículo en mantenimiento > 7 días | 7 días | ℹ️ Informativo |

Implementación: endpoint `GET /api/transport/alerts` que devuelve todas las alertas activas agrupadas por tipo. El dashboard principal mostrará un contador de alertas en el sidebar.

---

## Relación con otros módulos

```
Administración          Transporte              Planificación (futuro)
─────────────────       ──────────────────      ──────────────────────
companies  ────────────→ vehicles.company_id
companies  ────────────→ operators.company_id               ↓
users      ────────────→ assignments.assigned_by   viajes.vehicle_id
                         vehicles ─────────────────────→ viajes.operator_id
                         operators ────────────────────→
```

---

## Convenciones de implementación

- Los controladores se ubican en `App\Http\Controllers\Api\Transport\`
- Los modelos en `App\Models\` (sin subdirectorio, consistente con el resto)
- Los Form Requests en `App\Http\Requests\Vehicle\`, `App\Http\Requests\Operator\`, `App\Http\Requests\Assignment\`
- Los Resources en `App\Http\Resources\` con prefijo: `VehicleResource`, `OperatorResource`, `AssignmentResource`
- Las rutas se registran en `routes/api.php` bajo el grupo con prefijo `transport/`
- Los archivos Vue se ubican en `frontend/src/pages/transport/`

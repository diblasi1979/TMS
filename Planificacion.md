# Módulo: Planificación — Diseño Funcional

## Propósito

El módulo de Planificación gestiona tres dimensiones del ciclo operativo a mediano y largo plazo: los **viajes de transporte intercarga**, la **programación de mantenimiento preventivo de la flota** y los **turnos de operadores**. Su responsabilidad es anticipar la demanda de recursos antes de que los módulos de Transporte y Distribución necesiten activos disponibles, evitar conflictos de agenda y garantizar que vehículos y operadores lleguen al momento operativo en condiciones óptimas.

Este módulo provee a los módulos de Transporte y Distribución con el contexto de disponibilidad futura. Consume los activos del módulo de Transporte (vehículos, operadores) y del módulo de Administración (clientes, empresas).

---

## Alcance funcional

| Función | Descripción |
|---------|-------------|
| **Viajes** | Planificación de traslados de carga entre origen y destino con fecha, distancia y recursos asignados |
| **Mantenimiento** | Programación de mantenimientos preventivos y correctivos por vehículo |
| **Turnos** | Gestión de turnos de trabajo y descanso de operadores |
| **Conflictos** | Detección de solapamiento entre viajes, mantenimientos y turnos en el mismo período |

---

## Entidades

### 1. Viaje Planificado (`trip_plans`)

Representa un traslado de carga contratado o proyectado entre un punto de origen y un destino, con fecha de salida y llegada estimadas.

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | integer | PK autoincremental |
| `company_id` | FK → companies | Empresa que gestiona el viaje |
| `client_id` | FK → clients, nullable | Cliente que contrata el servicio |
| `trip_number` | string(50), unique | Código interno del viaje |
| `origin` | string(500) | Lugar de origen |
| `origin_lat` | decimal(10,7), nullable | Latitud del origen |
| `origin_lng` | decimal(10,7), nullable | Longitud del origen |
| `destination` | string(500) | Lugar de destino |
| `destination_lat` | decimal(10,7), nullable | Latitud del destino |
| `destination_lng` | decimal(10,7), nullable | Longitud del destino |
| `scheduled_departure` | datetime | Fecha y hora de salida planificada |
| `scheduled_arrival` | datetime | Fecha y hora de llegada estimada |
| `actual_departure` | datetime, nullable | Salida real registrada |
| `actual_arrival` | datetime, nullable | Llegada real registrada |
| `vehicle_id` | FK → vehicles, nullable | Vehículo asignado |
| `operator_id` | FK → operators, nullable | Operador asignado |
| `cargo_type` | string(100), nullable | Tipo de carga (general, refrigerada, peligrosa, etc.) |
| `cargo_description` | text, nullable | Descripción de la carga |
| `weight_kg` | decimal(10,2), nullable | Peso estimado de la carga (kg) |
| `volume_m3` | decimal(8,2), nullable | Volumen estimado (m³) |
| `distance_km` | decimal(8,2), nullable | Distancia estimada del trayecto (km) |
| `status` | enum | Estado del viaje (ver estados) |
| `priority` | enum | Prioridad del viaje (`low`, `normal`, `high`, `urgent`) |
| `notes` | text, nullable | Observaciones internas |
| `timestamps` | — | created_at / updated_at |

**Tipos de carga (`cargo_type` sugeridos):**
`general`, `refrigerated`, `hazardous`, `fragile`, `bulk`, `livestock`, `machinery`

**Prioridades (`priority`):**

| Prioridad | Valor | Descripción |
|-----------|-------|-------------|
| Baja | `low` | Sin urgencia, flexible en fechas |
| Normal | `normal` | Prioridad estándar |
| Alta | `high` | Requiere atención prioritaria en asignación |
| Urgente | `urgent` | Debe despacharse en el menor tiempo posible |

**Estados del viaje (`status`):**

| Estado | Valor | Descripción |
|--------|-------|-------------|
| Borrador | `draft` | Creado sin recursos asignados |
| Confirmado | `confirmed` | Vehículo y operador asignados, listo para ejecutar |
| En Camino | `in_progress` | Viaje iniciado |
| Completado | `completed` | Llegada confirmada en destino |
| Cancelado | `cancelled` | Cancelado antes del inicio |

**Transiciones permitidas:**

```
draft       ──→ confirmed   (al asignar vehículo + operador)
draft       ──→ cancelled
confirmed   ──→ draft       (al remover vehículo u operador)
confirmed   ──→ in_progress (al registrar salida real)
confirmed   ──→ cancelled
in_progress ──→ completed   (al registrar llegada real)
```

---

### 2. Mantenimiento Programado (`maintenance_schedules`)

Representa una intervención técnica planificada sobre un vehículo de la flota, ya sea preventiva (por kilometraje o fecha) o correctiva (por falla detectada).

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | integer | PK autoincremental |
| `company_id` | FK → companies | Empresa propietaria del vehículo |
| `vehicle_id` | FK → vehicles | Vehículo que recibirá el mantenimiento |
| `maintenance_type` | enum | Tipo de intervención (ver tipos) |
| `description` | text | Descripción del trabajo a realizar |
| `scheduled_date` | date | Fecha planificada para el ingreso |
| `estimated_duration_days` | smallint | Días estimados de indisponibilidad |
| `actual_start` | date, nullable | Fecha real de inicio |
| `actual_end` | date, nullable | Fecha real de finalización |
| `workshop` | string(255), nullable | Taller o lugar de mantenimiento |
| `estimated_cost` | decimal(12,2), nullable | Costo estimado |
| `actual_cost` | decimal(12,2), nullable | Costo real |
| `mileage_at_service` | integer, nullable | Kilometraje del vehículo al momento del servicio |
| `next_service_km` | integer, nullable | Kilómetros para el próximo servicio |
| `next_service_date` | date, nullable | Fecha del próximo servicio programado |
| `status` | enum | Estado del mantenimiento (ver estados) |
| `notes` | text, nullable | Observaciones del taller o del administrador |
| `timestamps` | — | created_at / updated_at |

**Tipos de mantenimiento (`maintenance_type`):**

| Tipo | Valor | Descripción |
|------|-------|-------------|
| Preventivo | `preventive` | Mantenimiento periódico por tiempo o kilometraje |
| Correctivo | `corrective` | Reparación por avería o daño detectado |
| Predictivo | `predictive` | Basado en diagnóstico técnico anticipado |
| Inspección | `inspection` | Revisión técnica sin intervención mayor |

**Estados del mantenimiento (`status`):**

| Estado | Valor | Descripción |
|--------|-------|-------------|
| Programado | `scheduled` | Registrado, pendiente de iniciar |
| En proceso | `in_progress` | Vehículo en el taller |
| Completado | `completed` | Mantenimiento finalizado |
| Cancelado | `cancelled` | Cancelado antes de iniciar |

**Transiciones permitidas:**

```
scheduled   ──→ in_progress  (al confirmar ingreso al taller)
scheduled   ──→ cancelled
in_progress ──→ completed    (al confirmar egreso del taller)
```

---

### 3. Turno de Operador (`operator_shifts`)

Representa un bloque de tiempo asignado a un operador, ya sea un turno de trabajo activo, un descanso obligatorio o un período de vacaciones/licencia.

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | integer | PK autoincremental |
| `company_id` | FK → companies | Empresa que gestiona el turno |
| `operator_id` | FK → operators | Operador al que pertenece el turno |
| `shift_type` | enum | Tipo de turno (ver tipos) |
| `start_datetime` | datetime | Inicio del turno |
| `end_datetime` | datetime | Fin del turno |
| `notes` | text, nullable | Observaciones del turno |
| `created_by` | FK → users | Usuario que registró el turno |
| `timestamps` | — | created_at / updated_at |

**Tipos de turno (`shift_type`):**

| Tipo | Valor | Descripción |
|------|-------|-------------|
| Trabajo | `work` | Turno activo de trabajo |
| Descanso | `rest` | Descanso obligatorio entre jornadas |
| Vacaciones | `vacation` | Período de vacaciones |
| Licencia | `leave` | Licencia médica u otro tipo de ausencia |
| Disponibilidad | `standby` | Disponible para ser llamado ante necesidad |

---

## Reglas de negocio

### Viajes
- El `trip_number` debe ser único por empresa.
- Un viaje no puede confirmar recursos que estén en mantenimiento programado durante el período del viaje.
- Un operador no puede ser asignado a un viaje durante un turno de tipo `rest`, `vacation` o `leave`.
- Un vehículo en mantenimiento planificado (`scheduled` o `in_progress`) no puede ser asignado a ningún viaje.
- Al iniciar el viaje (`in_progress`), el vehículo pasa a `on_route` y el operador a `on_duty` mediante el módulo de Transporte.
- Al completar el viaje, ambos regresan a `available`. Se registran `actual_departure` y `actual_arrival`.
- La verificación de capacidad (peso/volumen vs `payload_kg`/`volume_m3` del vehículo) se aplica al confirmar el viaje.

### Mantenimiento
- Al confirmar un mantenimiento (`in_progress`), el vehículo pasa a estado `maintenance` en el módulo de Transporte.
- Al completar, el vehículo regresa a `available`. Se actualiza `current_mileage` si se registra `mileage_at_service`.
- No se puede programar un mantenimiento para un vehículo que ya tiene uno `in_progress`.
- Si un vehículo tiene un mantenimiento `scheduled` solapado con un viaje confirmado, el sistema debe advertirlo.

### Turnos
- No puede existir solapamiento de turnos del mismo tipo para el mismo operador en el mismo período.
- Un operador con turno `rest`, `vacation` o `leave` activo no puede ser asignado a viajes ni a rutas de distribución.
- La duración de turnos de tipo `work` no debe superar 12 horas (advertencia, no bloqueo).

### Detección de conflictos
El sistema verifica automáticamente al asignar recursos:
1. ¿El vehículo tiene mantenimiento `scheduled` o `in_progress` que se solape con el período del viaje?
2. ¿El operador tiene un turno `rest`/`vacation`/`leave` en las fechas del viaje?
3. ¿El vehículo ya está asignado a otro viaje confirmado que se solape?
4. ¿El operador ya está asignado a otro viaje confirmado que se solape?

---

## Migraciones

### `create_trip_plans_table`
```php
Schema::create('trip_plans', function (Blueprint $table) {
    $table->id();
    $table->foreignId('company_id')->constrained()->cascadeOnDelete();
    $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
    $table->string('trip_number', 50)->unique();
    $table->string('origin', 500);
    $table->decimal('origin_lat', 10, 7)->nullable();
    $table->decimal('origin_lng', 10, 7)->nullable();
    $table->string('destination', 500);
    $table->decimal('destination_lat', 10, 7)->nullable();
    $table->decimal('destination_lng', 10, 7)->nullable();
    $table->dateTime('scheduled_departure');
    $table->dateTime('scheduled_arrival');
    $table->dateTime('actual_departure')->nullable();
    $table->dateTime('actual_arrival')->nullable();
    $table->foreignId('vehicle_id')->nullable()->constrained()->nullOnDelete();
    $table->foreignId('operator_id')->nullable()->constrained()->nullOnDelete();
    $table->string('cargo_type', 100)->nullable();
    $table->text('cargo_description')->nullable();
    $table->decimal('weight_kg', 10, 2)->nullable();
    $table->decimal('volume_m3', 8, 2)->nullable();
    $table->decimal('distance_km', 8, 2)->nullable();
    $table->string('status', 20)->default('draft');
    $table->string('priority', 20)->default('normal');
    $table->text('notes')->nullable();
    $table->timestamps();
});
```

### `create_maintenance_schedules_table`
```php
Schema::create('maintenance_schedules', function (Blueprint $table) {
    $table->id();
    $table->foreignId('company_id')->constrained()->cascadeOnDelete();
    $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
    $table->string('maintenance_type', 30);
    $table->text('description');
    $table->date('scheduled_date');
    $table->smallInteger('estimated_duration_days')->default(1);
    $table->date('actual_start')->nullable();
    $table->date('actual_end')->nullable();
    $table->string('workshop')->nullable();
    $table->decimal('estimated_cost', 12, 2)->nullable();
    $table->decimal('actual_cost', 12, 2)->nullable();
    $table->integer('mileage_at_service')->nullable();
    $table->integer('next_service_km')->nullable();
    $table->date('next_service_date')->nullable();
    $table->string('status', 20)->default('scheduled');
    $table->text('notes')->nullable();
    $table->timestamps();
});
```

### `create_operator_shifts_table`
```php
Schema::create('operator_shifts', function (Blueprint $table) {
    $table->id();
    $table->foreignId('company_id')->constrained()->cascadeOnDelete();
    $table->foreignId('operator_id')->constrained()->cascadeOnDelete();
    $table->string('shift_type', 20);
    $table->dateTime('start_datetime');
    $table->dateTime('end_datetime');
    $table->text('notes')->nullable();
    $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
    $table->timestamps();
});
```

---

## API Endpoints

Prefijo base: `/api/planning`
Requieren: `auth.session` + `profile:admin`

### Viajes Planificados

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| `GET` | `/api/planning/trips` | Listar viajes (filtros: status, priority, vehicle_id, operator_id, scheduled_departure) |
| `POST` | `/api/planning/trips` | Crear viaje en borrador |
| `GET` | `/api/planning/trips/{id}` | Detalle del viaje |
| `PUT` | `/api/planning/trips/{id}` | Actualizar viaje |
| `DELETE` | `/api/planning/trips/{id}` | Eliminar (solo `draft`) |
| `PATCH` | `/api/planning/trips/{id}/confirm` | Confirmar viaje (asignar recursos) |
| `PATCH` | `/api/planning/trips/{id}/start` | Registrar salida real |
| `PATCH` | `/api/planning/trips/{id}/complete` | Registrar llegada real |
| `PATCH` | `/api/planning/trips/{id}/cancel` | Cancelar viaje |

### Mantenimiento Programado

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| `GET` | `/api/planning/maintenance` | Listar mantenimientos (filtros: status, vehicle_id, scheduled_date) |
| `POST` | `/api/planning/maintenance` | Registrar mantenimiento |
| `GET` | `/api/planning/maintenance/{id}` | Detalle del mantenimiento |
| `PUT` | `/api/planning/maintenance/{id}` | Actualizar datos |
| `DELETE` | `/api/planning/maintenance/{id}` | Eliminar (solo `scheduled`) |
| `PATCH` | `/api/planning/maintenance/{id}/start` | Confirmar ingreso al taller |
| `PATCH` | `/api/planning/maintenance/{id}/complete` | Confirmar egreso del taller |
| `PATCH` | `/api/planning/maintenance/{id}/cancel` | Cancelar mantenimiento |

### Turnos de Operadores

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| `GET` | `/api/planning/shifts` | Listar turnos (filtros: operator_id, shift_type, start_datetime) |
| `POST` | `/api/planning/shifts` | Registrar turno |
| `GET` | `/api/planning/shifts/{id}` | Detalle del turno |
| `PUT` | `/api/planning/shifts/{id}` | Actualizar turno |
| `DELETE` | `/api/planning/shifts/{id}` | Eliminar turno |

### Disponibilidad (consultas transversales)

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| `GET` | `/api/planning/availability/vehicles` | Vehículos disponibles en un rango de fechas |
| `GET` | `/api/planning/availability/operators` | Operadores disponibles en un rango de fechas |
| `GET` | `/api/planning/conflicts` | Conflictos detectados (mantenimientos vs viajes, turnos vs asignaciones) |

---

## Contratos de API (ejemplos)

### `POST /api/planning/trips`
**Request:**
```json
{
  "client_id": 3,
  "trip_number": "TRIP-2026-0042",
  "origin": "Santiago, Región Metropolitana",
  "destination": "Antofagasta, Región de Antofagasta",
  "scheduled_departure": "2026-04-15T06:00:00",
  "scheduled_arrival": "2026-04-16T18:00:00",
  "cargo_type": "general",
  "cargo_description": "Insumos industriales",
  "weight_kg": 8500,
  "distance_km": 1350,
  "priority": "high"
}
```
**Response 201:**
```json
{
  "id": 42,
  "trip_number": "TRIP-2026-0042",
  "status": "draft",
  "priority": "high",
  "origin": "Santiago, Región Metropolitana",
  "destination": "Antofagasta, Región de Antofagasta",
  "scheduled_departure": "2026-04-15T06:00:00",
  "scheduled_arrival": "2026-04-16T18:00:00",
  "vehicle": null,
  "operator": null
}
```

### `PATCH /api/planning/trips/{id}/confirm`
**Request:**
```json
{
  "vehicle_id": 7,
  "operator_id": 2
}
```
**Response 200:**
```json
{
  "id": 42,
  "status": "confirmed",
  "vehicle": { "id": 7, "plate": "XYZ-789", "status": "available" },
  "operator": { "id": 2, "name": "Carlos López", "status": "available" },
  "conflicts": []
}
```
> Si hay conflictos, el campo `conflicts` detalla los solapamientos encontrados y la confirmación se rechaza con HTTP 422.

### `GET /api/planning/availability/vehicles?from=2026-04-15&to=2026-04-17`
**Response 200:**
```json
{
  "available": [
    { "id": 7, "plate": "XYZ-789", "type": "truck", "payload_kg": 15000 },
    { "id": 9, "plate": "LMN-456", "type": "semi",  "payload_kg": 28000 }
  ],
  "unavailable": [
    { "id": 3, "plate": "ABC-123", "reason": "maintenance", "maintenance_id": 5 },
    { "id": 5, "plate": "DEF-456", "reason": "trip",        "trip_id": 38 }
  ]
}
```

### `POST /api/planning/maintenance`
**Request:**
```json
{
  "vehicle_id": 3,
  "maintenance_type": "preventive",
  "description": "Cambio de aceite, filtros y revisión general cada 30.000 km",
  "scheduled_date": "2026-04-20",
  "estimated_duration_days": 2,
  "workshop": "Taller Central Volvo",
  "estimated_cost": 350000,
  "mileage_at_service": 90000,
  "next_service_km": 120000
}
```
**Response 201:**
```json
{
  "id": 8,
  "vehicle": { "id": 3, "plate": "ABC-123" },
  "maintenance_type": "preventive",
  "scheduled_date": "2026-04-20",
  "estimated_duration_days": 2,
  "status": "scheduled"
}
```

### `POST /api/planning/shifts`
**Request:**
```json
{
  "operator_id": 2,
  "shift_type": "rest",
  "start_datetime": "2026-04-16T18:00:00",
  "end_datetime": "2026-04-17T06:00:00",
  "notes": "Descanso obligatorio post-viaje"
}
```
**Response 201:**
```json
{
  "id": 15,
  "operator": { "id": 2, "name": "Carlos López" },
  "shift_type": "rest",
  "start_datetime": "2026-04-16T18:00:00",
  "end_datetime": "2026-04-17T06:00:00"
}
```

---

## Flujo operativo completo

```
1. PLANIFICACIÓN ANTICIPADA
   Administrador crea viajes (status: draft) con fechas y carga
   Administrador programa mantenimientos preventivos por kilómetros o fecha
   Administrador carga turnos de trabajo y descanso de operadores
   ↓
2. ASIGNACIÓN DE RECURSOS
   Administrador consulta disponibilidad de vehículos y operadores en el período
   El sistema valida conflictos (mantenimiento, turnos, viajes solapados)
   Administrador confirma el viaje asignando vehículo + operador (status: confirmed)
   ↓
3. EJECUCIÓN DEL VIAJE
   Al registrar salida real → viaje: confirmed → in_progress
   → vehículo: available → on_route (vía módulo Transporte)
   → operador: available → on_duty  (vía módulo Transporte)
   ↓
4. CIERRE
   Al registrar llegada real → viaje: in_progress → completed
   → vehículo y operador regresan a available
   → Si hay mantenimiento posterior programado → vehículo queda bloqueado

5. MANTENIMIENTO
   Al confirmar ingreso al taller → mantenimiento: scheduled → in_progress
   → vehículo: available → maintenance (vía módulo Transporte)
   Al confirmar egreso → mantenimiento: in_progress → completed
   → vehículo: maintenance → available
   → Se actualiza kilometraje y se programan próximos servicios
```

---

## Estructura de archivos propuesta (backend)

```
backend/app/
├── Http/
│   ├── Controllers/Api/Planning/
│   │   ├── TripController.php
│   │   ├── MaintenanceController.php
│   │   ├── ShiftController.php
│   │   └── AvailabilityController.php
│   ├── Requests/Planning/
│   │   ├── StoreTripRequest.php
│   │   ├── UpdateTripRequest.php
│   │   ├── ConfirmTripRequest.php
│   │   ├── StoreMaintenanceRequest.php
│   │   ├── UpdateMaintenanceRequest.php
│   │   ├── StoreShiftRequest.php
│   │   └── UpdateShiftRequest.php
│   └── Resources/Planning/
│       ├── TripResource.php
│       ├── MaintenanceResource.php
│       └── ShiftResource.php
└── Models/
    ├── TripPlan.php
    ├── MaintenanceSchedule.php
    └── OperatorShift.php
```

## Estructura de archivos propuesta (frontend)

```
frontend/src/
├── api/
│   ├── trips.js
│   ├── maintenance.js
│   └── shifts.js
├── stores/
│   ├── trips.js
│   ├── maintenance.js
│   └── shifts.js
└── pages/planning/
    ├── Trips.vue           # Lista y gestión de viajes planificados
    ├── Maintenance.vue     # Programación de mantenimientos
    └── Shifts.vue          # Gestión de turnos de operadores
```

---

## Dependencias con otros módulos

| Módulo | Relación |
|--------|----------|
| **Administración → Empresas** | Viajes, mantenimientos y turnos pertenecen a una empresa |
| **Administración → Clientes** | Los viajes pueden estar asociados a un cliente contratante |
| **Transporte → Vehículos** | Los viajes y mantenimientos referencian vehículos; su estado (`on_route`, `maintenance`) es gestionado desde aquí |
| **Transporte → Operadores** | Los viajes y turnos referencian operadores; su estado (`on_duty`) es gestionado desde aquí |
| **Transporte → Asignaciones** | Al iniciar un viaje confirmado se crea una `VehicleAssignment`; al completarlo se libera |
| **Distribución → Rutas** | Una ruta de distribución puede opcionalmente estar vinculada a un viaje planificado (extensión futura) |

---

## Notas de diseño y consideraciones

### Conflictos de recursos
La consulta `GET /api/planning/conflicts` analiza en tiempo real:
- Vehículos con mantenimiento solapado con viajes confirmados en el mismo período.
- Operadores con turnos de descanso/licencia solapados con viajes o rutas asignadas.
- Viajes dobles: mismo vehículo u operador en dos viajes confirmados en simultáneo.

El endpoint de disponibilidad (`/availability/vehicles` y `/availability/operators`) recibe `from` y `to` como parámetros de fecha y excluye automáticamente los recursos con conflictos en ese rango.

### Integración con Distribución
Las rutas de distribución (módulo Distribución) utilizan vehículos y operadores en períodos cortos (día a día). Los viajes del módulo de Planificación son de mayor duración (interdistancia, multiple días). Ambos consumen del mismo pool de activos, por lo que la verificación de conflictos debe cruzar ambas tablas al asignar.

### Mantenimiento predictivo
El campo `next_service_km` permite al sistema alertar automáticamente cuando `current_mileage` del vehículo se aproxima al valor de próximo servicio (sugerido: alerta a -500 km). Esta integración puede conectar con el endpoint `GET /api/transport/alerts`.

---

## Próximos pasos sugeridos

1. Validar las entidades y flujos con el equipo operativo.
2. Implementar migraciones en orden: `trip_plans` → `maintenance_schedules` → `operator_shifts`.
3. Implementar modelos Eloquent con relaciones, constantes y validaciones de solapamiento.
4. Implementar Form Requests, Resources y Controllers.
5. Implementar `AvailabilityController` con queries de rango de fechas JOIN sobre trips, maintenance y shifts.
6. Registrar rutas en `api.php` bajo el prefijo `/api/planning`.
7. Implementar frontend: stores Pinia, API clients y páginas Vue.
8. Integrar alertas de próximo servicio en `GET /api/transport/alerts`.

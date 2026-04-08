# Módulo: Distribución — Diseño Funcional

## Propósito

El módulo de Distribución gestiona el ciclo de vida de los **pedidos de entrega** y las **rutas de distribución**. Su responsabilidad es recibir solicitudes de entrega de clientes, organizar puntos de entrega en rutas optimizadas, asignar esas rutas a un vehículo y operador disponibles, y registrar el estado de cada entrega en tiempo real.

Este módulo consume los activos del módulo de Transporte (vehículos y operadores en estado `available`) y se integra con el módulo de Administración (clientes) para identificar al destinatario de cada pedido.

---

## Alcance funcional

| Función | Descripción |
|---------|-------------|
| **Pedidos** | Registro y seguimiento de cada solicitud de entrega |
| **Rutas** | Agrupación de pedidos en una ruta con secuencia de paradas |
| **Despacho** | Asignación de una ruta a un vehículo + operador |
| **Ejecución** | Registro de llegada, entrega y fallos en cada parada |
| **Cierre** | Cierre de ruta con resumen de entregas completadas / fallidas |

---

## Entidades

### 1. Pedido de Entrega (`delivery_orders`)

Representa una solicitud de entrega generada por o para un cliente.

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | integer | PK autoincremental |
| `company_id` | FK → companies | Empresa que gestiona el pedido |
| `client_id` | FK → clients | Cliente destinatario |
| `reference_number` | string(50), unique | Código interno del pedido |
| `description` | text, nullable | Descripción de la carga |
| `weight_kg` | decimal(10,2), nullable | Peso total de la carga (kg) |
| `volume_m3` | decimal(8,2), nullable | Volumen total de la carga (m³) |
| `delivery_address` | string(500) | Dirección de entrega |
| `delivery_lat` | decimal(10,7), nullable | Latitud del punto de entrega |
| `delivery_lng` | decimal(10,7), nullable | Longitud del punto de entrega |
| `contact_name` | string(255), nullable | Nombre del receptor en destino |
| `contact_phone` | string(30), nullable | Teléfono del receptor en destino |
| `requested_date` | date | Fecha solicitada de entrega |
| `status` | enum | Estado del pedido (ver estados) |
| `route_id` | FK → delivery_routes, nullable | Ruta a la que fue asignado |
| `sort_order` | smallint, nullable | Orden dentro de la ruta |
| `notes` | text, nullable | Observaciones internas |
| `timestamps` | — | created_at / updated_at |

**Estados del pedido (`status`):**

| Estado | Valor | Descripción |
|--------|-------|-------------|
| Pendiente | `pending` | Registrado, sin ruta asignada |
| Programado | `scheduled` | Incluido en una ruta planificada |
| En camino | `in_transit` | La ruta fue despachada, en tránsito |
| Entregado | `delivered` | Entrega confirmada en destino |
| Fallido | `failed` | No fue posible entregar (ausencia, dirección, etc.) |
| Cancelado | `cancelled` | Cancelado antes del despacho |

**Transiciones permitidas:**

```
pending    ──→ scheduled   (al incluirse en una ruta)
pending    ──→ cancelled
scheduled  ──→ pending     (si se retira de la ruta)
scheduled  ──→ in_transit  (al despachar la ruta)
in_transit ──→ delivered
in_transit ──→ failed
failed     ──→ pending     (reprogramar reintento)
```

---

### 2. Ruta de Distribución (`delivery_routes`)

Agrupa uno o más pedidos que serán atendidos en un mismo viaje, en secuencia ordenada.

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | integer | PK autoincremental |
| `company_id` | FK → companies | Empresa que gestiona la ruta |
| `name` | string(100) | Nombre o código de la ruta |
| `vehicle_id` | FK → vehicles, nullable | Vehículo asignado al despacho |
| `operator_id` | FK → operators, nullable | Operador asignado al despacho |
| `assignment_id` | FK → vehicle_assignments, nullable | Registro de asignación T&O |
| `planned_date` | date | Fecha planificada de ejecución |
| `dispatched_at` | timestamp, nullable | Momento efectivo del despacho |
| `completed_at` | timestamp, nullable | Momento de cierre de la ruta |
| `status` | enum | Estado de la ruta (ver estados) |
| `total_distance_km` | decimal(8,2), nullable | Distancia estimada total (km) |
| `notes` | text, nullable | Observaciones del despacho |
| `timestamps` | — | created_at / updated_at |

**Estados de la ruta (`status`):**

| Estado | Valor | Descripción |
|--------|-------|-------------|
| Borrador | `draft` | Creada, sin vehículo ni operador asignados |
| Planificada | `planned` | Con vehículo y operador confirmados, lista para despacho |
| En camino | `in_transit` | Despachada, en ejecución activa |
| Completada | `completed` | Todos los pedidos procesados (entregados o fallidos) |
| Cancelada | `cancelled` | Cancelada antes del despacho |

**Transiciones permitidas:**

```
draft      ──→ planned     (al asignar vehículo + operador)
draft      ──→ cancelled
planned    ──→ draft       (al remover vehículo u operador)
planned    ──→ in_transit  (al despachar)
planned    ──→ cancelled
in_transit ──→ completed   (cierre manual o automático)
```

---

### 3. Evento de Entrega (`delivery_events`)

Registro histórico de cada acción ocurrida sobre un pedido durante la ejecución de una ruta (llegada, entrega, fallo, foto, firma).

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | integer | PK autoincremental |
| `delivery_order_id` | FK → delivery_orders | Pedido al que pertenece |
| `route_id` | FK → delivery_routes | Ruta en que ocurrió |
| `event_type` | enum | Tipo de evento (ver tipos) |
| `occurred_at` | timestamp | Momento del evento |
| `lat` | decimal(10,7), nullable | Latitud GPS del evento |
| `lng` | decimal(10,7), nullable | Longitud GPS del evento |
| `notes` | text, nullable | Comentario del operador |
| `timestamps` | — | created_at / updated_at |

**Tipos de evento (`event_type`):**

| Tipo | Valor | Descripción |
|------|-------|-------------|
| Llegada | `arrived` | Operador llegó al punto de entrega |
| Entregado | `delivered` | Entrega completada y confirmada |
| Fallido | `failed` | Intento fallido (nadie en dirección, etc.) |
| Reintento | `retry_scheduled` | Pedido marcado para reintento |

---

## Reglas de negocio

### Pedidos
- El `reference_number` debe ser único por empresa.
- Un pedido en estado `in_transit` o `delivered` no puede ser cancelado.
- Si se cancela la ruta, todos sus pedidos en estado `scheduled` vuelven a `pending`.
- Un pedido `failed` puede ser reprogramado: vuelve a `pending` y queda disponible para otra ruta.

### Rutas
- Una ruta solo puede pasar a `planned` si tiene al menos un pedido en estado `scheduled`.
- El vehículo asignado debe estar en estado `available` y `is_active = true`.
- El operador asignado debe estar en estado `available` y `is_active = true`.
- Al despachar (`in_transit`): el vehículo y operador pasan a `on_route`/`on_duty` mediante una `VehicleAssignment` en el módulo de Transporte.
- Al completar o cancelar la ruta: se libera la `VehicleAssignment`, devolviendo vehículo y operador a `available`.
- No se puede agregar pedidos a una ruta en estado `in_transit` o posterior.

### Capacidad
- Al agregar un pedido a una ruta, el sistema verifica que la suma de `weight_kg` y `volume_m3` de todos los pedidos no supere la `payload_kg` y `volume_m3` del vehículo asignado (si estos campos están definidos).

---

## Migraciones

### `create_delivery_orders_table`
```php
Schema::create('delivery_orders', function (Blueprint $table) {
    $table->id();
    $table->foreignId('company_id')->constrained()->cascadeOnDelete();
    $table->foreignId('client_id')->constrained()->restrictOnDelete();
    $table->string('reference_number', 50)->unique();
    $table->text('description')->nullable();
    $table->decimal('weight_kg', 10, 2)->nullable();
    $table->decimal('volume_m3', 8, 2)->nullable();
    $table->string('delivery_address', 500);
    $table->decimal('delivery_lat', 10, 7)->nullable();
    $table->decimal('delivery_lng', 10, 7)->nullable();
    $table->string('contact_name')->nullable();
    $table->string('contact_phone', 30)->nullable();
    $table->date('requested_date');
    $table->string('status', 20)->default('pending');
    $table->foreignId('route_id')->nullable()->constrained('delivery_routes')->nullOnDelete();
    $table->smallInteger('sort_order')->nullable();
    $table->text('notes')->nullable();
    $table->timestamps();
});
```

### `create_delivery_routes_table`
```php
Schema::create('delivery_routes', function (Blueprint $table) {
    $table->id();
    $table->foreignId('company_id')->constrained()->cascadeOnDelete();
    $table->string('name', 100);
    $table->foreignId('vehicle_id')->nullable()->constrained()->nullOnDelete();
    $table->foreignId('operator_id')->nullable()->constrained()->nullOnDelete();
    $table->foreignId('assignment_id')->nullable()->constrained('vehicle_assignments')->nullOnDelete();
    $table->date('planned_date');
    $table->timestamp('dispatched_at')->nullable();
    $table->timestamp('completed_at')->nullable();
    $table->string('status', 20)->default('draft');
    $table->decimal('total_distance_km', 8, 2)->nullable();
    $table->text('notes')->nullable();
    $table->timestamps();
});
```

### `create_delivery_events_table`
```php
Schema::create('delivery_events', function (Blueprint $table) {
    $table->id();
    $table->foreignId('delivery_order_id')->constrained()->cascadeOnDelete();
    $table->foreignId('route_id')->constrained('delivery_routes')->cascadeOnDelete();
    $table->string('event_type', 30);
    $table->timestamp('occurred_at');
    $table->decimal('lat', 10, 7)->nullable();
    $table->decimal('lng', 10, 7)->nullable();
    $table->text('notes')->nullable();
    $table->timestamps();
});
```

---

## API Endpoints

Prefijo base: `/api/distribution`
Requieren: `auth.session` + `profile:admin`

### Pedidos

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| `GET` | `/api/distribution/orders` | Listar pedidos (filtros: status, client_id, requested_date, route_id) |
| `POST` | `/api/distribution/orders` | Crear pedido |
| `GET` | `/api/distribution/orders/{id}` | Detalle con ruta y eventos |
| `PUT` | `/api/distribution/orders/{id}` | Actualizar datos del pedido |
| `DELETE` | `/api/distribution/orders/{id}` | Eliminar (solo si `pending` o `cancelled`) |
| `PATCH` | `/api/distribution/orders/{id}/cancel` | Cancelar pedido |

### Rutas

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| `GET` | `/api/distribution/routes` | Listar rutas (filtros: status, planned_date, vehicle_id) |
| `POST` | `/api/distribution/routes` | Crear ruta en borrador |
| `GET` | `/api/distribution/routes/{id}` | Detalle con pedidos y asignación |
| `PUT` | `/api/distribution/routes/{id}` | Actualizar datos de la ruta |
| `DELETE` | `/api/distribution/routes/{id}` | Eliminar (solo si `draft`) |
| `POST` | `/api/distribution/routes/{id}/orders` | Agregar pedido a la ruta |
| `DELETE` | `/api/distribution/routes/{id}/orders/{orderId}` | Quitar pedido de la ruta |
| `PATCH` | `/api/distribution/routes/{id}/dispatch` | Despachar ruta |
| `PATCH` | `/api/distribution/routes/{id}/complete` | Cerrar ruta |
| `PATCH` | `/api/distribution/routes/{id}/cancel` | Cancelar ruta |

### Eventos de entrega

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| `POST` | `/api/distribution/orders/{id}/events` | Registrar evento en un pedido |
| `GET` | `/api/distribution/orders/{id}/events` | Historial de eventos del pedido |

---

## Contratos de API (ejemplos)

### `POST /api/distribution/orders`
**Request:**
```json
{
  "client_id": 4,
  "reference_number": "ORD-2026-0001",
  "description": "Electrodomésticos — fragil",
  "weight_kg": 120.50,
  "volume_m3": 0.80,
  "delivery_address": "Av. Libertador 1500, Santiago",
  "contact_name": "María González",
  "contact_phone": "+56912345678",
  "requested_date": "2026-04-10"
}
```
**Response 201:**
```json
{
  "id": 1,
  "reference_number": "ORD-2026-0001",
  "client": { "id": 4, "name": "Empresa ABC" },
  "status": "pending",
  "requested_date": "2026-04-10",
  "delivery_address": "Av. Libertador 1500, Santiago"
}
```

### `PATCH /api/distribution/routes/{id}/dispatch`
**Request:** _(sin body)_

**Response 200:**
```json
{
  "id": 3,
  "name": "Ruta Norte - 10 Abr",
  "status": "in_transit",
  "dispatched_at": "2026-04-10T08:30:00Z",
  "vehicle": { "id": 5, "plate": "ABC-123", "status": "on_route" },
  "operator": { "id": 2, "name": "Carlos López", "status": "on_duty" },
  "orders_count": 4
}
```

### `POST /api/distribution/orders/{id}/events`
**Request:**
```json
{
  "event_type": "delivered",
  "occurred_at": "2026-04-10T10:15:00Z",
  "lat": -33.4489,
  "lng": -70.6693,
  "notes": "Recibió el portero del edificio"
}
```
**Response 201:**
```json
{
  "id": 17,
  "event_type": "delivered",
  "occurred_at": "2026-04-10T10:15:00Z",
  "order": { "id": 1, "reference_number": "ORD-2026-0001", "status": "delivered" }
}
```

---

## Flujo operativo completo

```
1. REGISTRO
   Administrador crea pedidos (status: pending)
   ↓
2. PLANIFICACIÓN
   Administrador crea ruta (status: draft)
   Agrega pedidos a la ruta → pedidos pasan a scheduled
   Asigna vehículo + operador → ruta pasa a planned
   ↓
3. DESPACHO
   Administrador despacha la ruta (status: in_transit)
   → vehículo: available → on_route
   → operador: available → on_duty
   → VehicleAssignment creada
   → pedidos: scheduled → in_transit
   ↓
4. EJECUCIÓN
   Por cada parada:
   - Operador registra arrived
   - Operador registra delivered → pedido: in_transit → delivered
     o failed → pedido: in_transit → failed
   ↓
5. CIERRE
   Administrador (u operador) cierra la ruta (status: completed)
   → VehicleAssignment liberada
   → vehículo y operador regresan a available
   → pedidos failed quedan disponibles para reprogramar
```

---

## Estructura de archivos propuesta (backend)

```
backend/app/
├── Http/
│   ├── Controllers/Api/Distribution/
│   │   ├── OrderController.php
│   │   ├── RouteController.php
│   │   └── EventController.php
│   ├── Requests/Distribution/
│   │   ├── StoreOrderRequest.php
│   │   ├── UpdateOrderRequest.php
│   │   ├── StoreRouteRequest.php
│   │   ├── UpdateRouteRequest.php
│   │   └── StoreEventRequest.php
│   └── Resources/Distribution/
│       ├── OrderResource.php
│       ├── RouteResource.php
│       └── EventResource.php
└── Models/
    ├── DeliveryOrder.php
    ├── DeliveryRoute.php
    └── DeliveryEvent.php
```

## Estructura de archivos propuesta (frontend)

```
frontend/src/
├── api/
│   ├── orders.js
│   └── routes.js
├── stores/
│   ├── orders.js
│   └── routes.js
└── pages/distribution/
    ├── Orders.vue          # Lista y gestión de pedidos
    ├── Routes.vue          # Lista y gestión de rutas
    └── RouteDetail.vue     # Vista de detalle de ruta con mapa de paradas
```

---

## Dependencias con otros módulos

| Módulo | Relación |
|--------|----------|
| **Administración → Empresas** | `delivery_orders` y `delivery_routes` pertenecen a una empresa |
| **Administración → Clientes** | Cada pedido tiene un cliente destinatario |
| **Transporte → Vehículos** | La ruta referencia el vehículo asignado al despacho |
| **Transporte → Operadores** | La ruta referencia el operador asignado al despacho |
| **Transporte → Asignaciones** | Al despachar se crea una `VehicleAssignment`; al cerrar se libera |

---

## Próximos pasos sugeridos

1. Validar este diseño con el stakeholder antes de implementar.
2. Implementar migraciones (en el orden: `delivery_routes` → `delivery_orders` → `delivery_events` por FK).
3. Implementar modelos Eloquent con relaciones, constantes de estados y `$attributes` defaults.
4. Implementar Form Requests, Resources y Controllers.
5. Registrar rutas en `api.php` bajo el prefijo `/api/distribution`.
6. Implementar frontend: stores Pinia, API clients y páginas Vue.

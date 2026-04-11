# 🚛 TMS — Transportation Management System

Sistema de gestión de transporte modular, construido con una arquitectura desacoplada: **Laravel 13** como API backend y **Vue 3** como SPA frontend.

---

## Descripción general

TMS es una plataforma web diseñada para gestionar las operaciones logísticas de una empresa de transporte. Está organizada en módulos funcionales independientes que cubren el ciclo completo del negocio:

| Módulo | Estado | Descripción |
|--------|--------|-------------|
| **Administración** | ✅ Implementado | Gestión de empresas, usuarios, clientes y roles |
| **Transporte** | ✅ Implementado | Flota de vehículos, operadores y asignaciones |
| **Distribución** | ✅ Implementado | Pedidos de entrega, rutas de distribución y eventos |
| **Planificación** | ✅ Implementado | Viajes, mantenimientos, turnos y disponibilidad |
| **Seguimiento** | 🚧 En diseño | Trazabilidad en tiempo real |

---

## Arquitectura

```
TMS/
├── backend/          # Laravel 13 — API RESTful
└── frontend/         # Vue 3 — Single Page Application
```

La comunicación entre capas se realiza exclusivamente mediante la API REST usando tokens **Bearer (Sanctum)**. El frontend nunca accede directamente a la base de datos.

---

## Módulo: Administración (implementado)

### Autenticación

- **Login** con email y contraseña → emite token Bearer
- **Logout** de sesión actual
- **Logout global** — invalida todos los tokens activos del usuario
- Endpoint `GET /api/auth/me` para obtener el perfil del usuario autenticado

### Roles

| Rol | Acceso |
|-----|--------|
| `admin` | Acceso total: gestión de empresas, usuarios y clientes |
| `user` | Acceso operativo limitado |

### Recursos gestionados

#### Empresas (`/api/companies`)
Registro de las organizaciones que operan dentro del sistema. Campos: nombre, RFC/Tax ID, dirección, teléfono, email, estado activo/inactivo.

#### Usuarios (`/api/users`)
Cuentas de acceso al sistema. Cada usuario tiene un rol asignado (`admin` o `user`), puede estar asociado a una empresa y puede ser activado o desactivado sin eliminar su historial.

#### Clientes (`/api/clients`)
Empresas o personas que contratan los servicios de transporte. Se vinculan opcionalmente a una empresa del sistema.

---

## Módulo: Transporte (implementado)

### Vehículos (`/api/transport/vehicles`)

Gestión de la flota de vehículos de la empresa. Campos principales: placa, marca, modelo, año, tipo (`truck`, `van`, `pickup`, `semi`, `refrigerated`, `tanker`, `minibus`), combustible (`diesel`, `gasoline`, `electric`, `gas`), capacidad de carga (kg y m³), kilometraje, vencimientos de seguro, revisión técnica y permiso de circulación.

**Estados y transiciones permitidas:**

| Estado | Puede pasar a |
|--------|---------------|
| `available` | `on_route`, `maintenance`, `inactive` |
| `on_route` | `available` |
| `maintenance` | `available`, `inactive` |
| `inactive` | — |

- Un vehículo no puede eliminarse si tiene una asignación activa.
- El endpoint `GET /api/transport/vehicles/expiring` devuelve los documentos que vencen en los próximos 30 días.

### Operadores (`/api/transport/operators`)

Conductores y operadores de la empresa. Campos principales: nombre, número de documento, teléfono, email, número y clase de licencia (`A1`, `A2`, `B`, `C`, `D`, `E`), vencimiento de licencia, contacto de emergencia.

**Estados y transiciones permitidas:**

| Estado | Puede pasar a |
|--------|---------------|
| `available` | `on_duty`, `off_duty`, `inactive` |
| `on_duty` | `available`, `off_duty` |
| `off_duty` | `available`, `inactive` |
| `inactive` | — |

- El endpoint `GET /api/transport/operators/expiring-licenses` devuelve los operadores con licencia por vencer en 30 días.

### Asignaciones (`/api/transport/assignments`)

Vincula un vehículo con un operador para un servicio. Reglas de negocio:
- El vehículo debe estar en estado `available`.
- El operador debe estar en estado `available`.
- No puede existir una asignación activa previa para el mismo vehículo u operador.
- Al confirmar la asignación, el vehículo pasa a `on_route` y el operador a `on_duty`.
- Al liberar (`PATCH /release`), ambos retornan a `available` y se registra `released_at`.

### Alertas (`GET /api/transport/alerts`)

Endpoint unificado que retorna:
- Vehículos con documentos por vencer en ≤ 30 días (seguro, revisión técnica, permiso de circulación).
- Operadores con licencia por vencer en ≤ 30 días.
- Contador total de alertas.

---

## Módulo: Distribución (implementado)

### Pedidos de Entrega (`/api/distribution/orders`)

Gestiona el ciclo de vida de cada solicitud de entrega. Campos principales: cliente destinatario, número de referencia único, dirección y coordenadas de entrega, peso (kg), volumen (m³), fecha solicitada, contacto receptor.

**Estados del pedido:**

| Estado | Valor | Descripción |
|--------|-------|-------------|
| Pendiente | `pending` | Registrado, sin ruta asignada |
| Programado | `scheduled` | Incluido en una ruta planificada |
| En camino | `in_transit` | Ruta despachada, en tránsito |
| Entregado | `delivered` | Entrega confirmada en destino |
| Fallido | `failed` | No fue posible entregar |
| Cancelado | `cancelled` | Cancelado antes del despacho |

Un pedido `failed` puede reprogramarse volviendo a `pending`. Los pedidos en estado `in_transit` o `delivered` no pueden cancelarse ni modificarse.

### Rutas de Distribución (`/api/distribution/routes`)

Agrupa pedidos en un viaje secuenciado con vehículo, operador y viaje planificado opcionales.

**Estados de la ruta:**

| Estado | Valor | Descripción |
|--------|-------|-------------|
| Borrador | `draft` | En preparación |
| Planificada | `planned` | Lista para despacho |
| En camino | `in_transit` | Despachada, en ejecución |
| Completada | `completed` | Todos los pedidos procesados |
| Cancelada | `cancelled` | Cancelada antes del despacho |

**Reglas de negocio:**
- Puede asociarse a un `TripPlan` mediante `trip_plan_id`.
- Al despachar: crea una `VehicleAssignment`, vehículo → `on_route`, operador → `on_duty`.
- Al completar o cancelar: libera la `VehicleAssignment`, ambos regresan a `available`.
- Verificación de capacidad (kg y m³) al agregar pedidos si el vehículo tiene límites definidos.
- Al cancelar la ruta, los pedidos `scheduled` regresan a `pending`.
- Los pedidos se pueden agregar/quitar desde el panel de detalle (solo en estado `draft` o `planned`).

### Eventos de Entrega (`/api/distribution/orders/{id}/events`)

Registro histórico de acciones por parada: `arrived`, `delivered`, `failed`, `retry_scheduled`. Cada evento puede incluir coordenadas GPS y notas del operador. El estado del pedido se actualiza automáticamente según el tipo de evento registrado.

---

## Módulo: Planificación (implementado)

### Viajes Planificados (`/api/planning/trips`)

Programación de viajes de larga distancia. Campos principales: número de viaje, cliente, origen, destino, fechas de salida/llegada estimadas, tipo de carga, peso y volumen, prioridad (`low`, `normal`, `high`, `urgent`), vehículo y operador asignados.

**Estados del viaje:**

| Estado | Valor | Descripción |
|--------|-------|-------------|
| Borrador | `draft` | En preparación |
| Confirmado | `confirmed` | Vehículo y operador asignados |
| En camino | `in_progress` | Viaje iniciado |
| Completado | `completed` | Viaje finalizado |
| Cancelado | `cancelled` | Cancelado |

**Transiciones:**
- `draft` → `confirmed`: requiere asignar vehículo y operador (con verificación de conflictos de mantenimiento).
- `confirmed` → `in_progress`: vehículo → `on_route`, operador → `on_duty`.
- `in_progress` → `completed`: vehículo y operador regresan a `available`.

Las rutas de distribución pueden vincularse a un viaje planificado mediante `trip_plan_id`.

### Mantenimientos (`/api/planning/maintenance`)

Programa y controla el mantenimiento preventivo y correctivo de los vehículos. Tipos: `preventive`, `corrective`, `inspection`, `tire_change`, `oil_change`.

**Estados:**

| Estado | Valor | Descripción |
|--------|-------|-------------|
| Programado | `scheduled` | Agendado |
| En ejecución | `in_progress` | En taller |
| Completado | `completed` | Finalizado con costo real |
| Cancelado | `cancelled` | Cancelado |

### Turnos de Operadores (`/api/planning/shifts`)

Registro de disponibilidad horaria de los operadores (fecha, hora inicio/fin, tipo de turno, notas).

### Disponibilidad (`/api/planning/availability`)

Endpoint que consulta vehículos y operadores disponibles para un rango de fechas, excluyendo los que tengan mantenimientos programados, viajes activos o turnos en conflicto.

### Conflictos (`/api/planning/conflicts`)

Detecta superposiciones de recursos (vehículo u operador asignados a más de un viaje simultáneo).

---

## Stack tecnológico

### Backend
| Componente | Versión |
|------------|---------|
| PHP | 8.4 |
| Laravel | 13 |
| Laravel Sanctum | 4.3 |
| Base de datos | SQLite (desarrollo) |

### Frontend
| Componente | Versión |
|------------|---------|
| Vue | 3.5 |
| Vite | 8 |
| Vue Router | 4 |
| Pinia | última estable |
| Axios | última estable |

---

## Instalación y ejecución local

### Requisitos previos
- PHP 8.3+
- Composer
- Node.js 20+
- npm

### Backend

```bash
cd backend

# Instalar dependencias
composer install

# Configurar entorno
cp .env.example .env
php artisan key:generate

# Crear base de datos y ejecutar migraciones con datos de prueba
php artisan migrate:fresh --seed

# Iniciar servidor
php artisan serve --port=8000
```

### Frontend

```bash
cd frontend

# Instalar dependencias
npm install

# Iniciar servidor de desarrollo (con proxy a :8000)
npm run dev
```

Abrir **http://localhost:5173** en el navegador.

---

## Ejecución con Docker

El proyecto incluye un entorno Docker de desarrollo con dos servicios:

- `backend`: Laravel API en `http://localhost:8000`
- `frontend`: Vue + Vite en `http://localhost:5173`

### Requisitos previos

- Docker Desktop
- Docker Compose

### Levantar el entorno

```bash
docker compose up --build
```

En el primer arranque, el contenedor del backend realiza automáticamente estas tareas:

- crea `backend/.env` a partir de `backend/.env.example` si no existe
- genera `APP_KEY` si falta
- crea `backend/database/database.sqlite`
- ejecuta `php artisan migrate:fresh --seed --force`

En arranques posteriores ejecuta `php artisan migrate --force` para aplicar cambios pendientes sin reinicializar datos.

### Detener el entorno

```bash
docker compose down
```

### Reiniciar desde cero

Si quieres reconstruir dependencias e inicializar nuevamente la base de datos:

```bash
docker compose down -v
docker compose up --build
```

### Notas de funcionamiento

- El frontend usa proxy interno hacia el servicio `backend`, por lo que no depende de `localhost` dentro del contenedor.
- El backend mantiene la base de datos en SQLite para desarrollo.
- El primer arranque puede tardar más porque instala dependencias de Composer y npm dentro de los volúmenes del contenedor.

---

## Postman

Se incluyeron archivos listos para importar en Postman:

- `postman/TMS.postman_collection.json`
- `postman/TMS.local.postman_environment.json`

Uso recomendado:

1. Importar ambos archivos en Postman.
2. Seleccionar el environment `TMS Local`.
3. Ejecutar la request `Auth / Login` para guardar automáticamente el bearer token.
4. Ajustar los IDs (`clientId`, `vehicleId`, `operatorId`, etc.) según los datos creados en tu entorno.

---

## Exportación de pedidos pendientes

Endpoint disponible:

- `POST /api/distribution/orders/export-pending`

Comportamiento:

- envía solo pedidos con estado `pending`
- excluye pedidos ya exportados previamente (`optimizer_exported_at` no nulo)
- permite filtros opcionales por `client_id` y `requested_date`
- guarda en base el último payload enviado, la última respuesta recibida y el último error de exportación
- solo marca un pedido como exportado si el servicio externo responde con `external_id`

Variables de entorno relacionadas:

- `ROUTE_OPTIMIZER_ORDERS_URL`
- `ROUTE_OPTIMIZER_TIME_WINDOW_START`
- `ROUTE_OPTIMIZER_TIME_WINDOW_END`

Notas de configuracion:

- `ROUTE_OPTIMIZER_ORDERS_URL` debe apuntar a la URL real del servicio externo
- si el backend corre en Docker, `localhost` apunta al contenedor del backend, no a tu maquina ni a otro servicio externo
- si el optimizador corre en tu host Windows, una opcion habitual es usar `http://host.docker.internal:8009/api/orders`

---

## Credenciales de acceso (seed)

| Campo | Valor |
|-------|-------|
| Email | `admin@tms.local` |
| Contraseña | `password` |
| Rol | `admin` |

> ⚠️ Cambiar estas credenciales antes de cualquier despliegue en producción.

---

## Navegación del sistema

| Sección | Ítems disponibles | Estado |
|---------|-------------------|--------|
| **Administración** | Empresas · Usuarios · Clientes | ✅ Activo |
| **Transporte** | Flota · Operadores · Asignaciones | ✅ Activo |
| **Distribución** | Pedidos · Rutas | ✅ Activo |
| **Planificación** | Viajes · Mantenimientos · Turnos | ✅ Activo |
| **Seguimiento** | — | 🚧 En diseño |

---

## API — Referencia de endpoints

Todos los endpoints protegidos requieren el header:
```
Authorization: Bearer <token>
```

### Autenticación

| Método | Endpoint | Auth | Descripción |
|--------|----------|------|-------------|
| `POST` | `/api/auth/login` | — | Obtener token |
| `GET` | `/api/auth/me` | ✅ | Perfil del usuario autenticado |
| `POST` | `/api/auth/logout` | ✅ | Cerrar sesión actual |
| `POST` | `/api/auth/logout-all` | ✅ | Cerrar todas las sesiones |

### Empresas

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| `GET` | `/api/companies` | Listar (paginado) |
| `POST` | `/api/companies` | Crear |
| `GET` | `/api/companies/{id}` | Ver detalle |
| `PUT` | `/api/companies/{id}` | Actualizar |
| `DELETE` | `/api/companies/{id}` | Eliminar |

### Usuarios

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| `GET` | `/api/users` | Listar (paginado) |
| `POST` | `/api/users` | Crear |
| `GET` | `/api/users/{id}` | Ver detalle |
| `PUT` | `/api/users/{id}` | Actualizar |
| `DELETE` | `/api/users/{id}` | Eliminar y revocar tokens |

### Clientes

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| `GET` | `/api/clients` | Listar (paginado) |
| `POST` | `/api/clients` | Crear |
| `GET` | `/api/clients/{id}` | Ver detalle |
| `PUT` | `/api/clients/{id}` | Actualizar |
| `DELETE` | `/api/clients/{id}` | Eliminar |

### Vehículos

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| `GET` | `/api/transport/vehicles` | Listar (filtros: `status`, `type`, `is_active`, `per_page`) |
| `POST` | `/api/transport/vehicles` | Crear |
| `GET` | `/api/transport/vehicles/{id}` | Ver detalle |
| `PUT` | `/api/transport/vehicles/{id}` | Actualizar |
| `DELETE` | `/api/transport/vehicles/{id}` | Eliminar |
| `PATCH` | `/api/transport/vehicles/{id}/status` | Cambiar estado |
| `GET` | `/api/transport/vehicles/expiring` | Documentos por vencer |

### Operadores

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| `GET` | `/api/transport/operators` | Listar (filtros: `status`, `is_active`, `per_page`) |
| `POST` | `/api/transport/operators` | Crear |
| `GET` | `/api/transport/operators/{id}` | Ver detalle |
| `PUT` | `/api/transport/operators/{id}` | Actualizar |
| `DELETE` | `/api/transport/operators/{id}` | Eliminar |
| `PATCH` | `/api/transport/operators/{id}/status` | Cambiar estado |
| `GET` | `/api/transport/operators/expiring-licenses` | Licencias por vencer |

### Asignaciones

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| `GET` | `/api/transport/assignments` | Historial paginado |
| `GET` | `/api/transport/assignments/active` | Asignaciones activas |
| `POST` | `/api/transport/assignments` | Crear asignación |
| `PATCH` | `/api/transport/assignments/{id}/release` | Liberar asignación |

### Alertas

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| `GET` | `/api/transport/alerts` | Alertas unificadas de documentos y licencias |

### Pedidos de Entrega

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| `GET` | `/api/distribution/orders` | Listar (filtros: `status`, `client_id`, `requested_date`, `route_id`) |
| `POST` | `/api/distribution/orders` | Crear pedido |
| `GET` | `/api/distribution/orders/{id}` | Detalle con ruta y eventos |
| `PUT` | `/api/distribution/orders/{id}` | Actualizar pedido |
| `DELETE` | `/api/distribution/orders/{id}` | Eliminar (solo `pending` o `cancelled`) |
| `PATCH` | `/api/distribution/orders/{id}/cancel` | Cancelar pedido |
| `GET` | `/api/distribution/orders/{id}/events` | Historial de eventos |
| `POST` | `/api/distribution/orders/{id}/events` | Registrar evento de entrega |

### Rutas de Distribución

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| `GET` | `/api/distribution/routes` | Listar (filtros: `status`, `planned_date`, `vehicle_id`, `trip_plan_id`) |
| `POST` | `/api/distribution/routes` | Crear ruta en borrador |
| `GET` | `/api/distribution/routes/{id}` | Detalle con pedidos |
| `PUT` | `/api/distribution/routes/{id}` | Actualizar datos de la ruta |
| `DELETE` | `/api/distribution/routes/{id}` | Eliminar (solo `draft`) |
| `POST` | `/api/distribution/routes/{id}/orders` | Agregar pedido a la ruta |
| `DELETE` | `/api/distribution/routes/{id}/orders/{orderId}` | Quitar pedido de la ruta |
| `PATCH` | `/api/distribution/routes/{id}/dispatch` | Despachar ruta |
| `PATCH` | `/api/distribution/routes/{id}/complete` | Cerrar ruta |
| `PATCH` | `/api/distribution/routes/{id}/cancel` | Cancelar ruta |

### Viajes Planificados

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| `GET` | `/api/planning/trips` | Listar (filtros: `status`, `priority`, `vehicle_id`, `operator_id`, `from`, `to`, `per_page`) |
| `POST` | `/api/planning/trips` | Crear viaje |
| `GET` | `/api/planning/trips/{id}` | Detalle con rutas asociadas |
| `PUT` | `/api/planning/trips/{id}` | Actualizar viaje |
| `DELETE` | `/api/planning/trips/{id}` | Eliminar (solo `draft`) |
| `POST` | `/api/planning/trips/{id}/confirm` | Confirmar viaje (requiere `vehicle_id` y `operator_id`) |
| `POST` | `/api/planning/trips/{id}/start` | Iniciar viaje |
| `POST` | `/api/planning/trips/{id}/complete` | Completar viaje |
| `POST` | `/api/planning/trips/{id}/cancel` | Cancelar viaje |

### Mantenimientos

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| `GET` | `/api/planning/maintenance` | Listar mantenimientos |
| `POST` | `/api/planning/maintenance` | Programar mantenimiento |
| `GET` | `/api/planning/maintenance/{id}` | Ver detalle |
| `PUT` | `/api/planning/maintenance/{id}` | Actualizar |
| `DELETE` | `/api/planning/maintenance/{id}` | Eliminar |
| `POST` | `/api/planning/maintenance/{id}/start` | Iniciar mantenimiento |
| `POST` | `/api/planning/maintenance/{id}/complete` | Completar mantenimiento |
| `POST` | `/api/planning/maintenance/{id}/cancel` | Cancelar mantenimiento |

### Turnos

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| `GET` | `/api/planning/shifts` | Listar turnos |
| `POST` | `/api/planning/shifts` | Crear turno |
| `GET` | `/api/planning/shifts/{id}` | Ver detalle |
| `PUT` | `/api/planning/shifts/{id}` | Actualizar |
| `DELETE` | `/api/planning/shifts/{id}` | Eliminar |

### Disponibilidad y Conflictos

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| `GET` | `/api/planning/availability` | Recursos disponibles en rango de fechas |
| `GET` | `/api/planning/conflicts` | Conflictos de asignación detectados |

---

## Seguridad

- Los tokens Bearer son validados en cada request por el middleware `auth.session`
- El middleware `profile` controla el acceso por rol, devolviendo `403` si el rol es insuficiente
- Las cuentas desactivadas (`is_active = false`) no pueden autenticarse aunque el token sea válido
- La eliminación de un usuario revoca todos sus tokens activos
- El frontend invalida el token local y redirige a login ante cualquier respuesta `401`

---

## Estructura del proyecto

```
backend/
├── app/
│   ├── Http/
│   │   ├── Controllers/Api/
│   │   │   ├── AuthController.php
│   │   │   ├── UserController.php
│   │   │   ├── CompanyController.php
│   │   │   ├── ClientController.php
│   │   │   ├── Transport/
│   │   │   │   ├── VehicleController.php
│   │   │   │   ├── OperatorController.php
│   │   │   │   ├── AssignmentController.php
│   │   │   │   └── AlertController.php
│   │   │   ├── Distribution/
│   │   │   │   ├── OrderController.php
│   │   │   │   ├── RouteController.php
│   │   │   │   └── EventController.php
│   │   │   └── Planning/
│   │   │       ├── TripController.php
│   │   │       ├── MaintenanceController.php
│   │   │       ├── ShiftController.php
│   │   │       ├── AvailabilityController.php
│   │   │       └── ConflictController.php
│   │   ├── Middleware/          # AuthSession.php, Profile.php
│   │   ├── Requests/            # Form Requests por módulo
│   │   └── Resources/           # JSON Resources por módulo
│   └── Models/
│       ├── User.php, Company.php, Client.php
│       ├── Vehicle.php, Operator.php, VehicleAssignment.php
│       ├── DeliveryOrder.php, DeliveryRoute.php, DeliveryEvent.php
│       └── TripPlan.php, MaintenanceSchedule.php, OperatorShift.php
├── database/
│   ├── migrations/
│   └── seeders/                 # DatabaseSeeder — usuario admin inicial
└── routes/
    └── api.php

frontend/src/
├── api/                # auth.js, users.js, companies.js, clients.js,
│                       # vehicles.js, operators.js, assignments.js,
│                       # orders.js, routes.js, trips.js
├── stores/             # Pinia stores por módulo
├── router/             # index.js — guards requiresAuth / requiresAdmin
├── layouts/            # AuthLayout.vue, AppLayout.vue
├── pages/
│   ├── auth/           # Login.vue
│   ├── Dashboard.vue
│   ├── admin/          # Companies.vue, Users.vue, Clients.vue
│   ├── transport/      # Vehicles.vue, Operators.vue, Assignments.vue
│   ├── distribution/   # Orders.vue, Routes.vue
│   └── planning/       # Trips.vue, Maintenance.vue, Shifts.vue
└── assets/
    └── admin.css       # Estilos compartidos (btn--primary, field, modal-backdrop…)
```


Sistema de gestión de transporte modular, construido con una arquitectura desacoplada: **Laravel 13** como API backend y **Vue 3** como SPA frontend.

---

## Descripción general

TMS es una plataforma web diseñada para gestionar las operaciones logísticas de una empresa de transporte. Está organizada en módulos funcionales independientes que cubren el ciclo completo del negocio:

| Módulo | Estado | Descripción |
|--------|--------|-------------|
| **Administración** | ✅ Implementado | Gestión de empresas, usuarios, clientes y roles |
| **Transporte** | ✅ Implementado | Flota de vehículos, operadores y asignaciones |
| **Distribución** | ✅ Implementado | Pedidos de entrega, rutas de distribución y eventos |
| **Planificación** | 🚧 En diseño | Programación de viajes y cargas |
| **Seguimiento** | 🚧 En diseño | Trazabilidad en tiempo real |

---

## Arquitectura

```
TMS/
├── backend/          # Laravel 13 — API RESTful
└── frontend/         # Vue 3 — Single Page Application
```

La comunicación entre capas se realiza exclusivamente mediante la API REST usando tokens **Bearer (Sanctum)**. El frontend nunca accede directamente a la base de datos.

---

## Módulo: Transporte (implementado)

### Vehículos (`/api/transport/vehicles`)

Gestión de la flota de vehículos de la empresa. Campos principales: placa, marca, modelo, año, tipo (`truck`, `van`, `pickup`, `semi`, `refrigerated`, `tanker`, `minibus`), combustible (`diesel`, `gasoline`, `electric`, `gas`), capacidad de carga (kg y m³), kilometraje, vencimientos de seguro, revisión técnica y permiso de circulación.

**Estados y transiciones permitidas:**

| Estado | Puede pasar a |
|--------|---------------|
| `available` | `on_route`, `maintenance`, `inactive` |
| `on_route` | `available` |
| `maintenance` | `available`, `inactive` |
| `inactive` | — |

- Un vehículo no puede eliminarse si tiene una asignación activa.
- El endpoint `GET /api/transport/vehicles/expiring` devuelve los documentos que vencen en los próximos 30 días.

### Operadores (`/api/transport/operators`)

Conductores y operadores de la empresa. Campos principales: nombre, número de documento (`document_number`), teléfono, email, dirección, número y clase de licencia (`A1`, `A2`, `B`, `C`, `D`, `E`), vencimiento de licencia, contacto de emergencia y teléfono de emergencia.

**Estados y transiciones permitidas:**

| Estado | Puede pasar a |
|--------|---------------|
| `available` | `on_duty`, `off_duty`, `inactive` |
| `on_duty` | `available`, `off_duty` |
| `off_duty` | `available`, `inactive` |
| `inactive` | — |

- El endpoint `GET /api/transport/operators/expiring-licenses` devuelve los operadores con licencia por vencer en 30 días.

### Asignaciones (`/api/transport/assignments`)

Vincula un vehículo con un operador para un servicio. Reglas de negocio:
- El vehículo debe estar en estado `available`.
- El operador debe estar en estado `available`.
- No puede existir una asignación activa previa para el mismo vehículo u operador.
- Al confirmar la asignación, el vehículo pasa a `on_route` y el operador a `on_duty`.
- Al liberar (`PATCH /release`), ambos retornan a `available` y se registra `released_at`.

### Alertas (`GET /api/transport/alerts`)

Endpoint unificado que retorna:
- Vehículos con documentos por vencer en ≤ 30 días (seguro, revisión técnica, permiso de circulación).
- Operadores con licencia por vencer en ≤ 30 días.
- Contador total de alertas.

---

## Módulo: Distribución (implementado)

### Pedidos de Entrega (`/api/distribution/orders`)

Gestiona el ciclo de vida de cada solicitud de entrega. Campos principales: cliente destinatario, número de referencia único, dirección y coordenadas de entrega, peso (kg), volumen (m³), fecha solicitada, contacto receptor.

**Estados del pedido:**

| Estado | Valor | Descripción |
|--------|-------|-------------|
| Pendiente | `pending` | Registrado, sin ruta asignada |
| Programado | `scheduled` | Incluido en una ruta planificada |
| En camino | `in_transit` | Ruta despachada, en tránsito |
| Entregado | `delivered` | Entrega confirmada en destino |
| Fallido | `failed` | No fue posible entregar |
| Cancelado | `cancelled` | Cancelado antes del despacho |

Un pedido `failed` puede reprogramarse volviendo a `pending`. Los pedidos en estado `in_transit` o `delivered` no pueden cancelarse ni modificarse.

### Rutas de Distribución (`/api/distribution/routes`)

Agrupa pedidos en un viaje secuenciado con vehículo y operador asignados.

**Estados de la ruta:**

| Estado | Valor | Descripción |
|--------|-------|-------------|
| Borrador | `draft` | Sin vehículo ni operador asignados |
| Planificada | `planned` | Lista para despacho |
| En camino | `in_transit` | Despachada, en ejecución |
| Completada | `completed` | Todos los pedidos procesados |
| Cancelada | `cancelled` | Cancelada antes del despacho |

**Reglas de negocio:**
- Al despachar: crea una `VehicleAssignment`, vehículo → `on_route`, operador → `on_duty`.
- Al completar o cancelar: libera la `VehicleAssignment`, ambos regresan a `available`.
- Verificación de capacidad (kg y m³) al agregar pedidos si el vehículo tiene límites definidos.
- Al cancelar la ruta, los pedidos `scheduled` regresan a `pending`.

### Eventos de Entrega (`/api/distribution/orders/{id}/events`)

Registro histórico de acciones por parada: `arrived`, `delivered`, `failed`, `retry_scheduled`. Cada evento puede incluir coordenadas GPS y notas del operador. El estado del pedido se actualiza automáticamente según el tipo de evento registrado.

---

## Módulo: Administración (implementado)

### Autenticación

- **Login** con email y contraseña → emite token Bearer
- **Logout** de sesión actual
- **Logout global** — invalida todos los tokens activos del usuario
- Endpoint `GET /api/auth/me` para obtener el perfil del usuario autenticado

### Roles

| Rol | Acceso |
|-----|--------|
| `admin` | Acceso total: gestión de empresas, usuarios y clientes |
| `user` | Acceso operativo limitado (módulos en desarrollo) |

### Recursos gestionados

#### Empresas (`/api/companies`)
Registro de las organizaciones que operan dentro del sistema. Campos: nombre, RFC/Tax ID, dirección, teléfono, email, estado activo/inactivo.

#### Usuarios (`/api/users`)
Cuentas de acceso al sistema. Cada usuario tiene un rol asignado (`admin` o `user`), puede estar asociado a una empresa y puede ser activado o desactivado sin eliminar su historial.

#### Clientes (`/api/clients`)
Empresas o personas que contratan los servicios de transporte. Se vinculan opcionalmente a una empresa del sistema.

---

## Stack tecnológico

### Backend
| Componente | Versión |
|------------|---------|
| PHP | 8.4 |
| Laravel | 13 |
| Laravel Sanctum | 4.3 |
| Base de datos | SQLite (desarrollo) |

### Frontend
| Componente | Versión |
|------------|---------|
| Vue | 3.5 |
| Vite | 8 |
| Vue Router | 4 |
| Pinia | última estable |
| Axios | última estable |

---

## Instalación y ejecución local

### Requisitos previos
- PHP 8.3+
- Composer
- Node.js 20+
- npm

### Backend

```bash
cd backend

# Instalar dependencias
composer install

# Configurar entorno
cp .env.example .env
php artisan key:generate

# Crear base de datos y ejecutar migraciones con datos de prueba
php artisan migrate:fresh --seed

# Iniciar servidor
php artisan serve --port=8000
```

### Frontend

```bash
cd frontend

# Instalar dependencias
npm install

# Iniciar servidor de desarrollo (con proxy a :8000)
npm run dev
```

Abrir **http://localhost:5173** en el navegador.

---

## Credenciales de acceso (seed)

| Campo | Valor |
|-------|-------|
| Email | `admin@tms.local` |
| Contraseña | `password` |
| Rol | `admin` |

> ⚠️ Cambiar estas credenciales antes de cualquier despliegue en producción.

---

## Navegación del sistema

El sidebar de la aplicación está organizado en secciones agrupadas:

| Sección | Ítems disponibles | Estado |
|---------|-------------------|--------|
| **Administración** | Empresas · Usuarios · Clientes | ✅ Activo |
| **Transporte** | Flota · Operadores · Asignaciones | ✅ Activo |
| **Distribución** | Pedidos · Rutas | ✅ Activo |
| **Planificación** | — | 🚧 En diseño |
| **Seguimiento** | — | 🚧 En diseño |

---

## API — Referencia de endpoints

Todos los endpoints protegidos requieren el header:
```
Authorization: Bearer <token>
```

### Autenticación

| Método | Endpoint | Auth | Descripción |
|--------|----------|------|-------------|
| `POST` | `/api/auth/login` | — | Obtener token |
| `GET` | `/api/auth/me` | ✅ | Perfil del usuario autenticado |
| `POST` | `/api/auth/logout` | ✅ | Cerrar sesión actual |
| `POST` | `/api/auth/logout-all` | ✅ | Cerrar todas las sesiones |

### Empresas (solo `admin`)

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| `GET` | `/api/companies` | Listar (paginado) |
| `POST` | `/api/companies` | Crear |
| `GET` | `/api/companies/{id}` | Ver detalle |
| `PUT` | `/api/companies/{id}` | Actualizar |
| `DELETE` | `/api/companies/{id}` | Eliminar |

### Usuarios (solo `admin`)

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| `GET` | `/api/users` | Listar (paginado) |
| `POST` | `/api/users` | Crear |
| `GET` | `/api/users/{id}` | Ver detalle |
| `PUT` | `/api/users/{id}` | Actualizar |
| `DELETE` | `/api/users/{id}` | Eliminar y revocar tokens |

### Clientes (solo `admin`)

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| `GET` | `/api/clients` | Listar (paginado) |
| `POST` | `/api/clients` | Crear |
| `GET` | `/api/clients/{id}` | Ver detalle |
| `PUT` | `/api/clients/{id}` | Actualizar |
| `DELETE` | `/api/clients/{id}` | Eliminar |

### Vehículos (solo `admin`)

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| `GET` | `/api/transport/vehicles` | Listar (filtros: status, type) |
| `POST` | `/api/transport/vehicles` | Crear |
| `GET` | `/api/transport/vehicles/{id}` | Ver detalle |
| `PUT` | `/api/transport/vehicles/{id}` | Actualizar |
| `DELETE` | `/api/transport/vehicles/{id}` | Eliminar |
| `PATCH` | `/api/transport/vehicles/{id}/status` | Cambiar estado |
| `GET` | `/api/transport/vehicles/expiring` | Documentos por vencer |

### Operadores (solo `admin`)

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| `GET` | `/api/transport/operators` | Listar (filtros: status) |
| `POST` | `/api/transport/operators` | Crear |
| `GET` | `/api/transport/operators/{id}` | Ver detalle |
| `PUT` | `/api/transport/operators/{id}` | Actualizar |
| `DELETE` | `/api/transport/operators/{id}` | Eliminar |
| `PATCH` | `/api/transport/operators/{id}/status` | Cambiar estado |
| `GET` | `/api/transport/operators/expiring-licenses` | Licencias por vencer |

### Asignaciones (solo `admin`)

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| `GET` | `/api/transport/assignments` | Historial paginado |
| `GET` | `/api/transport/assignments/active` | Asignaciones activas |
| `POST` | `/api/transport/assignments` | Crear asignación |
| `PATCH` | `/api/transport/assignments/{id}/release` | Liberar asignación |

### Alertas (solo `admin`)

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| `GET` | `/api/transport/alerts` | Alertas unificadas de documentos y licencias |

### Pedidos de Entrega (solo `admin`)

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| `GET` | `/api/distribution/orders` | Listar (filtros: status, client_id, requested_date, route_id) |
| `POST` | `/api/distribution/orders` | Crear pedido |
| `GET` | `/api/distribution/orders/{id}` | Detalle con ruta y eventos |
| `PUT` | `/api/distribution/orders/{id}` | Actualizar pedido |
| `DELETE` | `/api/distribution/orders/{id}` | Eliminar (solo `pending` o `cancelled`) |
| `PATCH` | `/api/distribution/orders/{id}/cancel` | Cancelar pedido |
| `GET` | `/api/distribution/orders/{id}/events` | Historial de eventos |
| `POST` | `/api/distribution/orders/{id}/events` | Registrar evento de entrega |

### Rutas de Distribución (solo `admin`)

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| `GET` | `/api/distribution/routes` | Listar (filtros: status, planned_date, vehicle_id) |
| `POST` | `/api/distribution/routes` | Crear ruta en borrador |
| `GET` | `/api/distribution/routes/{id}` | Detalle con pedidos y asignación |
| `PUT` | `/api/distribution/routes/{id}` | Actualizar datos de la ruta |
| `DELETE` | `/api/distribution/routes/{id}` | Eliminar (solo `draft`) |
| `POST` | `/api/distribution/routes/{id}/orders` | Agregar pedido a la ruta |
| `DELETE` | `/api/distribution/routes/{id}/orders/{orderId}` | Quitar pedido de la ruta |
| `PATCH` | `/api/distribution/routes/{id}/dispatch` | Despachar ruta |
| `PATCH` | `/api/distribution/routes/{id}/complete` | Cerrar ruta |
| `PATCH` | `/api/distribution/routes/{id}/cancel` | Cancelar ruta |

---

## Seguridad

- Los tokens Bearer son validados en cada request por el middleware `auth.session`
- El middleware `profile` controla el acceso por rol, devolviendo `403` si el rol es insuficiente
- Las cuentas desactivadas (`is_active = false`) no pueden autenticarse aunque el token sea válido
- La eliminación de un usuario revoca todos sus tokens activos
- El frontend invalida el token local y redirige a login ante cualquier respuesta `401`

---

## Estructura del backend

```
backend/
├── app/
│   ├── Http/
│   │   ├── Controllers/Api/
│   │   │   ├── AuthController.php
│   │   │   ├── UserController.php
│   │   │   ├── CompanyController.php
│   │   │   ├── ClientController.php
│   │   │   └── Transport/
│   │   │       ├── VehicleController.php
│   │   │       ├── OperatorController.php
│   │   │       ├── AssignmentController.php
│   │   │       └── AlertController.php
│   │   │   └── Distribution/
│   │   │       ├── OrderController.php
│   │   │       ├── RouteController.php
│   │   │       └── EventController.php
│   │   ├── Middleware/          # AuthSession.php, Profile.php
│   │   ├── Requests/            # Form Requests por módulo (Transport/, Distribution/)
│   │   └── Resources/           # *Resource por módulo (Transport/, Distribution/)
│   └── Models/                  # User, Company, Client, Vehicle, Operator, VehicleAssignment,
│                                 # DeliveryOrder, DeliveryRoute, DeliveryEvent
├── database/
│   ├── migrations/              # users, companies, clients, vehicles, operators,
│   │                            # vehicle_assignments, delivery_routes, delivery_orders,
│   │                            # delivery_events
│   └── seeders/                 # DatabaseSeeder — usuario admin inicial
├── routes/
│   └── api.php                  # 56 rutas registradas (19 admin + 19 transport + 18 distribution)
└── bootstrap/
    └── app.php                  # Registro de middlewares y rutas API
```

## Estructura del frontend

```
frontend/src/
├── api/                # auth.js, users.js, companies.js, clients.js,
│                       # vehicles.js, operators.js, assignments.js,
│                       # orders.js, routes.js
├── stores/             # auth.js, vehicles.js, operators.js, assignments.js,
│                       # orders.js, routes.js (Pinia)
├── router/             # index.js — guards requiresAuth / requiresAdmin
├── layouts/            # AuthLayout.vue, AppLayout.vue (sidebar con secciones agrupadas)
├── pages/
│   ├── auth/           # Login.vue
│   ├── Dashboard.vue
│   ├── admin/          # Companies.vue, Users.vue, Clients.vue
│   ├── transport/      # Vehicles.vue, Operators.vue, Assignments.vue
│   └── distribution/   # Orders.vue, Routes.vue
└── assets/
    └── admin.css       # Estilos compartidos (BEM-like: btn--primary, field, modal-backdrop…)
```

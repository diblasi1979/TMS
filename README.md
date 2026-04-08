# 🚛 TMS — Transportation Management System

Sistema de gestión de transporte modular, construido con una arquitectura desacoplada: **Laravel 13** como API backend y **Vue 3** como SPA frontend.

---

## Descripción general

TMS es una plataforma web diseñada para gestionar las operaciones logísticas de una empresa de transporte. Está organizada en módulos funcionales independientes que cubren el ciclo completo del negocio:

| Módulo | Estado | Descripción |
|--------|--------|-------------|
| **Administración** | ✅ Implementado | Gestión de empresas, usuarios, clientes y roles |
| **Transporte** | ✅ Implementado | Flota de vehículos, operadores y asignaciones |
| **Distribución** | 🔜 Próximamente | Gestión de rutas y puntos de entrega |
| **Planificación** | 🔜 Próximamente | Programación de viajes y cargas |
| **Seguimiento** | 🔜 Próximamente | Trazabilidad en tiempo real |

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

Gestión de la flota de vehículos de la empresa. Campos principales: placa, marca, modelo, año, tipo (`sedan`, `suv`, `pickup`, `van`, `truck`, `trailer`, `bus`, `motorcycle`, `other`), combustible (`gasoline`, `diesel`, `electric`, `hybrid`, `gas`), vencimientos de seguro, revisión técnica y permiso de circulación.

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

Conductores y operadores de la empresa. Campos principales: nombre, DNI/RUT, teléfono, email, número y clase de licencia (`A1`, `A2`, `B`, `C`, `D`, `E`), vencimiento de licencia.

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
│   │   ├── Middleware/          # AuthSession.php, Profile.php
│   │   ├── Requests/            # Form Requests por módulo
│   │   └── Resources/           # *Resource por módulo
│   └── Models/                  # User, Company, Client, Vehicle, Operator, VehicleAssignment
├── database/
│   ├── migrations/              # users, companies, clients, vehicles, operators, vehicle_assignments
│   └── seeders/                 # DatabaseSeeder — usuario admin inicial
├── routes/
│   └── api.php                  # 38 rutas registradas (19 admin + 19 transport)
└── bootstrap/
    └── app.php                  # Registro de middlewares y rutas API
```

## Estructura del frontend

```
frontend/src/
├── api/                # auth.js, users.js, companies.js, clients.js,
│                       # vehicles.js, operators.js, assignments.js
├── stores/             # auth.js, vehicles.js, operators.js, assignments.js (Pinia)
├── router/             # index.js — guards requiresAuth / requiresAdmin
├── layouts/            # AuthLayout.vue, AppLayout.vue (sidebar)
├── pages/
│   ├── auth/           # Login.vue
│   ├── Dashboard.vue
│   ├── admin/          # Companies.vue, Users.vue, Clients.vue
│   └── transport/      # Vehicles.vue, Operators.vue, Assignments.vue
└── assets/
    └── admin.css       # Estilos compartidos
```

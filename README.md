# 🚛 TMS — Transportation Management System

Sistema de gestión de transporte modular, construido con una arquitectura desacoplada: **Laravel 13** como API backend y **Vue 3** como SPA frontend.

---

## Descripción general

TMS es una plataforma web diseñada para gestionar las operaciones logísticas de una empresa de transporte. Está organizada en módulos funcionales independientes que cubren el ciclo completo del negocio:

| Módulo | Estado | Descripción |
|--------|--------|-------------|
| **Administración** | ✅ Implementado | Gestión de empresas, usuarios, clientes y roles |
| **Distribución** | 🔜 Próximamente | Gestión de rutas y puntos de entrega |
| **Transporte** | 🔜 Próximamente | Flota de vehículos y operadores |
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
│   │   ├── Controllers/Api/     # AuthController, UserController, CompanyController, ClientController
│   │   ├── Middleware/          # AuthSession.php, Profile.php
│   │   ├── Requests/            # Form Requests por módulo
│   │   └── Resources/           # UserResource, CompanyResource, ClientResource
│   └── Models/                  # User, Company, Client
├── database/
│   ├── migrations/              # Tablas: users, companies, clients, personal_access_tokens
│   └── seeders/                 # DatabaseSeeder — usuario admin inicial
├── routes/
│   └── api.php                  # 19 rutas registradas
└── bootstrap/
    └── app.php                  # Registro de middlewares y rutas API
```

## Estructura del frontend

```
frontend/src/
├── api/                # Clientes HTTP: auth.js, users.js, companies.js, clients.js
├── stores/             # auth.js (Pinia)
├── router/             # index.js — guards requiresAuth / requiresAdmin
├── layouts/            # AuthLayout.vue, AppLayout.vue (sidebar)
├── pages/
│   ├── auth/           # Login.vue
│   ├── Dashboard.vue
│   └── admin/          # Companies.vue, Users.vue, Clients.vue
└── assets/
    └── admin.css       # Estilos compartidos para vistas administrativas
```

# Sistema de Gestión de Delivery — La Posta (Backend API)

API REST desarrollada con **Laravel** (PHP 8.2+) para la gestión integral de pedidos, catálogo gastronómico, control de cocina, asignación de repartos y liquidación de pagos en establecimientos gastronómicos. Este repositorio conforma la capa de backend desacoplada del cliente Single Page Application (Angular 19).

---

## 1. Descripción General y Arquitectura

El sistema centraliza las operaciones del restaurante "La Posta", proporcionando una arquitectura RESTful segura, validada y documentada con OpenAPI / Swagger. La plataforma implementa un control de acceso basado en roles (RBAC) mediante tokens de portador (Bearer Tokens) con **Laravel Sanctum**.

### 1.1. Invariantes de Dominio y Reglas Arquitectónicas

1. **Ciclo de Vida del Pedido e Inmutabilidad en Cocina:**
   - Flujo de estados: `Pendiente` ➔ `En Preparación` ➔ `Listo` ➔ `En Camino` ➔ `Entregado` (o `Cancelado`).
   - **Regla estricta:** Únicamente los pedidos en estado `Pendiente` admiten agregación, edición o eliminación de ítems (`PedidoItem`). Una vez iniciada la preparación en cocina, cualquier intento de modificación es rechazado con código `400 Bad Request`.
2. **Integridad de Precios en el Servidor:**
   - El precio unitario de los productos y adicionales, los subtotales de línea y el `monto_total` del pedido se calculan y persisten exclusivamente en el servidor. Nunca se confía en importes calculados por el cliente.
3. **Baja Lógica / Soft Deletes:**
   - Las entidades relacionales del sistema (productos, categorías, adicionales, estados, métodos de pago, usuarios y pedidos) emplean borrado lógico mediante la columna `deleted_at` (`SoftDeletes`) y sincronización del indicador `activo = false`. Nunca se efectúa borrado físico directo sobre registros con dependencias operativas.
4. **Seguridad y Documentación Swagger:**
   - Todos los endpoints protegidos requieren autenticación mediante middleware `auth:sanctum`.
   - Cada ruta protegida cuenta con respuestas formales documentadas para `401 Unauthorized` y `403 Forbidden` (en rutas restringidas al rol `Administrador`).

---

## 2. Stack Tecnológico

| Componente | Tecnología | Versión | Propósito |
|---|---|---|---|
| **Lenguaje** | PHP | `^8.2` | Entorno de ejecución de backend |
| **Framework** | Laravel | `^11.0 / ^12.0` | Arquitectura MVC, enrutamiento, Eloquent ORM |
| **Autenticación** | Laravel Sanctum | `^4.0` | Emisión y validación de Bearer Tokens para API |
| **Documentación** | L5-Swagger / OpenAPI | `^11.1` | Documentación interactiva y especificación OpenAPI 3.0 |
| **Base de Datos** | MySQL / MariaDB | `8.x+ / 10.x+` | Motor relacional con integridad referencial |
| **Testing Backend** | Pest PHP | `^3.8` | Suite de pruebas unitarias y de integración |
| **Testing E2E** | Playwright (Node.js) | `^1.57` | Pruebas de navegador extremo a extremo sobre Swagger UI |
| **Dependencias** | Composer | `2.x` | Gestor de paquetes PHP |

---

## 3. Estructura del Repositorio

```
backend/
├── app/
│   ├── Http/
│   │   ├── Controllers/          # Controladores RESTful con atributos OpenAPI
│   │   │   ├── AuthController.php
│   │   │   ├── CategoriaController.php
│   │   │   ├── ProductoController.php
│   │   │   ├── AdicionalController.php
│   │   │   ├── PedidoController.php
│   │   │   ├── PedidoItemController.php
│   │   │   ├── EstadoPedidoController.php
│   │   │   ├── PagoController.php
│   │   │   ├── ComprobanteController.php
│   │   │   ├── MetodoPagoController.php
│   │   │   ├── EstadoPagosController.php
│   │   │   ├── ClienteController.php
│   │   │   ├── RoleController.php
│   │   │   └── UserController.php
│   │   ├── Middleware/           # Middlewares de seguridad (CheckRole, HandleCors)
│   │   ├── Requests/             # FormRequests con validación desacoplada
│   │   │   ├── StorePedidoItemRequest.php
│   │   │   ├── UpdatePedidoItemRequest.php
│   │   │   ├── StoreEstadoPedidoRequest.php
│   │   │   └── UpdateEstadoPedidoRequest.php
│   │   └── Resources/            # API Resources para transformación de contratos JSON
│   │       ├── PedidoResource.php
│   │       ├── PedidoItemResource.php
│   │       ├── AdicionalResource.php
│   │       ├── PagoResource.php
│   │       ├── ComprobanteResource.php
│   │       ├── ProductoResource.php
│   │       ├── CategoriaResource.php
│   │       ├── EstadoPedidoResource.php
│   │       ├── ClienteResource.php
│   │       ├── UserResource.php
│   │       └── RoleResource.php
│   ├── Models/                   # Modelos Eloquent con relaciones y SoftDeletes
│   │   ├── User.php
│   │   ├── Role.php
│   │   ├── Cliente.php
│   │   ├── Categoria.php
│   │   ├── Producto.php
│   │   ├── Adicional.php
│   │   ├── Pedido.php
│   │   ├── PedidoItem.php
│   │   ├── EstadoPedido.php
│   │   ├── Pago.php
│   │   ├── Comprobante.php
│   │   ├── MetodoPago.php
│   │   └── EstadoPagos.php
│   └── Swagger/
│       └── OpenApi.php           # Información global del servidor y esquema de seguridad
├── config/
│   ├── cors.php                  # Política de CORS para el cliente Angular (localhost:4200)
│   └── l5-swagger.php            # Configuración de generación de documentación
├── database/
│   ├── migrations/               # Migraciones ordenadas preservando Foreign Keys
│   └── seeders/                  # Seeders de prueba (DatabaseSeeder, RoleSeeder, etc.)
├── routes/
│   ├── api.php                   # Rutas públicas y protegidas de la API
│   └── web.php
├── storage/
│   └── api-docs/                 # Artefacto JSON generado de la especificación OpenAPI
└── tests/
    └── Feature/                  # Cobertura funcional completa con Pest PHP
```

---

## 4. Catálogo Completo de Endpoints

### 4.1. Autenticación (`/api`)

| Método | Ruta | Acceso | Descripción |
|:---:|:---|:---:|:---|
| `POST` | `/api/login` | Público | Inicia sesión con email y contraseña. Retorna un Bearer Token Sanctum. |
| `POST` | `/api/logout` | `auth:sanctum` | Revoca el Bearer Token del usuario activo. |
| `GET` | `/api/user` | `auth:sanctum` | Obtiene el perfil del usuario autenticado. |

### 4.2. Catálogo Gastronómico (Lectura pública / Mutación Staff)

| Método | Ruta | Acceso | Descripción |
|:---:|:---|:---:|:---|
| `GET` | `/api/productos` | Público | Listar productos activos (con categoría vinculada). |
| `GET` | `/api/productos/{id}` | Público | Detalle de un producto puntual. |
| `POST` | `/api/productos` | `auth:sanctum` | Crear producto. Valida duplicados y asigna estado activo. |
| `PUT` | `/api/productos/{id}` | `auth:sanctum` | Actualizar atributos de un producto. |
| `DELETE` | `/api/productos/{id}` | `auth:sanctum` | Baja lógica de producto (`activo = false`, `deleted_at`). |
| `PUT` | `/api/productos/{id}/restore` | `auth:sanctum` | Restaurar un producto dado de baja. |
| `GET` | `/api/productos-desactivados` | `auth:sanctum` | Listar productos desactivados/eliminados lógicamente. |
| `GET` | `/api/categorias` | Público | Listar categorías activas. |
| `GET` | `/api/categorias/{id}` | Público | Detalle de una categoría puntual. |
| `POST` | `/api/categorias` | `auth:sanctum` | Crear nueva categoría. |
| `PUT` | `/api/categorias/{id}` | `auth:sanctum` | Actualizar categoría. |
| `DELETE` | `/api/categorias/{id}` | `auth:sanctum` | Baja lógica de categoría. |
| `PUT` | `/api/categorias/{id}/restore` | `auth:sanctum` | Restaurar categoría eliminada. |
| `GET` | `/api/categorias-desactivadas` | `auth:sanctum` | Listar categorías desactivadas. |
| `GET` | `/api/adicionales` | Público | Listar adicionales disponibles (toppings, guarniciones, etc.). |
| `GET` | `/api/adicionales/{id}` | Público | Detalle de un adicional puntual. |
| `POST` | `/api/adicionales` | `auth:sanctum` | Crear nuevo adicional. |
| `PUT` | `/api/adicionales/{id}` | `auth:sanctum` | Actualizar un adicional. |
| `DELETE` | `/api/adicionales/{id}` | `auth:sanctum` | Baja lógica de adicional. |
| `PUT` | `/api/adicionales/{id}/restore` | `auth:sanctum` | Restaurar adicional eliminado. |
| `GET` | `/api/adicionales-desactivados` | `auth:sanctum` | Listar adicionales desactivados. |

### 4.3. Gestión de Pedidos y Líneas de Pedido (`/api/pedidos`, `/api/pedido-items`)

| Método | Ruta | Acceso | Descripción |
|:---:|:---|:---:|:---|
| `GET` | `/api/pedidos` | `Administrador, Cocinero, Repartidor` | Listar pedidos ordenados cronológicamente con carga eager de cliente, items, adicionales vinculados y pagos. |
| `POST` | `/api/pedidos` | `auth:sanctum` | Registrar un nuevo pedido en estado inicial `Pendiente`. |
| `GET` | `/api/pedidos/{id}` | `auth:sanctum` | Detalle del pedido con array de items y adicionales serializados. |
| `PUT` | `/api/pedidos/{id}` | `auth:sanctum` | Actualizar estado o personal asignado al pedido. |
| `DELETE` | `/api/pedidos/{id}` | `auth:sanctum` | Cancelación o baja lógica del pedido. |
| `PUT` | `/api/pedidos/{id}/restore` | `auth:sanctum` | Restaurar pedido cancelado. |
| `GET` | `/api/pedidos-desactivados` | `auth:sanctum` | Listar pedidos cancelados/desactivados. |
| `GET` | `/api/pedido-items` | `auth:sanctum` | Listar items filtrando por `pedido_id`. |
| `POST` | `/api/pedidos/{pedido}/items` | `auth:sanctum` | Agregar ítem al pedido. Rechaza con 400 si el pedido no está en estado `Pendiente`. Rechaza con 422 si el producto está inactivo. |
| `GET` | `/api/pedido-items/{pedidoItem}` | `auth:sanctum` | Detalle de un ítem exponiendo `id`, producto, cantidad, subtotal y array de adicionales. |
| `PUT` | `/api/pedido-items/{pedidoItem}` | `auth:sanctum` | Modificar cantidad de un ítem. Rechaza con 400 si ya se inició la cocción. |
| `DELETE` | `/api/pedido-items/{pedidoItem}` | `auth:sanctum` | Quitar ítem del pedido. Rechaza con 400 si el pedido no es editable o si es el único ítem. |

### 4.4. Estados de Pedido (`/api/estado-pedidos`)

| Método | Ruta | Acceso | Descripción |
|:---:|:---|:---:|:---|
| `GET` | `/api/estado-pedidos` | `auth:sanctum` | Listar todos los estados de pedido activos. |
| `POST` | `/api/estado-pedidos` | `auth:sanctum` | Crear nuevo estado de pedido validando unicidad. |
| `GET` | `/api/estado-pedidos/{id}` | `auth:sanctum` | Detalle de un estado de pedido. |
| `PUT` | `/api/estado-pedidos/{id}` | `auth:sanctum` | Actualización parcial o total de nombre/descripción. |
| `DELETE` | `/api/estado-pedidos/{id}` | `auth:sanctum` | Baja lógica. Retorna `409 Conflict` si existen pedidos asociados. |
| `PUT` | `/api/estado-pedidos/{id}/restore` | `auth:sanctum` | Restaurar un estado dado de baja. |
| `GET` | `/api/estado-pedidos-desactivados` | `auth:sanctum` | Listar estados de pedido desactivados. |

### 4.5. Finanzas, Pagos y Comprobantes

| Método | Ruta | Acceso | Descripción |
|:---:|:---|:---:|:---|
| `GET` | `/api/pagos` | `auth:sanctum` | Listar pagos registrados con método, estado y comprobantes. |
| `POST` | `/api/pagos` | `auth:sanctum` | Registrar pago admitiendo campo opcional `referencia`. |
| `GET` | `/api/pagos/{id}` | `auth:sanctum` | Detalle del pago exponiendo `referencia` y array de comprobantes. |
| `PUT` | `/api/pagos/{id}` | `auth:sanctum` | Modificar datos de pago (ej. actualizar referencia). |
| `DELETE` | `/api/pagos/{id}` | `auth:sanctum` | Baja lógica de pago. |
| `PUT` | `/api/pagos/{id}/restore` | `auth:sanctum` | Restaurar pago eliminado. |
| `GET` | `/api/metodos-pago` | `auth:sanctum` | Listar métodos de pago (Efectivo, Transferencia, MercadoPago, etc.). |
| `POST` | `/api/metodos-pago` | `auth:sanctum` | Crear método de pago. |
| `GET` | `/api/metodos-pago/{id}` | `auth:sanctum` | Detalle de método de pago. |
| `PUT` | `/api/metodos-pago/{id}` | `auth:sanctum` | Actualizar método de pago. |
| `DELETE` | `/api/metodos-pago/{id}` | `auth:sanctum` | Baja lógica de método de pago. |
| `GET` | `/api/estado-pagos` | `auth:sanctum` | Listar estados de pago (Pendiente, Aprobado, Rechazado). |
| `GET` | `/api/comprobantes` | `auth:sanctum` | Listar comprobantes emitidos. |
| `POST` | `/api/comprobantes` | `auth:sanctum` | Asociar comprobante o factura a un pago. |

### 4.6. Administración General (Exclusivo Rol `Administrador`)

| Método | Ruta | Acceso | Descripción |
|:---:|:---|:---:|:---|
| `GET` | `/api/users` | `Administrador` | Listar usuarios y empleados del sistema. |
| `POST` | `/api/users` | `Administrador` | Crear usuario con contraseña hasheada y asignación de rol. |
| `GET` | `/api/users/{id}` | `Administrador` | Detalle de usuario. |
| `PUT` | `/api/users/{id}` | `Administrador` | Actualización parcial segura mediante `$user->fill($validated)` (admite modificar únicamente campos presentes). |
| `DELETE` | `/api/users/{id}` | `Administrador` | Baja lógica de usuario. Retorna 409 si tiene operaciones asignadas. |
| `PUT` | `/api/users/{id}/restore` | `Administrador` | Restaurar usuario desactivado. |
| `GET` | `/api/roles` | `Administrador` | Listar roles del sistema. |
| `POST` | `/api/roles` | `Administrador` | Crear rol permitiendo descripción opcional (`null-safe`). |
| `GET` | `/api/roles/{id}` | `Administrador` | Detalle de rol. |
| `PUT` | `/api/roles/{id}` | `Administrador` | Actualizar rol con regla `sometimes` en nombre. |
| `DELETE` | `/api/roles/{id}` | `Administrador` | Baja lógica de rol. |

---

## 5. Integración Frontend y Configuración CORS

El backend cuenta con soporte nativo para peticiones de origen cruzado (CORS) configurado en `config/cors.php`:

- **Orígenes autorizados:** `http://localhost:4200`, `http://127.0.0.1:4200` y configurable mediante variable de entorno `FRONTEND_URL`.
- **Patrones regex permitidos:** Cualquier puerto local para desarrollo (`#^http://localhost:\d+$#` y `#^http://127\.0\.0\.1:\d+$#`).
- **Métodos admitidos:** `GET`, `POST`, `PUT`, `PATCH`, `DELETE`, `OPTIONS`.
- **Cabeceras permitidas:** `Authorization`, `Content-Type`, `Accept`, `X-Requested-With`, `X-XSRF-TOKEN`, `Origin`.
- **Credenciales:** `'supports_credentials' => true` habilitado bajo orígenes explícitos cumpliendo la especificación W3C.
- **Preflight Cache:** 86400 segundos (24 horas) para optimizar el tráfico de solicitudes `OPTIONS`.

---

## 6. Documentación Interactiva (Swagger UI)

La API cuenta con documentación OpenAPI 3.0 auto-generada. Con el servidor en ejecución, la consola interactiva está disponible en:

```
http://127.0.0.1:8000/api/documentation
```

### Autenticación en Swagger
1. Realizar una petición a `POST /api/login` con credenciales de prueba.
2. Copiar el valor del token devuelto.
3. En la interfaz Swagger, presionar el botón verde **Authorize** e ingresar el token en formato:
   ```
   Bearer <tu-token-aqui>
   ```

### Regeneración de Especificación OpenAPI
Si se modifican atributos o controladores:

```bash
php artisan l5-swagger:generate
```

El artefacto JSON compilado se ubica en `storage/api-docs/api-docs.json`.

---

## 7. Pruebas Automatizadas (Testing)

El proyecto cuenta con una cobertura integral de pruebas que valida contratos, seguridad, lógica de negocio y compatibilidad de navegador.

### 7.1. Suite de Pruebas con Pest PHP

Para ejecutar todas las pruebas funcionales y unitarias:

```bash
php artisan test
```

*Estado actual de la suite:* **55 tests aprobados al 100% (221 aserciones)**.

#### Desglose de Pruebas Principales
- `tests/Feature/AuthLoginTest.php`: Login, hash de contraseñas, validación de credenciales erróneas y tokens Sanctum.
- `tests/Feature/AuthLogoutTest.php`: Revocación del Bearer Token y rechazo de logout anónimo.
- `tests/Feature/PedidoItemValidationTest.php`: Validación de producto inactivo (422) y regla de inmutabilidad en cocina para estados no pendientes (400).
- `tests/Feature/PedidoListTest.php`: Listado de pedidos y serialización del array `items.adicionales` en `index` y `show`.
- `tests/Feature/EstadoPedidoTest.php`: CRUD completo, validación de unicidad, soft deletes y restauración de estados.
- `tests/Feature/PagoTest.php`: Persistencia de columna `referencia` y serialización de la colección `comprobantes`.
- `tests/Feature/UserRolePartialUpdateTest.php`: Actualizaciones parciales seguras en usuarios y control null-safe en roles.
- `tests/Feature/SecurityRoutesTest.php`: Restricción de rutas protegidas (401), control RBAC por rol (403) y bypass de SuperAdmin.
- `tests/Feature/CorsTest.php`: Verificación de preflight OPTIONS (204) con credenciales y rechazo a orígenes no autorizados.

### 7.2. Pruebas E2E de Navegador con Playwright

Se dispone de un runner automatizado con **Playwright** en `e2e_swagger/` que valida la consola de Swagger UI en vivo contra el servidor local:

```bash
cd e2e_swagger
npm test
```

Valida automáticamente:
- Inyección del Bearer Token en el modal de autorización de Swagger.
- Ejecución en vivo de `GET /api/pedido-items/{pedidoItem}` verificando el contrato de adicionales.
- Ejecución de `GET /api/pagos/{id}` verificando el campo `referencia`.
- Ejecución de `GET /api/pedidos` verificando la respuesta estructurada de pedidos.
- Generación de capturas de pantalla de evidencia en `e2e_swagger/screenshots/`.

---

## 8. Instalación y Configuración Local

Seguir estos pasos para inicializar el backend en un entorno de desarrollo local:

### 1. Clonar el repositorio
```bash
git clone https://github.com/benjavaldez62/gestion-delivery-backend.git
cd gestion-delivery-backend
git checkout dev
```

### 2. Instalar dependencias PHP
```bash
composer install
```

### 3. Configurar variables de entorno
```bash
cp .env.example .env
```
Editar el archivo `.env` configurando la base de datos y la URL del frontend:
```env
APP_URL=http://localhost:8000
FRONTEND_URL=http://localhost:4200

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=delivery_db
DB_USERNAME=root
DB_PASSWORD=
```

### 4. Generar clave de aplicación
```bash
php artisan key:generate
```

### 5. Ejecutar migraciones y seeders
Crea el esquema relacional e inserta los datos iniciales de prueba (roles, usuarios, categorías, productos y adicionales):
```bash
php artisan migrate --seed
```

### 6. Iniciar el servidor de desarrollo
```bash
php artisan serve --port=8000
```
La API estará disponible en `http://127.0.0.1:8000` y Swagger UI en `http://127.0.0.1:8000/api/documentation`.

---

## 9. Convenciones de Ingeniería y Flujo Git

- **Rama Base:** Todas las ramas de características y fixes parten y apuntan hacia `dev`.
- **Formato de Commits:** [Conventional Commits](https://www.conventionalcommits.org/) obligatorio (`feat:`, `fix:`, `docs:`, `test:`, `refactor:`, `chore:`).
- **Idioma del Repositorio:** Todos los comentarios de código, docblocks, descripciones de PRs y cuerpos de commits deben redactarse estrictamente en español neutro/profesional.
- **Sin Atribuciones Automáticas:** No incluir líneas `Co-Authored-By` ni firmas automáticas de IA.
- **Protocolo de PRs Stackeados:** Al agrupar múltiples cambios en un release bundle, se mantiene 1 PR individual por tarea, apuntando luego a la rama bundle para permitir revisión y merge secuencial ordenado.

---

## 10. Equipo de Desarrollo (Grupo 2)

Proyecto desarrollado para la cátedra **Programación IV**:

- **Valdez, Benjamín Ezequiel**
- **Alanis, Fabricio**
- **Berra, Agustina Inés**
- **Berra Panelo, Florencia María**

# Sistema de Gestion de Delivery - Backend

Una API REST construida con **Laravel 12** para la gestion integral de pedidos de delivery en establecimientos gastronomicos. Este proyecto conforma el **Backend** del sistema, separando la logica de negocio y el acceso a la base de datos de la capa de presentacion (Frontend).

---

## Descripcion General

El Sistema de Gestion de Delivery tiene como objetivo permitir a los clientes explorar el menu, realizar pedidos y consultar su estado en tiempo real. Simultaneamente, el personal del local cuenta con herramientas para coordinar la preparacion, asignacion y distribucion de las entregas.

### Funcionalidades Principales

- **Autenticacion y Control de Acceso**: Registro e inicio de sesion del personal. Integracion planificada con Google (OAuth).
- **Roles y Permisos**:
  - **Cliente**: Visualizacion de menu publico, carrito de compras y seguimiento de ordenes.
  - **SuperAdministrador / Administrador**: Gestion de personal (CRUD), gestion del menu y operaciones globales.
  - **Cocinero**: Visualizacion de pedidos asignados y actualizacion de su estado de preparacion.
  - **Repartidor**: Hojas de ruta de distribucion y confirmacion de entrega.
- **Gestion de Ordenes**: Flujo completo de estados del pedido (Pendiente, En Preparacion, Listo, En Camino, Entregado, Cancelado).
- **Integraciones Avanzadas**: Notificaciones automaticas por Telegram a los clientes, encuestas de satisfaccion y actualizaciones en tiempo real.

---

## Stack Tecnologico

| Tecnologia | Version | Uso |
|---|---|---|
| PHP | ^8.2 | Lenguaje del servidor |
| Laravel | ^12.0 | Framework principal |
| Laravel Sanctum | ^4.0 | Autenticacion basada en tokens |
| l5-swagger | ^11.1 | Documentacion OpenAPI / Swagger UI |
| MySQL / MariaDB | 8.x+ / 10.x+ | Base de datos relacional |
| Composer | 2.x | Gestor de dependencias PHP |

---

## Estructura del Proyecto

```
app/
├── Http/
│   └── Controllers/       # Controladores de la API
│       ├── RoleController.php
│       ├── CategoriaController.php
│       ├── ProductoController.php
│       ├── MetodoPagoController.php
│       ├── EstadoPagosController.php
│       ├── PedidoController.php
│       ├── PedidoItemController.php
│       ├── PagoController.php
│       ├── ComprobanteController.php
│       └── AuthController.php
├── Models/                # Modelos Eloquent
│   ├── User.php
│   ├── Role.php
│   ├── Categoria.php
│   ├── Producto.php
│   ├── Cliente.php
│   ├── Pedido.php
│   ├── PedidoItem.php
│   ├── Adicional.php
│   ├── EstadoPedido.php
│   ├── Pago.php
│   ├── MetodoPago.php
│   ├── EstadoPagos.php
│   └── Comprobante.php
└── Swagger/
    └── OpenApi.php        # Configuracion base de Swagger
database/
├── migrations/            # Migraciones de la base de datos
└── seeders/               # Datos iniciales (roles, usuarios, categorias, productos)
routes/
└── api.php                # Definicion de rutas de la API
```

---

## Endpoints de la API

### Roles (`/api/roles`)

| Metodo | Ruta | Descripcion |
|---|---|---|
| GET | `/api/roles` | Listar todos los roles |
| POST | `/api/roles` | Crear un nuevo rol |
| GET | `/api/roles/{id}` | Obtener un rol por ID |
| PUT | `/api/roles/{id}` | Actualizar un rol |
| DELETE | `/api/roles/{id}` | Eliminar un rol |
| PUT | `/api/roles/{id}/restore` | Restaurar un rol eliminado |

### Categorias (`/api/categorias`)

| Metodo | Ruta | Descripcion |
|---|---|---|
| GET | `/api/categorias` | Listar todas las categorias |
| POST | `/api/categorias` | Crear una nueva categoria |
| GET | `/api/categorias/{id}` | Obtener una categoria por ID |
| PUT | `/api/categorias/{id}` | Actualizar una categoria |
| DELETE | `/api/categorias/{id}` | Eliminar una categoria |
| PUT | `/api/categorias/{id}/restore` | Restaurar una categoria eliminada |

### Productos (`/api/productos`)

| Metodo | Ruta | Descripcion |
|---|---|---|
| GET | `/api/productos` | Listar todos los productos |
| POST | `/api/productos` | Crear un nuevo producto |
| GET | `/api/productos/{id}` | Obtener un producto por ID |
| PUT | `/api/productos/{id}` | Actualizar un producto |
| DELETE | `/api/productos/{id}` | Eliminar un producto |
| PUT | `/api/productos/{id}/restore` | Restaurar un producto eliminado |

### Metodos de Pago (`/api/metodos-pago`)

| Metodo | Ruta | Descripcion |
|---|---|---|
| GET | `/api/metodos-pago` | Listar todos los metodos de pago |
| POST | `/api/metodos-pago` | Crear un nuevo metodo de pago |
| GET | `/api/metodos-pago/{id}` | Obtener un metodo de pago por ID |
| PUT | `/api/metodos-pago/{id}` | Actualizar un metodo de pago |
| DELETE | `/api/metodos-pago/{id}` | Eliminar un metodo de pago |
| PUT | `/api/metodos-pago/{id}/restore` | Restaurar un metodo de pago eliminado |

### Estados de Pago (`/api/estado-pagos`)

| Metodo | Ruta | Descripcion |
|---|---|---|
| GET | `/api/estado-pagos` | Listar todos los estados de pago |
| POST | `/api/estado-pagos` | Crear un nuevo estado de pago |
| GET | `/api/estado-pagos/{id}` | Obtener un estado de pago por ID |
| PUT | `/api/estado-pagos/{id}` | Actualizar un estado de pago |
| DELETE | `/api/estado-pagos/{id}` | Eliminar un estado de pago |
| PUT | `/api/estado-pagos/{id}/restore` | Restaurar un estado de pago eliminado |

### Autenticacion

| Metodo | Ruta | Descripcion |
|---|---|---|
| GET | `/api/user` | Obtener datos del usuario autenticado (requiere token Sanctum) |

---

## Documentacion Interactiva (Swagger)

La API cuenta con documentacion interactiva generada con **Swagger UI** mediante `l5-swagger`. Una vez levantado el servidor, se puede acceder desde:

```
http://127.0.0.1:8000/api/documentation
```

Para regenerar la documentacion tras modificar las anotaciones OpenAPI:

```bash
php artisan l5-swagger:generate
```

---

## Requisitos Previos

- **PHP**: 8.2 o superior
- **Composer**: Para la gestion de dependencias PHP
- **MySQL/MariaDB**: Sistema de base de datos relacional
- **Git**: Control de versiones

---

## Instalacion y Configuracion

Seguir estos pasos para levantar el entorno de desarrollo del backend localmente:

### 1. Clonar el Repositorio

```bash
git clone https://github.com/benjavaldez62/gestion-delivery-backend.git
cd gestion-delivery-backend
git checkout dev
```

### 2. Instalar Dependencias

```bash
composer install
```

### 3. Configurar Variables de Entorno

Copiar el archivo de ejemplo para crear la configuracion local:

```bash
cp .env.example .env
```

Abrir el archivo `.env` y configurar la conexion a la base de datos:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=delivery_db
DB_USERNAME=tu_usuario
DB_PASSWORD=tu_contraseña
```

### 4. Generar la Clave de la Aplicacion

```bash
php artisan key:generate
```

### 5. Ejecutar Migraciones y Seeders

Crear la estructura de la base de datos y cargar los datos iniciales (roles, usuarios de prueba, categorias y productos):

```bash
php artisan migrate --seed
```

Los seeders incluidos son:
- `RoleSeeder`: Crea los roles del sistema (Administrador, Cocinero, Repartidor, etc.).
- `UserSeeder`: Crea usuarios de prueba para cada rol.
- `CategoriaSeeder`: Carga categorias de ejemplo para el menu.
- `ProductoSeeder`: Carga productos de ejemplo asociados a las categorias.

Si solo se necesita la estructura sin datos:

```bash
php artisan migrate
```

### 6. Levantar el Servidor de Desarrollo

```bash
php artisan serve
```

El servidor estara disponible en `http://localhost:8000`.

---

## Flujo de Trabajo (Git)

El equipo trabaja bajo el flujo documentado en `.agents/rules/git-workflow.md`:
- **Rama base de desarrollo**: `dev`
- **Formato de ramas**: `{nombre}/{seccion}/{alcance}`
  - Ejemplos: `benja/feature/auth-google`, `fabricio/fix/calculo-total`
- **Pull Requests**: Siempre dirigidos hacia `dev` (salvo hotfixes a `main`), con Conventional Commits y sin emojis en la descripcion.
- **Releases a produccion (`main`)**: Gestionados exclusivamente por el Team Leader (Benja) al validar entregas en `dev`.

---

## Equipo (Grupo 2)

- Valdez, Benjamin Ezequiel
- Alanis, Fabricio
- Berra, Agustina Ines
- Berra Panelo, Florencia Maria

Materia: **Programacion IV**

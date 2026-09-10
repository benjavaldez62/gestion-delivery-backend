# NextERP - API de Gestión de Ventas

Una API REST robusta construida con **Laravel 12** para la gestión integral de ventas, inventario, clientes y pagos. Sistema ERP diseñado para facilitar operaciones comerciales en tiempo real.

## 🚀 Descripción General

NextERP es un sistema de gestión empresarial (ERP) especializado en la administración de ventas. Proporciona una API completa para:

- **Gestión de Ventas**: Crear, actualizar y consultar órdenes de venta
- **Inventario**: Controlar stock de productos y movimientos de inventario
- **Clientes**: Administrar información de clientes y su historial
- **Pagos**: Registrar y gestionar pagos con diferentes métodos
- **Productos**: Catálogo de productos con categorías y precios
- **Usuarios y Roles**: Control de acceso basado en roles

## 📋 Requisitos Previos

- **PHP**: 8.2 o superior
- **Composer**: Para gestión de dependencias PHP
- **Node.js**: 18+ (para compilación de assets)
- **MySQL/MariaDB**: Sistema de base de datos
- **Git**: Control de versiones

## 🔧 Instalación y Configuración

### 1. Clonar el Repositorio

```bash
git clone <url-del-repositorio>
cd admin
```

### 2. Instalación Automática

Ejecuta el script de configuración:

```bash
composer run setup
```

Este comando automatiza:

- Instalación de dependencias PHP
- Generación de archivo `.env`
- Generación de clave de aplicación
- Ejecución de migraciones
- Instalación de dependencias Node.js
- Compilación de assets

### 3. Configuración Manual (Alternativa)

Si prefieres configurar manualmente:

```bash
# Instalar dependencias PHP
composer install

# Copiar archivo de entorno
cp .env.example .env

# Generar clave de aplicación
php artisan key:generate

# Crear base de datos y ejecutar migraciones
php artisan migrate

# Instalar dependencias Frontend
npm install

# Compilar assets
npm run build
```

### 4. Configurar Base de Datos

Edita el archivo `.env` con tus credenciales:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=tubase
DB_USERNAME=root
DB_PASSWORD=
```

## 📁 Estructura del Proyecto

```
admin/
├── app/
│   ├── Http/
│   │   ├── Controllers/          # Controladores API
│   │   │   ├── VentaController.php
│   │   │   ├── ProductoController.php
│   │   │   ├── ClienteController.php
│   │   │   ├── PagoController.php
│   │   │   └── ...
│   │   └── Middleware/           # Middleware personalizado
│   ├── Models/                    # Modelos Eloquent
│   │   ├── Venta.php
│   │   ├── Producto.php
│   │   ├── Cliente.php
│   │   ├── Pago.php
│   │   ├── User.php
│   │   └── ...
│   └── Providers/                 # Service Providers
├── routes/
│   ├── api.php                    # Rutas de API
│   ├── web.php                    # Rutas web
│   └── console.php                # Comandos de consola
├── database/
│   ├── migrations/                # Migraciones de BD
│   ├── factories/                 # Factories para testing
│   └── seeders/                   # Seeders para datos iniciales
├── tests/                         # Tests automatizados (Pest)
├── bootstrap/
│   ├── app.php                    # Configuración principal
│   └── providers.php              # Proveedores registrados
├── config/                        # Archivos de configuración
└── storage/                       # Almacenamiento de archivos
```

## 📊 Modelos y Relaciones

### Estructura de Datos Principal

#### **User** (Usuario)

- Autenticación con tokens Sanctum
- Relación con Role
- Asociado a Ventas y Movimientos de Stock

```
User
├── has many → Venta
├── has many → MovimientoStock
└── belongs to → Role
```

#### **Cliente**

- Información de contacto completa
- Historial de ventas

```
Cliente
└── has many → Venta
```

#### **Producto**

- Gestión de precios y stock
- Categorización
- Control de stock mínimo

```
Producto
├── belongs to → Categoria
├── has many → VentaDetalle
└── has many → MovimientoStock
```

#### **Venta**

- Registro completo de transacciones
- Cálculo de totales
- Estado configurable

```
Venta
├── belongs to → Cliente
├── belongs to → User
├── belongs to → EstadoVenta
├── has many → VentaDetalle
└── has many → Pago
```

#### **VentaDetalle**

- Líneas individuales de venta
- Referencia a producto y cantidad

```
VentaDetalle
├── belongs to → Venta
└── belongs to → Producto
```

#### **Pago**

- Registro de pagos
- Métodos de pago configurables
- Estados de pago

```
Pago
├── belongs to → Venta
├── belongs to → MetodoPago
└── belongs to → EstadoPago
```

#### **MovimientoStock**

- Auditoría de cambios de inventario
- Tipos de movimiento (entrada/salida)

```
MovimientoStock
├── belongs to → Producto
├── belongs to → User
└── belongs to → TipoMovimientoStock
```

#### **Categoría**

- Clasificación de productos

```
Categoria
└── has many → Producto
```

#### **Role**

- Roles de usuario

```
Role
└── has many → User
```

#### **EstadoVenta** y **EstadoPago**

- Configuración de estados

#### **MetodoPago** y **TipoMovimientoStock**

- Catálogos configurables

## 🔑 Autenticación

La API utiliza **Laravel Sanctum** para autenticación token-based:

### Obtener Token

```bash
POST /api/login
Content-Type: application/json

{
  "email": "user@example.com",
  "password": "password"
}
```

### Usar Token en Requests

```bash
GET /api/user
Authorization: Bearer <token>
```

### Logout

```bash
POST /api/logout
Authorization: Bearer <token>
```

## 🌐 Endpoints API

### Estructura REST

La API sigue convenciones RESTful estándar:

| Método | Endpoint           | Descripción              |
| ------ | ------------------ | ------------------------ |
| GET    | `/api/ventas`      | Listar todas las ventas  |
| POST   | `/api/ventas`      | Crear nueva venta        |
| GET    | `/api/ventas/{id}` | Obtener venta específica |
| PUT    | `/api/ventas/{id}` | Actualizar venta         |
| DELETE | `/api/ventas/{id}` | Eliminar venta           |

### Ejemplos de Endpoints por Recurso

#### Ventas

```
GET    /api/ventas
POST   /api/ventas
GET    /api/ventas/{id}
PUT    /api/ventas/{id}
DELETE /api/ventas/{id}
```

#### Productos

```
GET    /api/productos
POST   /api/productos
GET    /api/productos/{id}
PUT    /api/productos/{id}
DELETE /api/productos/{id}
```

#### Clientes

```
GET    /api/clientes
POST   /api/clientes
GET    /api/clientes/{id}
PUT    /api/clientes/{id}
DELETE /api/clientes/{id}
```

#### Pagos

```
GET    /api/pagos
POST   /api/pagos
GET    /api/pagos/{id}
PUT    /api/pagos/{id}
DELETE /api/pagos/{id}
```

#### Categorías

```
GET    /api/categorias
POST   /api/categorias
GET    /api/categorias/{id}
PUT    /api/categorias/{id}
DELETE /api/categorias/{id}
```

#### Movimientos de Stock

```
GET    /api/movimientos-stock
POST   /api/movimientos-stock
GET    /api/movimientos-stock/{id}
DELETE /api/movimientos-stock/{id}
```

#### Estados y Métodos

```
GET    /api/estados-venta
GET    /api/estados-pago
GET    /api/metodos-pago
GET    /api/tipos-movimiento
```

#### Roles

```
GET    /api/roles
POST   /api/roles
GET    /api/roles/{id}
PUT    /api/roles/{id}
DELETE /api/roles/{id}
```

## 📝 Ejemplo de Uso

### Crear una Venta

```bash
POST /api/ventas
Authorization: Bearer <token>
Content-Type: application/json

{
  "cliente_id": 1,
  "fecha": "2026-09-08",
  "estado_venta_id": 1,
  "detalles": [
    {
      "producto_id": 5,
      "cantidad": 2,
      "precio_unitario": 50.00
    },
    {
      "producto_id": 8,
      "cantidad": 1,
      "precio_unitario": 100.00
    }
  ],
  "observaciones": "Entrega rápida"
}
```

### Registrar Pago

```bash
POST /api/pagos
Authorization: Bearer <token>
Content-Type: application/json

{
  "venta_id": 10,
  "monto": 200.00,
  "metodo_pago_id": 1,
  "estado_pago_id": 1,
  "referencia": "TRX123456"
}
```

### Crear Movimiento de Stock

```bash
POST /api/movimientos-stock
Authorization: Bearer <token>
Content-Type: application/json

{
  "producto_id": 5,
  "tipo_movimiento_id": 1,
  "cantidad": 50,
  "observaciones": "Entrada de mercadería"
}
```

## 🏃 Ejecutar la Aplicación

### Entorno de Desarrollo

```bash
# Terminal 1: Compilar assets en tiempo real
npm run dev

# Terminal 2: Ejecutar servidor Laravel
php artisan serve
```

La API estará disponible en: `http://localhost:8000/api`

### Entorno de Producción

```bash
# Compilar assets
npm run build

# Ejecutar servidor
php artisan serve --host=0.0.0.0 --port=8000
```

## 🧪 Testing

El proyecto utiliza **Pest** como framework de testing.

### Ejecutar Tests

```bash
# Todos los tests
php artisan test

# Tests específicos
php artisan test --filter=VentaTest

# Con salida detallada
php artisan test --verbose
```

### Escribir Tests

Los tests se encuentran en `/tests`:

```
tests/
├── Feature/          # Tests de funcionalidad
└── Unit/             # Tests unitarios
```

Ejemplo de test:

```php
test('se puede crear una venta', function () {
    $cliente = Cliente::factory()->create();

    $response = $this->actingAs(User::factory()->create())
        ->postJson('/api/ventas', [
            'cliente_id' => $cliente->id,
            'fecha' => now(),
            'estado_venta_id' => 1,
        ]);

    $response->assertStatus(201);
});
```

## 📦 Dependencias Principales

- **Laravel Framework 12.x**: Framework web
- **Laravel Sanctum 4.x**: Autenticación API
- **Pest 3.x**: Framework de testing
- **Laravel Pint 1.x**: Code formatter
- **FakerPHP 1.x**: Generación de datos de prueba

Ver [composer.json](composer.json) para la lista completa.

## ⚙️ Configuración Importante

### Archivo `.env`

```env
# Base de Datos
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=NextERP
DB_USERNAME=root
DB_PASSWORD=

# App
APP_NAME="NextERP"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

# Session
SESSION_DRIVER=file
```

### Middleware

Los middlewares están configurados en `bootstrap/app.php`. Incluye:

- CORS para solicitudes del frontend
- Rate limiting
- Throttling

## 🔐 Consideraciones de Seguridad

- Todos los endpoints requieren autenticación con token Sanctum
- Validación de datos en todas las peticiones
- Protección contra CSRF
- Sanitización de inputs
- Control de acceso basado en roles

## 🐛 Debugging

### Habilitar Modo Debug

```env
APP_DEBUG=true
```

### Usar Tinker

```bash
php artisan tinker
> User::all()
> Cliente::count()
```

### Ver Logs

```bash
# En tiempo real
tail -f storage/logs/laravel.log

# O usar Laravel Pail
php artisan pail
```

## 📚 Recursos Útiles

- [Documentación oficial de Laravel](https://laravel.com/docs)
- [Laravel Sanctum](https://laravel.com/docs/sanctum)
- [Pest Testing](https://pestphp.com)
- [Eloquent ORM](https://laravel.com/docs/eloquent)

## 🤝 Contribuciones

Las contribuciones son bienvenidas. Por favor:

1. Crea una rama para tu feature: `git checkout -b feature/nueva-funcionalidad`
2. Commit tus cambios: `git commit -m "Agregar nueva funcionalidad"`
3. Push a la rama: `git push origin feature/nueva-funcionalidad`
4. Abre un Pull Request

## 📄 Licencia

Este proyecto está bajo licencia MIT. Ver el archivo [LICENSE](LICENSE) para más detalles.

## 👥 Soporte

Para reportar bugs o solicitar features, por favor abre un issue en el repositorio.

---

**Última actualización**: Septiembre 2026  
**Versión de Laravel**: 12.x  
**Versión de PHP**: 8.2+

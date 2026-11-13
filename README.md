# Sistema de Gestión de Delivery — La Posta (Backend API)

API REST desarrollada con **Laravel** (PHP 8.2+) para la gestión integral de pedidos, catálogo gastronómico, control de cocina, asignación de repartos y liquidación de pagos en establecimientos gastronómicos. Este repositorio conforma la capa de backend desacoplada del cliente Single Page Application (Angular 22).

---

## 1. Descripción General y Arquitectura

El sistema centraliza las operaciones del restaurante "La Posta", proporcionando una arquitectura RESTful segura, validada y documentada con OpenAPI / Swagger. La plataforma implementa un control de acceso basado en roles (RBAC) mediante tokens de portador (Bearer Tokens) con **Laravel Sanctum**.

### 1.1. Invariantes de Dominio y Reglas Arquitectónicas

1. **Ciclo de Vida del Pedido e Inmutabilidad en Cocina:**
   - Flujo de estados: `Pre-pedido` ➔ `Sin Asignar` ➔ `Pendiente` ➔ `En Preparación` ➔ `Listo para entregar` ➔ `En Camino` ➔ `Entregado` (o `Cancelado`).
   - **Pre-pedido y Confirmación por Telegram:** El pedido nace como `Pre-pedido` durante el checkout público. En esta etapa permanece aislado de los paneles operativos y KPIs del dashboard administrativo. Cuando el cliente envía al bot configurado un mensaje con el número del pedido, la orden transiciona a `Sin Asignar`, ingresando formalmente a la cola de pedidos pendientes de cocina y asignación de personal.
   - **Regla estricta:** Únicamente los pedidos en estado `Pre-pedido` o `Pendiente` admiten agregación, edición o eliminación de ítems (`PedidoItem`). Una vez iniciada la preparación en cocina (`En Preparación` en adelante), cualquier intento de modificación es rechazado con código `400 Bad Request`.
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
| **Testing** | Pest PHP | `^3.8` | Suite de pruebas unitarias y de integración |
| **Dependencias** | Composer | `2.x` | Gestor de paquetes PHP |

---

## 3. Estructura del Repositorio

```
backend/
├── app/
│   ├── Console/Commands/         # Comandos Artisan personalizados
│   │   ├── TelegramPollCommand.php           # Long polling worker en tiempo real
│   │   ├── TelegramTestMessageCommand.php    # Test de credenciales y envío a Telegram
│   │   └── TelegramSimularWebhookCommand.php # Simulador de webhook para tests locales
│   ├── Http/
│   │   ├── Controllers/          # Controladores RESTful con atributos OpenAPI
│   │   │   ├── AuthController.php
│   │   │   ├── CategoriaController.php
│   │   │   ├── ProductoController.php
│   │   │   ├── AdicionalController.php
│   │   │   ├── PedidoController.php
│   │   │   ├── PedidoItemController.php
│   │   │   ├── EstadoPedidoController.php
│   │   │   ├── BorradorCheckoutController.php
│   │   │   ├── TelegramWebhookController.php
│   │   │   ├── DashboardController.php
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
│   │   │   ├── UpdateEstadoPedidoRequest.php
│   │   │   ├── ConfirmarPedidoTelegramRequest.php
│   │   │   └── BorradorCheckoutRequest.php
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
│   ├── Services/                 # Capa de servicios desacoplados
│   │   ├── TelegramService.php   # Despacho de mensajes y notificaciones push
│   │   └── DashboardService.php  # Métricas operativas, KPIs y alertas de pedidos
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

#### Recuperación de contraseña

- `POST /api/forgot-password` recibe `email`. Para el dominio exacto `test.com` (sin distinguir mayúsculas), responde con `action: contactar_administrador` sin enviar correo ni notificación. Para otras cuentas, responde 404 si no existe, 403 si está inactiva, 429 ante limitación y 503 si falla el correo; responde `action: enlace_enviado` solo cuando SMTP acepta el mensaje.
- `POST /api/reset-password` recibe `email`, `token`, `password` y `password_confirmation`; cambia la contraseña local de una cuenta activa cuando el enlace es válido. Las cuentas `test.com` no pueden usar este mecanismo, incluso con un token anterior. La contraseña de Google no cambia.

Configurar `FRONTEND_URL` con la URL pública del frontend y un transporte de correo
real mediante las variables `MAIL_*`. En producción usar HTTPS y no guardar
credenciales en el repositorio. Configurar `MAIL_MAILER=smtp`, `MAIL_HOST`,
`MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_SCHEME` y
`MAIL_FROM_ADDRESS` según el proveedor. `MAIL_URL`, si se usa, prevalece sobre
los parámetros del transporte. `MAIL_MAILER=log` y `array` no envían correo y
la recuperación los rechaza. La aceptación de SMTP no garantiza entrega en la bandeja.

El enlace apunta a `/restablecer-contrasena` en el frontend y coloca el token y
correo en el fragmento de la URL. El token se almacena con hash, vence según
`auth.passwords.users.expire` (60 minutos por defecto) y se invalida al usarlo.
Al restablecer se revocan los tokens Sanctum existentes. La contraseña debe tener
al menos 8 caracteres, como máximo 72 bytes y no contener caracteres nulos. Cada
endpoint limita a 5 solicitudes por minuto por IP, además del límite del broker.
Los fallos de envío se registran sin contraseñas, tokens ni detalles del proveedor.
Las respuestas diferenciadas permiten enumerar cuentas y difieren del criterio
actual de privacidad de SD-150; coordinar este cambio con el equipo.

| Método | Ruta | Acceso | Descripción |
|:---:|:---|:---:|:---|
| `POST` | `/api/login` | Público | Inicia sesión con email y contraseña. Retorna un Bearer Token Sanctum. |
| `POST` | `/api/logout` | `auth:sanctum` | Revoca el Bearer Token del usuario activo. |
| `GET` | `/api/user` | `auth:sanctum` | Obtiene el perfil del usuario autenticado. |

### 4.2. Catálogo Gastronómico (Lectura pública / Mutación Staff)

| Método | Ruta | Acceso | Descripción |
|:---:|:---|:---:|:---|
| `GET` | `/api/productos` | Público | Listar productos activos con categoría vinculada. Admite filtros opcionales por query params: `?categoria_id={id}` y búsqueda textual por nombre con `?q={termino}`. |
| `GET` | `/api/productos/{id}` | Público | Detalle de un producto puntual. |
| `POST` | `/api/productos` | `auth:sanctum` | Crear producto. Valida duplicados y asigna estado activo. |
| `PUT` | `/api/productos/{id}` | `auth:sanctum` | Actualizar atributos de un producto. |
| `DELETE` | `/api/productos/{id}` | `auth:sanctum` | Baja lógica de producto (`activo = false`, `deleted_at`). |
| `PUT` | `/api/productos/{id}/restore` | `auth:sanctum` | Restaurar un producto dado de baja. |
| `GET` | `/api/productos-desactivados` | `auth:sanctum` | Listar productos desactivados/eliminados lógicamente. |
| `GET` | `/api/categorias` | Público | Listar categorías activas. |
| `GET` | `/api/categorias/{id}` | Público | Detalle de una categoría puntual. |
| `GET` | `/api/categorias/{categoria}/adicionales` | Público | Listar adicionales disponibles asociados a una categoría gastronómica específica. |
| `POST` | `/api/categorias` | `auth:sanctum` | Crear nueva categoría. |
| `PUT` | `/api/categorias/{id}` | `auth:sanctum` | Actualizar categoría. |
| `DELETE` | `/api/categorias/{id}` | `auth:sanctum` | Baja lógica de categoría. |
| `PUT` | `/api/categorias/{id}/restore` | `auth:sanctum` | Restaurar categoría eliminada. |
| `GET` | `/api/categorias-desactivadas` | `auth:sanctum` | Listar categorías desactivadas. |
| `GET` | `/api/adicionales` | Público | Listar adicionales disponibles (toppings, guarniciones, etc.). Admite filtro opcional por categoría `?categoria_id={id}`. |
| `GET` | `/api/adicionales/{id}` | Público | Detalle de un adicional puntual exponiendo `categoria_id`. |
| `POST` | `/api/adicionales` | `auth:sanctum` | Crear nuevo adicional asociando opcionalmente `categoria_id`. |
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
| `POST` | `/api/pedidos/{pedido}/items` | `auth:sanctum` | Agregar ítem al pedido. Admite array opcional `adicionales` (`[{"id": 1, "cantidad": 1}]`), calcula el subtotal `(precio + sum(adicionales)) * cantidad`, persiste el snapshot histórico de precios en la tabla pivote y actualiza el total del pedido. Rechaza con 400 si el pedido no está en estado `Pendiente`. Rechaza con 422 si el producto o adicional está inactivo. |
| `GET` | `/api/pedido-items/{pedidoItem}` | `auth:sanctum` | Detalle de un ítem exponiendo `id`, producto (con `categoria_id`), cantidad, subtotal y array de adicionales. |
| `PUT` | `/api/pedido-items/{pedidoItem}` | `auth:sanctum` | Modificar cantidad o adicionales de un ítem recalculando subtotales y total del pedido. Rechaza con 400 si ya se inició la cocción. |
| `DELETE` | `/api/pedido-items/{pedidoItem}` | `auth:sanctum` | Quitar ítem del pedido. Rechaza con 400 si el pedido no es editable o si es el único ítem. |

### 4.4. Checkout Público y Webhook de Telegram (`/api/checkout`, `/api/telegram`)

| Método | Ruta | Acceso | Descripción |
|:---:|:---|:---:|:---|
| `POST` | `/api/checkout/borradores` | Público | Registra cliente por teléfono y persiste de forma atómica un `Pedido` en estado inicial `Pre-pedido` junto con sus líneas (`PedidoItem`) y adicionales, calculando precios, subtotales y costo de envío fijo ($1500) en el servidor. Retorna `pedido_id` y `estado: Pre-pedido` para continuar la confirmación en Telegram. Rate limit: 10 req/min. |
| `POST` | `/api/pedidos/{id}/confirmar-telegram` | Público | Endpoint consumido por la integración de Telegram para confirmar una orden en estado `Pre-pedido`, actualizando nombre, username, chat ID y domicilio del cliente, y transicionando el pedido a `Sin Asignar`. |
| `POST` | `/api/telegram/webhook` | Público | Webhook oficial para recibir eventos de Telegram Bot API (`Update`). Procesa `/start <id>`, comandos con número de pedido (`Pedido #<id>`), consulta de estado `/estado` y respuestas interactivas con anti-spam (cooldown de 2 horas). |

### 4.5. Dashboard y Métricas Operativas (`/api/dashboard`)

| Método | Ruta | Acceso | Descripción |
|:---:|:---|:---:|:---|
| `GET` | `/api/dashboard/general` | `Administrador` | Métricas consolidadas en tiempo real: KPIs de facturación, volumen de pedidos, distribución por método de pago y desglose por estado (excluyendo automáticamente órdenes en estado `Pre-pedido`). |
| `GET` | `/api/dashboard/pedidos-sin-asignar` | `Administrador` | Alerta operativa de pedidos en estado `Sin Asignar` que requieren intervención y asignación inmediata de personal de cocina o reparto. |

### 4.6. Estados de Pedido (`/api/estado-pedidos`)

| Método | Ruta | Acceso | Descripción |
|:---:|:---|:---:|:---|
| `GET` | `/api/estado-pedidos` | `auth:sanctum` | Listar todos los estados de pedido activos. |
| `POST` | `/api/estado-pedidos` | `auth:sanctum` | Crear nuevo estado de pedido validando unicidad. |
| `GET` | `/api/estado-pedidos/{id}` | `auth:sanctum` | Detalle de un estado de pedido. |
| `PUT` | `/api/estado-pedidos/{id}` | `auth:sanctum` | Actualización parcial o total de nombre/descripción. |
| `DELETE` | `/api/estado-pedidos/{id}` | `auth:sanctum` | Baja lógica. Retorna `409 Conflict` si existen pedidos asociados. |
| `PUT` | `/api/estado-pedidos/{id}/restore` | `auth:sanctum` | Restaurar un estado dado de baja. |
| `GET` | `/api/estado-pedidos-desactivados` | `auth:sanctum` | Listar estados de pedido desactivados. |

### 4.7. Finanzas, Pagos y Comprobantes

| Método | Ruta | Acceso | Descripción |
|:---:|:---|:---:|:---|
| `GET` | `/api/pagos` | `auth:sanctum` | Listar pagos registrados con método, estado y comprobantes. |
| `POST` | `/api/pagos` | `auth:sanctum` | Registrar pago admitiendo campo opcional `referencia`. |
| `GET` | `/api/pagos/{id}` | `auth:sanctum` | Detalle del pago exponiendo `referencia` y array de comprobantes. |
| `PUT` | `/api/pagos/{id}` | `auth:sanctum` | Modificar datos de pago (ej. actualizar referencia). |
| `DELETE` | `/api/pagos/{id}` | `auth:sanctum` | Baja lógica de pago. |
| `PUT` | `/api/pagos/{id}/restore` | `auth:sanctum` | Restaurar pago eliminado. |
| `GET` | `/api/metodos-pago` | `auth:sanctum` | Listar métodos de pago (Efectivo, Transferencia, MercadoPago, etc.) con soporte para relación inversa hacia pedidos (`pedidos(): HasMany`). |
| `POST` | `/api/metodos-pago` | `auth:sanctum` | Crear método de pago. |
| `GET` | `/api/metodos-pago/{id}` | `auth:sanctum` | Detalle de método de pago. |
| `PUT` | `/api/metodos-pago/{id}` | `auth:sanctum` | Actualizar método de pago. |
| `DELETE` | `/api/metodos-pago/{id}` | `auth:sanctum` | Baja lógica de método de pago. |
| `GET` | `/api/estado-pagos` | `auth:sanctum` | Listar estados de pago (Pendiente, Aprobado, Rechazado). |
| `GET` | `/api/comprobantes` | `auth:sanctum` | Listar comprobantes emitidos. |
| `POST` | `/api/comprobantes` | `auth:sanctum` | Asociar comprobante o factura a un pago. |

### 4.8. Administración General (Exclusivo Rol `Administrador`)

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

## 5. Integración con Telegram Bot

El sistema cuenta con una integración bidireccional con el bot de Telegram configurado en cada entorno. Esta capa actúa como canal conversacional para que el cliente confirme su orden tras el checkout público en la web y reciba notificaciones push a medida que el pedido transiciona en cocina y reparto.

Cada entorno debe configurar `TELEGRAM_BOT_USERNAME` sin `@` y `TELEGRAM_BOT_TOKEN` para el mismo bot. El endpoint público `GET /api/configuracion-publica` devuelve solo `telegram_bot_username`, nunca el token. Si el nombre falta o no es válido devuelve 503 y el frontend permite reintentar sin crear otro pedido. El nombre debe tener entre 5 y 32 caracteres ASCII alfanuméricos o guion bajo.

No guardar tokens en el repositorio ni compartir el `.env`. Al cambiar sus valores, ejecutar `php artisan config:clear` para descartar la caché y reiniciar los procesos de larga duración. La configuración no comprueba que el token pertenezca al nombre ni que el bot esté escuchando.

### 5.1. Arquitectura y Desglose de Componentes

| Componente / Capa | Archivo | Responsabilidad Principal |
| :--- | :--- | :--- |
| **Configuración y Credenciales** | `.env` / `.env.example` | Variables de entorno `TELEGRAM_BOT_TOKEN` y `TELEGRAM_BOT_USERNAME`. |
| **Migración de Base de Datos** | `database/migrations/2026_11_12_155340_add_telegram_fields_to_pedidos_table.php` | Incorpora `telegram_chat_id` (bigInteger nullable) y `telegram_username` (string nullable) en la tabla `pedidos`. |
| **Modelo Eloquent** | `app/Models/Pedido.php` | Atributos `$fillable` para persistencia de chat ID y username de Telegram. |
| **API Resource** | `app/Http/Resources/PedidoResource.php` | Exposición de `telegram_chat_id` y `telegram_username` en los contratos JSON de la API. |
| **Servicio de Salida (Push)** | `app/Services/TelegramService.php` | Despacho de mensajes HTML (`enviarMensaje`), soporte de teclados (`InlineKeyboardMarkup` y `ReplyKeyboardMarkup`) y notificaciones push reactivas ante cambios de estado (`notificarCambioEstado`). |
| **Controlador Webhook** | `app/Http/Controllers/TelegramWebhookController.php` | Procesamiento de updates de Telegram, comandos `/start <id>`, `Pedido #<id>`, consulta de estado `/estado`, cooldown anti-spam (2 horas en saludos) y botones interactivos. |
| **Controlador de Pedidos** | `app/Http/Controllers/PedidoController.php` | Endpoint `POST /api/pedidos/{id}/confirmar-telegram` y disparador de notificaciones push en asignación de personal (`asignarPersonal`) y cambio de estado (`update`). |
| **Checkout Público** | `app/Http/Controllers/BorradorCheckoutController.php` | Creación atómica de cliente, pedido en estado inicial `Pre-pedido` y desglose de ítems para derivación a Telegram. |
| **Form Request** | `app/Http/Requests/ConfirmarPedidoTelegramRequest.php` | Validación estricta de parámetros para la confirmación de la comanda desde el bot. |
| **Worker CLI (Long Polling)** | `app/Console/Commands/TelegramPollCommand.php` | `php artisan telegram:poll`: Escucha y procesa eventos de Telegram en tiempo real sin requerir túneles externos (ngrok o Cloudflare). |
| **Herramienta CLI de Test** | `app/Console/Commands/TelegramTestMessageCommand.php` | `php artisan telegram:test-message <chat_id>`: Verificación rápida de credenciales y envío directo al chat. |
| **Simulador de Webhook** | `app/Console/Commands/TelegramSimularWebhookCommand.php` | `php artisan telegram:simular-webhook <id>`: Simulación local de confirmación entrante para pruebas unitarias y e2e. |
| **Pruebas Automatizadas** | `tests/Feature/TelegramWebhookTest.php` | Suite Pest con cobertura de flujos reactivos, transiciones de estado, idempotencia y notificaciones push. |

### 5.2. Catálogo de Disparadores y Respuestas del Bot (Flujo Reactivo)

Gestionado en `TelegramWebhookController@handle` cuando el usuario envía un mensaje al bot configurado:

| # | Mensaje del Cliente | Condición en el Sistema | Respuesta del Bot |
| :---: | :--- | :--- | :--- |
| **1** | Saludo o texto cualquiera sin número de pedido (ej: `Hola`, `/start` solo). | No se detecta `/start <id>` ni `Pedido #<id>` (aplica cooldown anti-spam de 2 horas en saludo largo). | `👋 ¡Hola! Bienvenido a <b>La Posta Delivery</b>.<br>Para confirmar tu pedido, envianos el mensaje con el número de tu orden (por ejemplo: <code>Pedido #12</code> o hacé clic en el enlace desde la web).` |
| **2** | Número de pedido inexistente (ej: `Pedido #999` o `/start 999`). | No se encuentra registro del pedido en base de datos. | `⚠️ No pudimos encontrar el <b>Pedido #999</b>. Por favor verificá el número ingresado.` |
| **3** | Pedido ya confirmado o en cocina/camino (ej: reintento `Pedido #12`). | El pedido existe pero su estado es distinto de `Pre-pedido`. | `ℹ️ El <b>Pedido #12</b> ya se encuentra registrado con estado: <b>[NombreEstado]</b>.` |
| **4** | Mensaje de checkout web válido (ej: `/start 12` o texto con `Pedido #12`). | El pedido existe y su estado es **`Pre-pedido`** (transiciona a `Sin Asignar` y registra chat ID). | `🎉 <b>¡Tu Pedido #12 fue confirmado con éxito!</b><br><br>💰 Total: <b>$[monto_total]</b><br>Te iremos notificando por acá el avance de tu comida (en cocina, empaque y en viaje con el repartidor).` |
| **5** | Consulta de estado (`/estado` o botón interactivo). | Cliente con chat ID registrado en el sistema. | `📦 <b>Estado de tu último pedido:</b><br>Pedido #12: <b>[NombreEstado]</b>.` |

### 5.3. Catálogo de Notificaciones Push Proactivas (Cambios de Estado)

Disparadas automáticamente por `TelegramService@notificarCambioEstado` al modificarse el estado del pedido:

| # | Evento en el Sistema | Estado Resultante | Notificación Push enviada al Chat de Telegram |
| :---: | :--- | :--- | :--- |
| **1** | Admin asigna cocinero y/o repartidor en panel. | `Pendiente` | `👨‍🍳 <b>Pedido #[id]:</b> Personal asignado. En breve comenzará su preparación.` |
| **2** | Cocinero toma el pedido en el Kanban de Cocina (`/cocina`). | `En preparación` | `🔥 <b>¡Tu Pedido #[id] ya se está cocinando!</b><br>Nuestros cocineros lo están preparando con dedicación.` |
| **3** | Cocina finaliza y pasa el pedido a empaque. | `Listo para entregar` | `📦 <b>Pedido #[id] listo:</b> Ya está empacado esperando al repartidor.` |
| **4** | Repartidor retira el paquete e inicia recorrido (`/repartos`). | `En camino` | `🛵 <b>¡Tu Pedido #[id] va en camino!</b><br>El repartidor se dirige a tu domicilio.` |
| **5** | Repartidor efectúa la entrega al cliente en mano. | `Entregado` | `🎉 <b>¡Pedido #[id] entregado!</b><br>¡Que lo disfrutes mucho! Gracias por elegir La Posta.` |
| **6** | Cancelación administrativa o de personal. | `Cancelado` | `❌ <b>Pedido #[id] cancelado:</b> Tu pedido fue cancelado. Por favor comunicate con el restaurante si tenés dudas.` |
| **7** | Actualización de estado genérica. | *Cualquier otro* | `ℹ️ <b>Pedido #[id]:</b> El estado se actualizó a '[NombreEstado]'.` |

### 5.4. Modos de Ejecución y Comandos Artisan

- **Modo Desarrollo (Long Polling sin túneles):**
  Para desarrollo local sin necesidad de herramientas como ngrok o cloudflared, se ejecuta el worker de polling:
  ```bash
  php artisan telegram:poll
  ```
  Este comando elimina el webhook configurado en el bot antes de consultar la API de Telegram y despacha las actualizaciones directamente al controlador interno. Usarlo únicamente en un bot de prueba propio cuando no se necesite conservar ese webhook.

- **Comando de prueba de credenciales:**
  ```bash
  php artisan telegram:test-message <chat_id>
  ```

- **Simulador de webhook para pruebas automatizadas:**
  ```bash
  php artisan telegram:simular-webhook <id_pedido>
  ```

---

## 6. Integración Frontend y Configuración CORS

El backend cuenta con soporte nativo para peticiones de origen cruzado (CORS) configurado en `config/cors.php`:

- **Orígenes autorizados:** `http://localhost:4200`, `http://127.0.0.1:4200` y configurable mediante variable de entorno `FRONTEND_URL`.
- **Patrones regex permitidos:** Cualquier puerto local para desarrollo (`#^http://localhost:\d+$#` y `#^http://127\.0\.0\.1:\d+$#`).
- **Métodos admitidos:** `GET`, `POST`, `PUT`, `PATCH`, `DELETE`, `OPTIONS`.
- **Cabeceras permitidas:** `Authorization`, `Content-Type`, `Accept`, `X-Requested-With`, `X-XSRF-TOKEN`, `Origin`.
- **Credenciales:** `'supports_credentials' => true` habilitado bajo orígenes explícitos cumpliendo la especificación W3C.
- **Preflight Cache:** 86400 segundos (24 horas) para optimizar el tráfico de solicitudes `OPTIONS`.

---

## 7. Documentación Interactiva (Swagger UI)

La API cuenta con documentación OpenAPI 3.0 auto-generada. Con el servidor en ejecución, la consola interactiva está disponible en:

```
http://127.0.0.1:8000/api/documentation
```

> **Nota importante de origen (Same-Origin Policy):** Se debe acceder a Swagger UI utilizando estrictamente la dirección IP `http://127.0.0.1:8000/api/documentation` y **no** `http://localhost:8000/api/documentation`. Debido a que el servidor en la especificación OpenAPI está definido bajo `http://127.0.0.1:8000`, abrir la interfaz desde `localhost` provoca que el navegador trate las peticiones de prueba (*Try it out*) como solicitudes de origen cruzado (CORS) e interrumpa la conexión con `TypeError: NetworkError when attempting to fetch resource`.

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

## 8. Pruebas Automatizadas (Pest PHP)

El proyecto cuenta con una cobertura integral de pruebas unitarias y de integración desarrolladas con **Pest PHP** para validar contratos de API, reglas de negocio e integridad referencial (101 pruebas pasando, 546 aserciones):

```bash
php artisan test
```

### Desglose de Pruebas Principales
- `tests/Feature/AuthLoginTest.php`: Login, hash de contraseñas, validación de credenciales erróneas y tokens Sanctum.
- `tests/Feature/AuthLogoutTest.php`: Revocación del Bearer Token y rechazo de logout anónimo.
- `tests/Feature/TelegramWebhookTest.php`: Cobertura del webhook de Telegram, procesamiento de `/start <id>`, comandos `Pedido #<id>`, consulta `/estado`, persistencia de `telegram_chat_id`, transición atómica a `Sin Asignar` y despacho de notificaciones push de estado.
- `tests/Feature/DashboardTest.php`: Aislamiento de órdenes en `Pre-pedido` fuera de los KPIs de facturación y alertas de pedidos sin asignar en el dashboard.
- `tests/Feature/AdicionalPorCategoriaTest.php`: Relación de adicionales con categorías gastronómicas, endpoint `GET /api/categorias/{categoria}/adicionales`, filtro `?categoria_id` y exposición de `categoria_id` en resources.
- `tests/Feature/ProductoFilterTest.php`: Filtros en listado de productos activos por categoría (`?categoria_id`) y búsqueda por texto (`?q`).
- `tests/Feature/MetodoPagoRelationTest.php`: Relación inversa `pedidos(): HasMany` en el modelo `MetodoPago`.
- `tests/Feature/PedidoItemValidationTest.php`: Validación de producto inactivo (422), inmutabilidad en cocina para estados no pendientes (400), persistencia de modificadores adicionales con snapshot de precios y recálculo automático de subtotales y total del pedido.
- `tests/Feature/PedidoListTest.php`: Listado de pedidos y serialización del array `items.adicionales` en `index` y `show`.
- `tests/Feature/EstadoPedidoTest.php`: CRUD completo, validación de unicidad, soft deletes y restauración de estados.
- `tests/Feature/PagoTest.php`: Persistencia de columna `referencia` y serialización de la colección `comprobantes`.
- `tests/Feature/UserRolePartialUpdateTest.php`: Actualizaciones parciales seguras en usuarios y control null-safe en roles.
- `tests/Feature/SecurityRoutesTest.php`: Restricción de rutas protegidas (401), control RBAC por rol (403) y bypass de SuperAdmin.
- `tests/Feature/CorsTest.php`: Verificación de preflight OPTIONS (204) con credenciales y rechazo a orígenes no autorizados.

---

## 9. Instalación y Configuración Local

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
Editar el archivo `.env` configurando la base de datos, la URL del frontend y las credenciales de Telegram:
```env
APP_URL=http://127.0.0.1:8000
FRONTEND_URL=http://localhost:4200

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=delivery_db
DB_USERNAME=root
DB_PASSWORD=

TELEGRAM_BOT_TOKEN=
TELEGRAM_BOT_USERNAME=
```

### 4. Generar clave de aplicación
```bash
php artisan key:generate
```

### 5. Ejecutar migraciones y seeders
Crea el esquema relacional e inserta los datos iniciales de prueba (roles, usuarios, categorías, productos, adicionales y estados de pedido):
```bash
php artisan migrate --seed
```

### 6. Iniciar el servidor de desarrollo
```bash
php artisan serve --port=8000
```
La API estará disponible en `http://127.0.0.1:8000` y Swagger UI en `http://127.0.0.1:8000/api/documentation`.

---

## 10. Convenciones de Ingeniería y Flujo Git

- **Rama Base:** Todas las ramas de características y fixes parten y apuntan hacia `dev`.
- **Formato de Commits:** [Conventional Commits](https://www.conventionalcommits.org/) obligatorio (`feat:`, `fix:`, `docs:`, `test:`, `refactor:`, `chore:`).
- **Idioma del Repositorio:** Todos los comentarios de código, docblocks, descripciones de PRs y cuerpos de commits deben redactarse estrictamente en español neutro/profesional.
- **Sin Atribuciones Automáticas:** No incluir líneas `Co-Authored-By` ni firmas automáticas de IA.
- **Protocolo de PRs Stackeados:** Al agrupar múltiples cambios en un release bundle, se mantiene 1 PR individual por tarea, apuntando luego a la rama bundle para permitir revisión y merge secuencial ordenado.

---

## 11. Equipo de Desarrollo (Grupo 2)

Proyecto desarrollado para la cátedra **Programación IV**:

- **Valdez, Benjamín Ezequiel**
- **Alanis, Fabricio**
- **Berra, Agustina Inés**
- **Berra Panelo, Florencia María**

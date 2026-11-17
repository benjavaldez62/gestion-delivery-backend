# Sistema de Gestión de Delivery — La Posta (Backend API)

API REST desarrollada con **Laravel 12** (PHP 8.2+) para la gestión integral de pedidos, catálogo gastronómico, control de cocina, asignación de repartos y liquidación de pagos en establecimientos gastronómicos. Este repositorio conforma la capa de backend desacoplada del cliente Single Page Application (Angular 22).

---

## 1. Descripción General y Arquitectura

El sistema centraliza las operaciones del restaurante "La Posta", proporcionando una arquitectura RESTful segura, validada y documentada con OpenAPI / Swagger. La plataforma implementa control de acceso basado en roles (RBAC) mediante tokens de portador (Bearer Tokens) con **Laravel Sanctum** y middleware `CheckRole` (contemplando `Administrador`, `SuperAdmin`, `Cocinero` y `Repartidor`, y rechazando con `403` a usuarios inactivos o sin permisos).

### 1.1. Invariantes de Dominio y Reglas Arquitectónicas

1. **Ciclo de Vida del Pedido e Inmutabilidad en Cocina:**
   - Flujo de estados: `Pre-pedido` ➔ `Sin Asignar` ➔ `Pendiente` ➔ `En preparación` ➔ `Listo para entregar` ➔ `En camino` ➔ `Entregado` (o `Cancelado`).
   - **Pre-pedido y Confirmación por Telegram:** El pedido nace como `Pre-pedido` durante el checkout público. En esta etapa permanece aislado de los paneles operativos y KPIs del dashboard administrativo. Cuando el cliente confirma mediante el bot configurado, la orden transiciona a `Sin Asignar`, ingresando a la cola de cocina y asignación de personal.
   - **Regla estricta:** Únicamente los pedidos en estado `Pre-pedido` o `Pendiente` admiten agregación, edición o eliminación de ítems (`PedidoItem`). Una vez iniciada la preparación en cocina (`En preparación` en adelante), cualquier intento de modificación es rechazado con código `400 Bad Request`. Si el pedido padre fue eliminado lógicamente, las mutaciones sobre sus ítems responden `404 Not Found`.
2. **Integridad de Precios en el Servidor:**
   - El precio unitario de los productos y adicionales, los subtotales de línea, el costo de envío ($1500) y el `monto_total` del pedido se calculan y persisten exclusivamente en el servidor. Nunca se confía en importes calculados por el cliente.
3. **Baja Lógica / Soft Deletes:**
   - Las entidades relacionales del sistema (productos, categorías, adicionales, estados, métodos de pago, clientes, usuarios y pedidos) emplean borrado lógico mediante la columna `deleted_at` (`SoftDeletes`) y sincronización del indicador `activo = false`. Nunca se efectúa borrado físico directo sobre registros con dependencias operativas.
4. **Zona Horaria Operativa de Pagos y Dashboard:**
   - Los filtros temporales del dashboard (`hoy`, `7d`, `30d`), resúmenes de cobros, resúmenes de envíos y emisión de tickets utilizan `America/Argentina/Buenos_Aires` por defecto, configurable mediante `PAGOS_TIMEZONE` (o `TICKET_TIMEZONE` como respaldo) y convertido a UTC para consultas consistentes en base de datos.
5. **Seguridad y Documentación Swagger:**
   - Todos los endpoints protegidos requieren autenticación mediante middleware `auth:sanctum`.
   - Las rutas protegidas cuentan con respuestas formales documentadas para `401 Unauthorized` y `403 Forbidden`.

---

## 2. Stack Tecnológico

| Componente | Tecnología | Versión | Propósito |
|---|---|---|---|
| **Lenguaje** | PHP | `^8.2` | Entorno de ejecución de backend |
| **Framework** | Laravel | `^12.0` | Arquitectura MVC, enrutamiento, Eloquent ORM |
| **Autenticación API** | Laravel Sanctum | `^4.0` | Emisión y validación de Bearer Tokens para API |
| **OAuth Google** | Google Auth (`google/auth`) | `^1.55` | Verificación de `id_token` emitidos por Google Identity |
| **Documentación** | L5-Swagger / OpenAPI | `^11.1` | Documentación interactiva y especificación OpenAPI 3.0 |
| **Base de Datos** | MySQL / MariaDB (SQLite en tests) | `8.x+ / 10.x+` | Motor relacional con integridad referencial |
| **Testing** | Pest PHP | `^3.8` | Suite de pruebas unitarias y de integración |
| **Dependencias** | Composer | `2.x` | Gestor de paquetes PHP |

---

## 3. Estructura del Repositorio

```text
backend/
├── app/
│   ├── Console/Commands/            # Comandos Artisan personalizados
│   │   ├── TelegramPollCommand.php           # Worker long polling para bot de Telegram
│   │   ├── TelegramTestMessageCommand.php    # Test de credenciales y envío directo a Telegram
│   │   └── TelegramSimularWebhookCommand.php # Simulador de webhook para pruebas locales
│   ├── Http/
│   │   ├── Controllers/             # 21 controladores RESTful con atributos OpenAPI
│   │   │   ├── AdicionalController.php
│   │   │   ├── AuthController.php
│   │   │   ├── BorradorCheckoutController.php
│   │   │   ├── CategoriaController.php
│   │   │   ├── ClienteController.php
│   │   │   ├── ComprobanteController.php
│   │   │   ├── ConfiguracionPublicaController.php
│   │   │   ├── Controller.php
│   │   │   ├── DashboardController.php
│   │   │   ├── EstadoPagosController.php
│   │   │   ├── EstadoPedidoController.php
│   │   │   ├── GoogleAuthController.php
│   │   │   ├── MetodoPagoController.php
│   │   │   ├── PagoController.php
│   │   │   ├── PasswordRecoveryController.php
│   │   │   ├── PedidoController.php
│   │   │   ├── PedidoItemController.php
│   │   │   ├── ProductoController.php
│   │   │   ├── RoleController.php
│   │   │   ├── TelegramWebhookController.php
│   │   │   └── UserController.php
│   │   ├── Middleware/              # Control de acceso RBAC (CheckRole)
│   │   ├── Requests/                # 11 FormRequests con validación desacoplada
│   │   │   ├── ActualizarPagoRequest.php
│   │   │   ├── AsignarPersonalPedidoRequest.php
│   │   │   ├── ConfirmarPedidoTelegramRequest.php
│   │   │   ├── RegistrarPagoPedidoRequest.php
│   │   │   ├── RestablecerContrasenaRequest.php
│   │   │   ├── SolicitarRecuperacionRequest.php
│   │   │   ├── StoreBorradorCheckoutRequest.php
│   │   │   ├── StoreEstadoPedidoRequest.php
│   │   │   ├── StorePedidoItemRequest.php
│   │   │   ├── UpdateEstadoPedidoRequest.php
│   │   │   └── UpdatePedidoItemRequest.php
│   │   └── Resources/               # 13 API Resources para contratos JSON
│   │       ├── AdicionalResource.php
│   │       ├── CategoriaResource.php
│   │       ├── ClienteResource.php
│   │       ├── ComprobanteResource.php
│   │       ├── EstadoPagosResource.php
│   │       ├── EstadoPedidoResource.php
│   │       ├── MetodoPagoResource.php
│   │       ├── PagoResource.php
│   │       ├── PedidoItemResource.php
│   │       ├── PedidoResource.php
│   │       ├── ProductoResource.php
│   │       ├── RoleResource.php
│   │       └── UserResource.php
│   ├── Models/                      # 14 modelos Eloquent con relaciones y SoftDeletes
│   │   ├── Adicional.php
│   │   ├── BorradorCheckout.php
│   │   ├── Categoria.php
│   │   ├── Cliente.php
│   │   ├── Comprobante.php
│   │   ├── EstadoPagos.php
│   │   ├── EstadoPedido.php
│   │   ├── MetodoPago.php
│   │   ├── Pago.php
│   │   ├── Pedido.php
│   │   ├── PedidoItem.php
│   │   ├── Producto.php
│   │   ├── Role.php
│   │   └── User.php
│   ├── Notifications/               # Notificaciones de correo
│   │   └── RecuperarContrasena.php
│   ├── Providers/                   # Service Providers de Laravel
│   ├── Services/                    # Capa de lógica de negocio desacoplada
│   │   ├── DashboardService.php     # Métricas operativas, KPIs y alertas del dashboard
│   │   ├── PagoEfectivoService.php  # Registro de pagos en efectivo y validaciones
│   │   ├── ResumenEnviosService.php # Cálculo de envíos cobrados por repartidor
│   │   ├── TelegramService.php      # Despacho de mensajes HTML y notificaciones push
│   │   └── TicketService.php        # Generación y envío de tickets no fiscales
│   └── Swagger/
│       └── OpenApi.php              # Información global del servidor y esquema Sanctum
├── config/
│   ├── app.php                      # Configuración general, frontend_url y pagos_timezone
│   ├── cors.php                     # Política CORS para el cliente Angular
│   └── l5-swagger.php               # Configuración de generación OpenAPI
├── database/
│   ├── migrations/                  # Migraciones ordenadas preservando claves foráneas
│   └── seeders/                     # 13 seeders (RoleSeeder, UserSeeder, Catalogo, PagoSeeder, etc.)
├── routes/
│   ├── api.php                      # Rutas públicas y protegidas de la API
│   └── web.php
├── storage/
│   └── api-docs/                    # Especificación OpenAPI compilada (api-docs.json)
└── tests/
    ├── Feature/                     # 24 archivos de pruebas funcionales en Pest PHP
    └── Unit/                        # Pruebas unitarias
```

---

## 4. Catálogo Completo de Endpoints

### 4.1. Autenticación, Configuración Pública y Perfil (`/api`)

| Método | Ruta | Acceso | Descripción |
|:---:|:---|:---:|:---|
| `GET` | `/api/configuracion-publica` | Público | Retorna el username público del bot (`telegram_bot_username`, nunca el token). Devuelve `503` si falta o es inválido. |
| `POST` | `/api/login` | Público | Inicia sesión con correo y contraseña. Retorna usuario y Bearer Token Sanctum. |
| `POST` | `/api/auth/google` | Público | Autenticación con `id_token` de Google (`throttle:10,1`). Rechaza con `503` si `GOOGLE_CLIENT_ID` está vacío. |
| `POST` | `/api/forgot-password` | Público | Inicia recuperación de contraseña (`throttle:password-request`). |
| `POST` | `/api/reset-password` | Público | Restablece contraseña local con token válido (`throttle:password-reset`). |
| `POST` | `/api/logout` | `auth:sanctum` | Revoca el Bearer Token del usuario activo. |
| `GET` | `/api/user` | `auth:sanctum` | Obtiene el perfil del usuario autenticado. |
| `PUT` | `/api/profile` | `auth:sanctum` | Actualiza nombre, correo, teléfono y dirección únicamente del usuario autenticado; no permite cambiar rol, estado ni contraseña. |

#### Recuperación de contraseña

- `POST /api/forgot-password` recibe `email`. Para el dominio exacto `test.com` (sin distinguir mayúsculas), responde con `action: contactar_administrador` sin enviar correo ni notificación. Para otras cuentas, responde `404` si no existe, `403` si está inactiva, `429` ante limitación y `503` si falla el correo; responde `action: enlace_enviado` solo cuando SMTP acepta el mensaje.
- `POST /api/reset-password` recibe `email`, `token`, `password` y `password_confirmation`; cambia la contraseña local de una cuenta activa cuando el enlace es válido. Las cuentas `test.com` no pueden usar este mecanismo, incluso con un token anterior. La contraseña de Google no cambia.
- Configurar `FRONTEND_URL` con la URL pública del frontend y un transporte real mediante `MAIL_MAILER=smtp`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_SCHEME` y `MAIL_FROM_ADDRESS` (o `MAIL_URL`). `MAIL_MAILER=log` y `array` no envían correo y la recuperación los rechaza.
- El enlace apunta a `/restablecer-contrasena` en el frontend y coloca token y correo en el fragmento de la URL. El token vence según `auth.passwords.users.expire` (60 minutos por defecto), se invalida al usarlo y revoca los tokens Sanctum existentes. La contraseña admite entre 8 caracteres y 72 bytes, sin caracteres nulos. Cada endpoint limita a 5 solicitudes por minuto por IP.

### 4.2. Catálogo Gastronómico (Lectura pública / Mutación `auth:sanctum`)

| Método | Ruta | Acceso | Descripción |
|:---:|:---|:---:|:---|
| `GET` | `/api/productos` | Público | Lista productos activos con categoría vinculada. Admite filtros `?categoria_id={id}` y `?q={termino}` (ignora valores no escalares de forma segura sin producir errores 500). |
| `GET` | `/api/productos/{id}` | Público | Detalle de un producto puntual. |
| `POST` | `/api/productos` | `auth:sanctum` | Crear producto validando duplicados y estado activo. |
| `PUT` | `/api/productos/{id}` | `auth:sanctum` | Actualizar atributos de un producto. |
| `DELETE` | `/api/productos/{id}` | `auth:sanctum` | Baja lógica de producto (`activo = false`, `deleted_at`). |
| `PUT` | `/api/productos/{id}/restore` | `auth:sanctum` | Restaurar un producto dado de baja. |
| `GET` | `/api/productos-desactivados` | `auth:sanctum` | Listar productos eliminados lógicamente. |
| `GET` | `/api/categorias` | Público | Listar categorías activas. |
| `GET` | `/api/categorias/{id}` | Público | Detalle de una categoría puntual. |
| `GET` | `/api/categorias/{categoria}/adicionales` | Público | Listar adicionales activos asociados a una categoría o globales. |
| `POST` | `/api/categorias` | `auth:sanctum` | Crear nueva categoría. |
| `PUT` | `/api/categorias/{id}` | `auth:sanctum` | Actualizar categoría. |
| `DELETE` | `/api/categorias/{id}` | `auth:sanctum` | Baja lógica de categoría. |
| `PUT` | `/api/categorias/{id}/restore` | `auth:sanctum` | Restaurar categoría eliminada. |
| `GET` | `/api/categorias-desactivadas` | `auth:sanctum` | Listar categorías desactivadas. |
| `GET` | `/api/adicionales` | Público | Listar adicionales disponibles. Admite filtro opcional `?categoria_id={id}`. |
| `GET` | `/api/adicionales/{id}` | Público | Detalle de un adicional puntual exponiendo `categoria_id`. |
| `POST` | `/api/adicionales` | `auth:sanctum` | Crear nuevo adicional con `categoria_id` opcional (`null` para global). |
| `PUT` | `/api/adicionales/{id}` | `auth:sanctum` | Actualizar un adicional. |
| `DELETE` | `/api/adicionales/{id}` | `auth:sanctum` | Baja lógica de adicional. |
| `PUT` | `/api/adicionales/{id}/restore` | `auth:sanctum` | Restaurar adicional eliminado. |
| `GET` | `/api/adicionales-desactivados` | `auth:sanctum` | Listar adicionales desactivados. |

### 4.3. Gestión de Pedidos y Líneas de Pedido (`/api/pedidos`, `/api/pedido-items`)

| Método | Ruta | Acceso | Descripción |
|:---:|:---|:---:|:---|
| `GET` | `/api/pedidos` | `Administrador, Cocinero, Repartidor` | Lista pedidos activos (excluye `Pre-pedido` en vistas operativas según lógica del controlador) con carga eager de cliente, items, adicionales y pagos, exponiendo `nombre_cliente`. |
| `POST` | `/api/pedidos` | `auth:sanctum` | Registrar un nuevo pedido. |
| `GET` | `/api/pedidos/{id}` | `auth:sanctum` | Detalle del pedido con items y adicionales serializados. |
| `PUT` | `/api/pedidos/{id}` | `auth:sanctum` | Actualizar estado o datos del pedido (dispara notificaciones push de Telegram cuando corresponde). |
| `PUT` | `/api/pedidos/{id}/asignaciones` | `Administrador` | Asigna cocinero y/o repartidor activos al pedido y avanza el estado según corresponda. |
| `POST` | `/api/pedidos/{id}/ticket/enviar` | `auth:sanctum` | Reintenta el envío del ticket no fiscal por Telegram (validado para administrador o repartidor asignado). |
| `DELETE` | `/api/pedidos/{id}` | `auth:sanctum` | Cancelación o baja lógica del pedido. |
| `PUT` | `/api/pedidos/{id}/restore` | `auth:sanctum` | Restaurar pedido desactivado. |
| `GET` | `/api/pedidos-desactivados` | `auth:sanctum` | Listar pedidos cancelados/desactivados. |
| `GET` | `/api/pedido-items` | `auth:sanctum` | Listar items filtrando por `pedido_id`. |
| `POST` | `/api/pedidos/{pedido}/items` | `auth:sanctum` | Agregar ítem al pedido recalculando subtotal y total del pedido en el servidor. Rechaza con `400` si no está editable y `422` si producto o adicional están inactivos. |
| `GET` | `/api/pedido-items/{pedidoItem}` | `auth:sanctum` | Detalle de un ítem exponiendo producto (`categoria_id`), cantidad, subtotal y adicionales. |
| `PUT` | `/api/pedido-items/{pedidoItem}` | `auth:sanctum` | Modificar cantidad o adicionales de un ítem recalculando subtotales. Rechaza con `400` si inició la cocción y con `404` si el pedido padre tiene baja lógica. |
| `DELETE` | `/api/pedido-items/{pedidoItem}` | `auth:sanctum` | Quitar ítem del pedido. Rechaza con `400` si no es editable o es el único ítem, y con `404` si el pedido padre tiene baja lógica. |
| `GET` | `/api/pedido-items-desactivados` | `auth:sanctum` | Listar items desactivados. |

### 4.4. Gestión de Clientes (`/api/clientes`)

| Método | Ruta | Acceso | Descripción |
|:---:|:---|:---:|:---|
| `GET` | `/api/clientes` | `auth:sanctum` | Listar clientes activos. |
| `POST` | `/api/clientes` | `auth:sanctum` | Registrar un nuevo cliente. |
| `GET` | `/api/clientes/{id}` | `auth:sanctum` | Detalle de un cliente. |
| `PUT` | `/api/clientes/{id}` | `auth:sanctum` | Actualizar datos del cliente. |
| `DELETE` | `/api/clientes/{id}` | `auth:sanctum` | Baja lógica de cliente. |
| `PUT` | `/api/clientes/{id}/restore` | `auth:sanctum` | Restaurar cliente desactivado. |
| `GET` | `/api/clientes-desactivados` | `auth:sanctum` | Listar clientes desactivados. |

### 4.5. Checkout Público y Webhook de Telegram (`/api/checkout`, `/api/telegram`)

| Método | Ruta | Acceso | Descripción |
|:---:|:---|:---:|:---|
| `POST` | `/api/checkout/borradores` | Público | Registra cliente por teléfono y persiste de forma atómica un `Pedido` en estado inicial `Pre-pedido` junto con `nombre_cliente`, sus líneas (`PedidoItem`) y adicionales, calculando precios y envío fijo ($1500) en el servidor. Nombre y dirección admiten hasta 25 caracteres, aclaraciones hasta 255 y teléfono hasta 25 dígitos con `+` opcional al inicio. Solo acepta efectivo (`throttle:10,1`). |
| `POST` | `/api/pedidos/{id}/confirmar-telegram` | Público | Confirma el pre-pedido, vincula los datos del cliente y registra el pago en efectivo como pendiente (`throttle:30,1`). |
| `POST` | `/api/telegram/webhook` | Público | Webhook oficial para eventos de Telegram Bot API (`Update`) (`throttle:60,1`). Procesa `/start <id>`, `Pedido #<id>`, consulta `/estado` y respuestas con cooldown anti-spam de 2 horas. |

### 4.6. Dashboard y Métricas Operativas (`/api/dashboard`)

| Método | Ruta | Acceso | Descripción |
|:---:|:---|:---:|:---|
| `GET` | `/api/dashboard` | `Administrador` | Resumen consolidado del panel administrativo (`admin_nombre`, KPIs de facturación, ticket promedio, pedidos en curso, alertas de `Sin Asignar`, equipo disponible, distribución por estado y por método de pago; excluye `Pre-pedido`). Admite `?periodo=hoy\|7d\|30d\|historico` (por defecto `historico`), evaluado en `pagos_timezone` convertido a UTC. |

### 4.7. Estados de Pedido (`/api/estado-pedidos`)

| Método | Ruta | Acceso | Descripción |
|:---:|:---|:---:|:---|
| `GET` | `/api/estado-pedidos` | `auth:sanctum` | Listar todos los estados de pedido activos. |
| `POST` | `/api/estado-pedidos` | `auth:sanctum` | Crear nuevo estado de pedido validando unicidad. |
| `GET` | `/api/estado-pedidos/{id}` | `auth:sanctum` | Detalle de un estado de pedido. |
| `PUT` | `/api/estado-pedidos/{id}` | `auth:sanctum` | Actualización parcial o total de nombre/descripción. |
| `DELETE` | `/api/estado-pedidos/{id}` | `auth:sanctum` | Baja lógica. Retorna `409 Conflict` si existen pedidos asociados. |
| `PUT` | `/api/estado-pedidos/{id}/restore` | `auth:sanctum` | Restaurar un estado dado de baja. |
| `GET` | `/api/estado-pedidos-desactivados` | `auth:sanctum` | Listar estados de pedido desactivados. |

### 4.8. Finanzas, Pagos y Comprobantes

| Método | Ruta | Acceso | Descripción |
|:---:|:---|:---:|:---|
| `GET` | `/api/pagos` | `Administrador` | Listar pagos; admite `busqueda` por número de pago, pedido o cliente, nombre o teléfono, y filtros `pedido_id`, `estado_pago_id` y `metodo_pago_id`. |
| `GET` | `/api/pagos/resumen` | `Administrador` | Totales cobrados del día y mes hasta la fecha actual, según `pagos_timezone`. |
| `GET` | `/api/pagos/envios?fecha=YYYY-MM-DD` | `Administrador` | Detalle y suma de envíos cobrados por repartidor; sin fecha usa el día actual de `pagos_timezone`. |
| `POST` | `/api/pagos` | `Administrador` | Registrar pago pendiente en efectivo (`pedido_id`, `metodo_pago_id`, `monto` y `referencia` opcional). |
| `POST` | `/api/pedidos/{pedido}/pagos` | `Administrador` | Registrar pago pendiente asociado al pedido validando duplicados y monto total. |
| `GET` | `/api/pagos/{id}` | `Administrador` | Consultar pago, pedido, método, estado y comprobantes. |
| `PUT` | `/api/pagos/{id}` | `Administrador` | Actualizar estado pendiente/fallido y referencia de pago no confirmado. |
| `DELETE` | `/api/pagos/{id}` | `Administrador` | Baja lógica de pagos pendientes o fallidos sin comprobante. |
| `PUT` | `/api/pagos/{id}/restore` | `Administrador` | Restaurar un pago si el pedido no tiene otro pago activo. |
| `GET` | `/api/pagos-desactivados` | `Administrador` | Listar pagos desactivados. |
| `GET` | `/api/metodos-pago` | `auth:sanctum` | Listar métodos de pago activos. |
| `GET` | `/api/metodos-pago/{id}` | `auth:sanctum` | Detalle de método de pago. |
| `GET` | `/api/metodos-pago-desactivados` | `auth:sanctum` | Listar métodos de pago desactivados. |
| `POST` | `/api/metodos-pago` | `Administrador` | Crear método de pago. |
| `PUT` | `/api/metodos-pago/{id}` | `Administrador` | Actualizar método de pago (no permite renombrar uno asociado a pagos). |
| `DELETE` | `/api/metodos-pago/{id}` | `Administrador` | Baja lógica si no está asociado a pagos. |
| `PUT` | `/api/metodos-pago/{id}/restore` | `Administrador` | Restaurar método de pago desactivado. |
| `GET` | `/api/estado-pagos` | `auth:sanctum` | Listar estados de pago activos. |
| `GET` | `/api/estado-pagos/{id}` | `auth:sanctum` | Detalle de un estado de pago. |
| `GET` | `/api/estado-pagos-desactivados` | `auth:sanctum` | Listar estados de pago desactivados. |
| `POST` / `PUT` / `DELETE` / `restore` | `/api/estado-pagos...` | `Administrador` | Alta, edición, baja lógica y restauración de estados de pago. |
| `GET` | `/api/comprobantes` | `Administrador` | Listar comprobantes emitidos. |
| `GET` | `/api/comprobantes/{id}` | `Administrador` | Consultar comprobante y contenido original del ticket, si existe. |
| `POST` | `/api/comprobantes` | `Administrador` | Asociar comprobante o factura a un pago. |
| `DELETE` / `restore` / `-desactivados` | `/api/comprobantes...` | `Administrador` | Baja lógica, restauración y listado de comprobantes desactivados. |

#### Comportamiento financiero y tickets

- Al confirmar el pedido desde Telegram se registra el pago en efectivo como pendiente, sin fecha de cobro. Al pasar un pedido de `En camino` a `Entregado`, el repartidor asignado o un administrador registra el cobro, la fecha y un ticket no fiscal en una transacción. El contenido original del ticket queda guardado para que los reenvíos no cambien si se modifican los productos. Un fallo en el envío por Telegram no revierte la entrega ni el cobro y puede reintentarse mediante `POST /api/pedidos/{id}/ticket/enviar`. Los tickets extensos se dividen en mensajes. El ticket no es una factura fiscal.
- Los nuevos cobros, tickets y resúmenes usan `America/Argentina/Buenos_Aires` por defecto (`PAGOS_TIMEZONE` o `TICKET_TIMEZONE`). Los registros históricos anteriores al cambio conservan sus fechas UTC originales.
- El resumen de envíos considera pagos activos `Pagado` de pedidos entregados, estima el importe como `monto_total` menos subtotal y señala pedidos con datos inconsistentes. Es un estimado y no registra una liquidación pagada al repartidor.
- `PagoSeeder` solo se ejecuta en entorno `testing` (`DatabaseSeeder.php`), nunca en producción.

### 4.9. Administración General (Exclusivo Rol `Administrador` / `SuperAdmin`)

| Método | Ruta | Acceso | Descripción |
|:---:|:---|:---:|:---|
| `GET` | `/api/users` | `Administrador` | Listar usuarios y empleados activos. |
| `POST` | `/api/users` | `Administrador` | Crear usuario con contraseña hasheada y rol asignado. |
| `GET` | `/api/users/{id}` | `Administrador` | Detalle de usuario. |
| `PUT` | `/api/users/{id}` | `Administrador` | Actualización parcial segura mediante `$user->fill($validated)`. |
| `DELETE` | `/api/users/{id}` | `Administrador` | Baja lógica de usuario. Retorna `409` si tiene operaciones activas o asignadas. |
| `PUT` | `/api/users/{id}/restore` | `Administrador` | Restaurar usuario desactivado. |
| `GET` | `/api/users-desactivados` | `Administrador` | Listar usuarios desactivados con rol precargado. |
| `GET` | `/api/roles` | `Administrador` | Listar roles activos. |
| `POST` | `/api/roles` | `Administrador` | Crear rol permitiendo descripción opcional (`null-safe`). |
| `GET` | `/api/roles/{id}` | `Administrador` | Detalle de rol. |
| `PUT` | `/api/roles/{id}` | `Administrador` | Actualizar rol con regla `sometimes` en nombre. |
| `DELETE` | `/api/roles/{id}` | `Administrador` | Baja lógica de rol. |
| `PUT` | `/api/roles/{id}/restore` | `Administrador` | Restaurar rol desactivado. |
| `GET` | `/api/roles-desactivados` | `Administrador` | Listar roles desactivados. |

---

## 5. Integración con Telegram Bot

El sistema cuenta con una integración bidireccional con el bot de Telegram configurado en cada entorno. Actúa como canal conversacional para que el cliente confirme su `Pre-pedido` tras el checkout web y reciba notificaciones push ante cada cambio de estado.

Cada entorno debe configurar `TELEGRAM_BOT_USERNAME` sin `@` y `TELEGRAM_BOT_TOKEN` para el mismo bot. El endpoint público `GET /api/configuracion-publica` devuelve únicamente `telegram_bot_username` (nunca el token). Si falta o no cumple entre 5 y 32 caracteres ASCII alfanuméricos o guion bajo devuelve `503`. Al cambiar variables de entorno, ejecutar `php artisan config:clear` y reiniciar los workers activos.

### 5.1. Arquitectura y Desglose de Componentes

| Componente / Capa | Archivo | Responsabilidad Principal |
| :--- | :--- | :--- |
| **Configuración y Credenciales** | `.env` / `.env.example` | Variables `TELEGRAM_BOT_TOKEN` y `TELEGRAM_BOT_USERNAME`. |
| **Configuración Pública** | `app/Http/Controllers/ConfiguracionPublicaController.php` | Exposición segura de `telegram_bot_username`. |
| **Migración Telegram** | `database/migrations/2026_11_12_155340_add_telegram_fields_to_pedidos_table.php` | Incorpora `telegram_chat_id` y `telegram_username` en `pedidos`. |
| **Modelo y Resource** | `app/Models/Pedido.php`, `PedidoResource.php` | Persistencia y serialización de `nombre_cliente`, `telegram_chat_id` y `telegram_username`. |
| **Servicio Push** | `app/Services/TelegramService.php` | Despacho HTML (`enviarMensaje`), teclados interactivos y `notificarCambioEstado`. |
| **Controlador Webhook** | `app/Http/Controllers/TelegramWebhookController.php` | Procesamiento de updates, `/start <id>`, `Pedido #<id>`, `/estado` y cooldown anti-spam (2 horas). |
| **Checkout y Confirmación** | `BorradorCheckoutController.php`, `PedidoController.php` | Creación atómica de `Pre-pedido` y transición/confirmación en Telegram. |
| **Comandos Artisan** | `TelegramPollCommand.php`, `TelegramTestMessageCommand.php`, `TelegramSimularWebhookCommand.php` | Long polling local, verificación directa de credenciales y simulación de updates. |
| **Pruebas Automatizadas** | `tests/Feature/TelegramWebhookTest.php`, `ConfiguracionTelegramTest.php` | Cobertura Pest del webhook, transiciones, idempotencia y configuración pública. |

### 5.2. Catálogo de Disparadores y Respuestas del Bot

Gestionado en `TelegramWebhookController@handle`:

| # | Mensaje del Cliente | Condición en el Sistema | Respuesta del Bot |
| :---: | :--- | :--- | :--- |
| **1** | Saludo o texto cualquiera sin número de pedido (ej: `Hola`, `/start` solo). | No se detecta `/start <id>` ni `Pedido #<id>` (cooldown anti-spam de 2 horas en saludo largo). | Mensaje de bienvenida indicando formato `Pedido #12` o enlace desde la web. |
| **2** | Número de pedido inexistente (ej: `Pedido #999` o `/start 999`). | No existe el pedido en base de datos. | Aviso de pedido no encontrado solicitando verificar el número. |
| **3** | Pedido ya confirmado (ej: reintento `Pedido #12`). | El pedido existe con estado distinto de `Pre-pedido`. | Información del estado actual registrado para el pedido. |
| **4** | Mensaje de checkout válido (ej: `/start 12` o `Pedido #12`). | El pedido está en `Pre-pedido` (transiciona a `Sin Asignar` y registra `chat_id`). | Confirmación inicial con total del pedido y aviso de próximas notificaciones. |
| **5** | Consulta de estado (`/estado` o botón interactivo). | Cliente con `chat_id` registrado en el sistema. | Estado actual del último pedido asociado al chat. |

### 5.3. Catálogo de Notificaciones Push Proactivas (Cambios de Estado)

Disparadas por `TelegramService@notificarCambioEstado`:

| # | Evento en el Sistema | Estado Resultante | Notificación enviada al Chat |
| :---: | :--- | :--- | :--- |
| **1** | Admin asigna personal en `/pedidos`. | `Pendiente` | Aviso de personal asignado y próximo inicio de preparación. |
| **2** | Cocinero toma el pedido en `/cocina`. | `En preparación` | Confirmación de que el pedido se está cocinando. |
| **3** | Cocina finaliza preparación. | `Listo para entregar` | Aviso de pedido empacado esperando al repartidor. |
| **4** | Repartidor inicia recorrido en `/repartos`. | `En camino` | Aviso de reparto en camino hacia el domicilio. |
| **5** | Repartidor marca entrega y registra cobro. | `Entregado` | Confirmación de entrega + envío de ticket no fiscal. |
| **6** | Cancelación administrativa o de personal. | `Cancelado` | Aviso de cancelación del pedido. |

### 5.4. Modos de Ejecución y Comandos Artisan

- **Modo Desarrollo (Long Polling sin túneles):**
  ```bash
  php artisan telegram:poll
  ```
  Elimina el webhook configurado en el bot antes de consultar la API de Telegram y despacha actualizaciones localmente. Usarlo únicamente con un bot de prueba propio cuando no se requiera conservar un webhook externo.
- **Prueba de credenciales y envío directo:**
  ```bash
  php artisan telegram:test-message <chat_id>
  ```
- **Simulación de webhook local:**
  ```bash
  php artisan telegram:simular-webhook <id_pedido>
  ```

---

## 6. Integración Frontend y Configuración CORS

El backend configura CORS en `config/cors.php` sobre `api/*` y `sanctum/csrf-cookie`:

- **Orígenes autorizados:** `http://localhost:4200`, `http://127.0.0.1:4200` y el origen configurado en `FRONTEND_URL`.
- **Patrones regex permitidos:** `#^http://localhost:\d+$#` y `#^http://127\.0\.0\.1:\d+$#`.
- **Métodos admitidos:** `GET`, `POST`, `PUT`, `PATCH`, `DELETE`, `OPTIONS`.
- **Cabeceras permitidas:** `Authorization`, `Content-Type`, `Accept`, `X-Requested-With`, `X-XSRF-TOKEN`, `Origin`.
- **Cabecera expuesta:** `Authorization`.
- **Credenciales y Cache:** `'supports_credentials' => true`, `max_age => 86400`.

---

## 7. Documentación Interactiva (Swagger UI)

La API cuenta con documentación OpenAPI 3.0 generada con `L5-Swagger` bajo el servidor `http://127.0.0.1:8000`. Con el servidor en ejecución, la consola está disponible en:

```text
http://127.0.0.1:8000/api/documentation
```

> **Nota de origen (Same-Origin Policy):** Acceder a Swagger UI mediante `http://127.0.0.1:8000/api/documentation` y no desde `localhost`, para evitar bloqueos de origen cruzado al probar endpoints desde el navegador.

### Autenticación en Swagger

1. Ejecutar `POST /api/login` con credenciales válidas.
2. Copiar el token devuelto.
3. En **Authorize**, ingresar `Bearer <tu-token-aqui>`.

### Regeneración de Especificación OpenAPI

```bash
php artisan l5-swagger:generate
```

El artefacto JSON compilado se guarda en `storage/api-docs/api-docs.json`.

---

## 8. Pruebas Automatizadas (Pest PHP)

El proyecto cuenta con cobertura funcional y unitaria en **Pest PHP** (`183 pruebas pasando, 1017 aserciones` sobre `dev`):

```bash
php artisan test
```

### Desglose de Archivos de Prueba (`tests/Feature` y `tests/Unit`)

- `tests/Unit/ExampleTest.php`: Prueba base unitaria.
- `tests/Feature/AuthLoginTest.php`: Login, hash de contraseñas, tokens Sanctum, rechazo de usuarios inactivos y validación de configuración de Google OAuth.
- `tests/Feature/AuthLogoutTest.php`: Revocación de token y rechazo anónimo.
- `tests/Feature/PasswordRecoveryTest.php`: Flujo `/forgot-password` y `/reset-password`, regla `test.com` y límites de tasa.
- `tests/Feature/ProfileTest.php`: Actualización segura de perfil propio vía `PUT /api/profile`.
- `tests/Feature/SecurityRoutesTest.php`: Protección `401` y control RBAC por rol (`403`) para `Administrador`, `SuperAdmin`, `Cocinero` y `Repartidor`.
- `tests/Feature/CorsTest.php`: Verificación de preflight `OPTIONS` y orígenes autorizados.
- `tests/Feature/BorradorCheckoutTest.php`: Creación atómica de `Pre-pedido`, límites de entrega, recálculo en servidor y rechazo de medios no habilitados.
- `tests/Feature/ConfiguracionTelegramTest.php`: Exposición pública de `telegram_bot_username` y validación `503`.
- `tests/Feature/TelegramWebhookTest.php`: Confirmación de pre-pedidos, `/estado`, cooldown e idempotencia.
- `tests/Feature/DashboardTest.php`: Exclusión de `Pre-pedido`, prioridad de `nombre_cliente` y filtros horarios en `America/Argentina/Buenos_Aires` convertidos a UTC.
- `tests/Feature/AdicionalPorCategoriaTest.php` y `AdicionalSeederTest.php`: Adicionales por categoría/globales y seeders idempotentes.
- `tests/Feature/ProductoFilterTest.php`: Filtros `?categoria_id` / `?q` y protección contra parámetros no escalares.
- `tests/Feature/AsignarPersonalPedidoTest.php`: Asignación de cocinero y repartidor activos por rol.
- `tests/Feature/PedidoItemValidationTest.php`: Validaciones de estado editable, snapshot de precios y protección `404` ante pedido padre eliminado lógicamente.
- `tests/Feature/PedidoListTest.php`: Exposición de `nombre_cliente`, `items.adicionales` y contratos JSON.
- `tests/Feature/EstadoPedidoTest.php`: CRUD completo, unicidad y baja lógica.
- `tests/Feature/MetodoPagoRelationTest.php`: Relación inversa con pedidos.
- `tests/Feature/PagoTest.php`, `GestionPagoEfectivoTest.php` y `PagoEfectivoEntregaTest.php`: Resúmenes, filtros, envíos por repartidor, entrega con cobro y envío/reenvío de tickets.
- `tests/Feature/UserDestroyValidationTest.php` y `UserRolePartialUpdateTest.php`: Validación de baja de usuarios con asignaciones y actualizaciones parciales seguras.

---

## 9. Instalación y Configuración Local

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

Configurar en `.env` según el entorno local:

```env
APP_URL=http://127.0.0.1:8000
FRONTEND_URL=http://localhost:4200
PAGOS_TIMEZONE=America/Argentina/Buenos_Aires

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=admin
DB_USERNAME=root
DB_PASSWORD=

GOOGLE_CLIENT_ID=TU_CLIENT_ID.apps.googleusercontent.com

TELEGRAM_BOT_TOKEN=
TELEGRAM_BOT_USERNAME=
```

### 4. Generar clave de aplicación

```bash
php artisan key:generate
```

### 5. Ejecutar migraciones y seeders

```bash
php artisan migrate --seed
```

### 6. Iniciar el servidor de desarrollo

```bash
php artisan serve --host=127.0.0.1 --port=8000
```

La API quedará disponible en `http://127.0.0.1:8000` y Swagger UI en `http://127.0.0.1:8000/api/documentation`.

---

## 10. Convenciones de Ingeniería y Flujo Git

- **Rama Base:** Todas las ramas de características, fixes y documentación parten y apuntan hacia `dev`.
- **Formato de Commits:** [Conventional Commits](https://www.conventionalcommits.org/) obligatorio (`feat:`, `fix:`, `docs:`, `test:`, `refactor:`, `chore:`).
- **Idioma del Repositorio:** Comentarios de código, docblocks, descripciones de PRs y cuerpos de commits redactados en español neutro y profesional.
- **Sin Atribuciones Automáticas:** No incluir líneas `Co-Authored-By` ni firmas automáticas de IA.
- **Protocolo de PRs Stackeados:** Al agrupar múltiples cambios relacionados, se mantiene 1 PR individual por tarea apuntando a la rama bundle para revisión ordenada.

---

## 11. Equipo de Desarrollo (Grupo 2)

Proyecto desarrollado para la cátedra **Programación IV**:

- **Valdez, Benjamín Ezequiel**
- **Alanis, Fabricio**
- **Berra, Agustina Inés**
- **Berra Panelo, Florencia María**

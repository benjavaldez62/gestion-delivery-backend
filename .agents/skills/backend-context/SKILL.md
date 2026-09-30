---
name: backend-context
description: Contexto del proyecto "Sistema de Gestión de Delivery" (Grupo 2). Incluye reglas de negocio, roles de usuario, base de datos y funcionalidades. Úsalo siempre que trabajes en el backend para entender el dominio y seguir los estándares del proyecto.
---

# Contexto del Proyecto: Sistema de Gestión de Delivery

Este proyecto es una aplicación web (Laravel) para la gestión de pedidos de delivery en establecimientos gastronómicos.
Actualmente estamos trabajando en el **Backend**. La arquitectura separa claramente el Frontend, Backend (lógica de negocio) y Base de Datos.

## Funcionalidades Principales
1. **Autenticación y Acceso**: Login/Signup. Autorregistro público para clientes, y gestión interna para el personal a cargo de Administradores y SuperAdministradores.
2. **Roles y Permisos**:
   - **Cliente**: Explora el menú dinámico (dashboard público), agrega productos al carrito, y hace seguimiento del estado de las órdenes en tiempo real.
   - **SuperAdministrador / Administrador**: CRUD de personal y cuentas, gestión del menú (alta/baja de platos, actualización de precios), gestión global de operaciones y asignación de tareas.
   - **Cocinero**: Visualiza pedidos asignados y actualiza el estado (ej. *En Preparación* -> *Listo para Entregar*).
   - **Repartidor**: Visualiza hoja de ruta y pedidos pendientes de distribución, y actualiza el estado final.
3. **Integraciones Avanzadas (Planificadas)**:
   - Sincronización en tiempo real, notificaciones vía Telegram, automatización con n8n.

## Esquema de Base de Datos
El sistema maneja las siguientes entidades y tablas principales:

- **roles**: `rol_id` (PK), `nombre`, `descripcion`.
- **usuarios**: Personal. `usuario_id` (PK), `nombre`, `email`, `password`, `telefono`, `dni`, `rol_id` (FK), `activo`.
- **clientes**: Clientes externos. `cliente_id` (PK), `telefono`, `nombre`, `direccion`.
- **categorias**: Categorías de productos. `categoria_id` (PK), `nombre`, `descripcion`, `activo`.
- **productos**: Menú. `producto_id` (PK), `nombre`, `descripcion`, `precio`, `imagen`, `categoria_id` (FK), `activo`.
- **adicionals**: Extras para los productos. `adicional_id` (PK), `nombre`, `precio`, `activo`.
- **estado_pedidos**: (PENDIENTE, EN PREPARACIÓN, LISTO, EN CAMINO, ENTREGADO, CANCELADO). `estado_pedido_id` (PK), `nombre`.
- **pedidos**: `pedido_id` (PK), `cliente_id` (FK), `cocinero_id` (FK), `repartidor_id` (FK), `estado_pedido_id` (FK).
- **pedido_items**: Detalle del pedido. `pedido_item_id` (PK), `pedido_id` (FK), `producto_id` (FK), `cantidad`, `precio_unitario`.
- **pedidoitems_adicionales**: Relación entre items y extras. `item_adicional_id` (PK), `pedido_item_id` (FK), `adicional_id` (FK), `cantidad`, `precio_unitario`.
- **estado_pagos**: Estados de pago (PENDIENTE, APROBADO, RECHAZADO).
- **metodo_pagos**: Métodos de pago (EFECTIVO, TRANSFERENCIA, MERCADOPAGO).
- **pagos**: `pago_id` (PK), `pedido_id` (FK), `metodo_pago_id` (FK), `estado_pago_id` (FK), `monto`.
- **comprobantes**: Facturas/Tickets. `comprobante_id` (PK), `pago_id` (FK), `tipo`, `url_archivo`.

## Estándares de Arquitectura y Desarrollo (Guía para la IA)

### 1. Regla de "Soft Deletes" Personalizados
- **NO utilizamos `deleted_at`** (el estándar de Laravel). 
- Para las bajas lógicas, usamos la columna booleana `activo` (`true` = Habilitado, `false` = Inhabilitado).
- Al implementar controladores o modelos, asegúrate de filtrar siempre por `activo = true` para las listas públicas, y de crear endpoints explícitos para "inhabilitar" o "restaurar" registros cuando corresponda.

### 2. Estandarización de la API REST
- **API Resources Obligatorios**: Todas las respuestas JSON que devuelvan modelos deben transformarse utilizando Laravel `JsonResource` (`php artisan make:resource`). No devuelvas modelos crudos desde los controladores.
- Mantén una estructura de respuesta coherente (ej: envolver colecciones en `data`).

### 3. Documentación con Swagger (L5-Swagger)
- Documentamos la API mediante **Atributos de PHP 8** (ej: `#[OA\Get]`, `#[OA\Property]`, etc.) en los controladores.
- **Importante**: Si L5-Swagger presenta errores de clases no encontradas, recuerda que depende de un paso de generación. Verifica haber ejecutado `composer install` si el paquete se actualizó.

### 4. Validaciones y Transacciones
- **FormRequests**: Todo dato de entrada (`POST`, `PUT`, `PATCH`) debe validarse estrictamente mediante un `FormRequest` (`php artisan make:request`).
- **Transacciones (DB::transaction)**: Las operaciones que modifiquen múltiples tablas relacionadas (ej: Crear un pedido + sus items + generar el pago) deben estar envueltas en transacciones de base de datos para garantizar la integridad.

### 5. Roles, Permisos y Autenticación
- Proteger las rutas de la API en `routes/api.php` utilizando los middlewares adecuados (`auth:sanctum` u otros).
- Implementar validaciones de roles (Gates o Policies) donde un endpoint solo deba ser accesible por un Administrador, Cocinero, Repartidor, etc.

### 6. Flujo de Trabajo en Git
- Respeta SIEMPRE las reglas de `.agents/rules/git-workflow.md`.
- Ramas: Desde `dev` con formato `{nombre}/{seccion}/{alcance}`.
- PRs: Siempre apuntados a `dev`, sin emojis, con mensajes claros.

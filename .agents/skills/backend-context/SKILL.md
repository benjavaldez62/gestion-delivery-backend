---
name: backend-context
description: Contexto del proyecto "Sistema de Gestión de Delivery" (Grupo 2 - UTN Prog IV). Incluye reglas de negocio, roles de usuario, esquema de base de datos real, modelos Eloquent, API Resources y convenciones de desarrollo.
---

# Contexto del Proyecto: Sistema de Gestión de Delivery (Backend)

Este proyecto es una API REST construida con **Laravel 12** y **PHP 8.2+** para la gestión integral de pedidos de delivery en establecimientos gastronómicos.
La arquitectura desacopla el Backend (API REST y lógica de negocio) del Frontend y la Base de Datos (**MariaDB / MySQL**).

---

## 1. Roles y Permisos del Sistema

El sistema implementa control de acceso basado en roles (`roles` y `users` con autenticación mediante **Laravel Sanctum**):

1. **SuperAdministrador / Administrador**:
   - Gestión integral del personal (CRUD de usuarios internos y asignación de roles).
   - Gestión del catálogo (CRUD de categorías, productos y adicionales, actualización de precios y disponibilidad).
   - Configuración operativa de métodos de pago y estados.
   - Visión global de todos los pedidos, asignación de cocineros y repartidores.
2. **Cocinero**:
   - Visualización de pedidos asignados en cocina.
   - Actualización de estados del pedido (ej. *Pendiente* -> *En Preparación* -> *Listo para Entrega*).
3. **Repartidor**:
   - Visualización de hoja de ruta y pedidos pendientes de distribución.
   - Actualización del estado final (ej. *En Camino* -> *Entregado*).
4. **Cliente**:
   - Entidad separada (`clientes`), con autorregistro o registro asociado a usuario.
   - Exploración del catálogo dinámico (categorías, productos y adicionales).
   - Realización de pedidos y seguimiento del estado en tiempo real.
   - Clave para futuras integraciones de mensajería (Telegram).

---

## 2. Estructura de la Base de Datos y Modelos Eloquent

La base de datos relacional (`delivery_db`) cuenta con las siguientes entidades y modelos:

### Usuarios y Roles
- **`roles`** (`Role`): `id` (PK), `nombre`, `descripcion`, `activo`, `created_at`, `updated_at`.
- **`users`** (`User`): `id` (PK), `name`, `email`, `password`, `role_id` (FK -> `roles`), `activo`, `created_at`, `updated_at`.
- **`clientes`** (`Cliente`): `id` (PK), `nombre`, `telefono` (clave para Telegram), `direccion`, `activo`, `user_id` (FK opcional -> `users`), `created_at`, `updated_at`.

### Catálogo y Menú
- **`categorias`** (`Categoria`): `id` (PK), `nombre`, `descripcion`, `activo`, `created_at`, `updated_at`.
- **`productos`** (`Producto`): `id` (PK), `nombre`, `descripcion`, `precio`, `categoria_id` (FK -> `categorias`), `imagen`, `activo`, `disponible`, `created_at`, `updated_at`.
- **`adicionals`** (`Adicional`): `id` (PK), `nombre`, `precio`, `activo`, `created_at`, `updated_at`.

### Pedidos
- **`estado_pedidos`** (`EstadoPedido`): `id` (PK), `nombre` (Pendiente, En Preparación, Listo, En Camino, Entregado, Cancelado), `descripcion`, `activo`.
- **`pedidos`** (`Pedido`): `id` (PK), `cliente_id` (FK -> `clientes`), `cocinero_id` (FK opcional -> `users`), `repartidor_id` (FK opcional -> `users`), `estado_pedido_id` (FK -> `estado_pedidos`), `total`, `direccion_entrega`, `fecha_pedido`, `observaciones`, `created_at`, `updated_at`.
- **`pedido_items`** (`PedidoItem`): `id` (PK), `pedido_id` (FK -> `pedidos`), `producto_id` (FK -> `productos`), `cantidad`, `precio_unitario`, `subtotal`, `created_at`, `updated_at`.
- **`pedidoitems_adicionales`**: Tabla pivote para adicionales en cada ítem. `id` (PK), `pedido_item_id` (FK -> `pedido_items`), `adicional_id` (FK -> `adicionals`), `precio_adicional`, `created_at`, `updated_at`.

### Cobros y Pagos
- **`metodos_pago`** (`MetodoPago`): `id` (PK), `nombre` (Efectivo, Transferencia, MercadoPago, etc.), `descripcion`, `activo`.
- **`estado_pagos`** (`EstadoPagos`): `id` (PK), `nombre` (Pendiente, Aprobado, Rechazado), `descripcion`, `activo`.
- **`pagos`** (`Pago`): `id` (PK), `pedido_id` (FK -> `pedidos`), `metodo_pago_id` (FK -> `metodos_pago`), `estado_pago_id` (FK -> `estado_pagos`), `monto`, `fecha_pago`, `referencia`, `activo`, `created_at`, `updated_at`.
- **`comprobantes`** (`Comprobante`): `id` (PK), `pago_id` (FK -> `pagos`), `numero_comprobante`, `tipo`, `url_archivo`, `fecha_emision`, `created_at`, `updated_at`.

---

## 3. Convenciones de Arquitectura y Desarrollo

1. **Estandarización de Respuestas con API Resources (`app/Http/Resources/`)**:
   - Todo controlador debe transformar los modelos utilizando su Resource correspondiente (`RoleResource`, `CategoriaResource`, `ProductoResource`, `PagoResource`, `PedidoResource`, etc.).
   - Evitar retornar modelos crudos directamente en las respuestas JSON.

2. **Soft Delete Lógico (`activo`)**:
   - Las entidades principales usan una columna booleana `activo` para borrado lógico.
   - En `destroy($id)` se marca `activo = false`.
   - En `restore($id)` se marca `activo = true`.
   - El método `index()` puede filtrar registros activos por defecto o permitir listar inactivos según parámetros.

3. **Documentación OpenAPI / Swagger (`l5-swagger`)**:
   - La documentación se genera en `/api/documentation`.
   - La configuración base reside en `app/Swagger/OpenApi.php`.
   - Nuevos endpoints deben documentarse utilizando atributos nativos de PHP 8 (`#[OA\Get]`, `#[OA\Post]`, `#[OA\Parameter]`, `#[OA\Response]`).

4. **Flujo de Trabajo Git ([.agents/rules/git-workflow.md](file:///home/benja/Desktop/prog_4_utn/gestion-delivery-backend/.agents/rules/git-workflow.md))**:
   - Rama base de desarrollo: `dev`.
   - Nomenclatura obligatoria: `{nombre}/{seccion}/{alcance}` (ej. `benja/feature/auth-google`).
   - Todos los PRs deben tener como destino `dev`.
   - Sin emojis en títulos de commits, PRs ni descripciones.
   - Los releases a `main` son gestionados exclusivamente por el Team Leader (Benja).

---

## 4. Guía para la IA

- **Consistencia de nombres**: Utiliza siempre los nombres de tabla y atributos reales especificados en este documento (`productos` en lugar de `menus`, `users` en lugar de `usuarios`, `estado_pedidos` y `estado_pagos` en lugar de un único `estados`).
- **Controladores**: Implementa validaciones adecuadas (`FormRequest` o `Validator`) y usa siempre los `Resource` de `app/Http/Resources/` para devolver respuestas JSON estandarizadas.
- **Relaciones Eloquent**: Respeta las relaciones definidas en los modelos (`belongsTo`, `hasMany`, `belongsToMany`).
- **Verificación**: Comprueba siempre los cambios ejecutando los tests o la suite de migraciones (`php artisan migrate:status`).

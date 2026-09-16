---
name: backend-context
description: Contexto del proyecto "Sistema de Gestión de Delivery" (Grupo 2). Incluye reglas de negocio, roles de usuario, base de datos y funcionalidades. Úsalo siempre que trabajes en el backend para entender el dominio.
---

# Contexto del Proyecto: Sistema de Gestión de Delivery

Este proyecto es una aplicación web (Laravel) para la gestión de pedidos de delivery en establecimientos gastronómicos.
Actualmente estamos trabajando en el **Backend**. La arquitectura separa claramente el Frontend, Backend (lógica de negocio) y Base de Datos.

## Funcionalidades Principales
1. **Autenticación y Acceso**: Login/Signup. Autorregistro público para clientes, y gestión interna para el personal a cargo de Administradores y SuperAdministradores. Integración planificada con Google (OAuth).
2. **Roles y Permisos**:
   - **Cliente**: Explora el menú dinámico (dashboard público), agrega productos al carrito, y hace seguimiento del estado de las órdenes en tiempo real.
   - **SuperAdministrador / Administrador**: CRUD de personal y cuentas (el SuperAdministrador no puede darse de baja, el Administrador solo inhabilita roles inferiores), gestión del menú (alta/baja de platos, actualización de precios), gestión global de operaciones y asignación de tareas a cocineros/repartidores.
   - **Cocinero**: Visualiza pedidos asignados y actualiza el estado (ej. *En Preparación* -> *Listo para Entregar*).
   - **Repartidor**: Visualiza hoja de ruta y pedidos pendientes de distribución, y actualiza el estado final (*En Camino* -> *Entregado*).
3. **Integraciones Avanzadas (Planificadas)**:
   - Sincronización en tiempo real.
   - Notificaciones y encuestas de satisfacción vía Telegram al concretarse la entrega.
   - Autenticación con Google para personal.
   - Automatización de flujos de trabajo con **n8n**.

## Estructura de la Base de Datos

El sistema maneja las siguientes entidades y tablas principales:

- **roles**: `rol_id` (PK), `nombre`, `descripcion`.
- **usuarios**: Personal del sistema. `usuario_id` (PK), `nombre`, `email`, `password`, `telefono`, `dni`, `rol_id` (FK).
- **clientes**: Clientes externos. `cliente_id` (PK), `telefono` (clave para Telegram), `nombre`, `direccion`.
- **estados**: Estados de pedidos (PENDIENTE, EN PREPARACIÓN, LISTO, EN CAMINO, ENTREGADO, CANCELADO) o de productos/usuarios (HABILITADO, INHABILITADO). `estado_id` (PK), `nombre`, `descripcion`.
- **categorias**: Categorías de productos. `categoria_id` (PK), `nombre`, `descripcion`, `created_at`, `updated_at`.
- **menus**: Platos del menú. `menu_id` (PK), `nombre`, `descripcion`, `precio`, `imagen`, `categoria_id` (FK), `estado_id` (FK), `created_at`.
- **pedidos**: `pedido_id` (PK), `cliente_id` (FK), `cocinero_id` (FK), `repartidor_id` (FK), `estado_id` (FK), `created_at`, `updated_at`.
- **pedido_items**: Detalle (muchos-a-muchos). `pedido_item_id` (PK), `pedido_id` (FK), `menu_id` (FK), `cantidad`, `precio_unitario`.

## Guía para la IA
- Cuando implementes lógica de negocio, ten en cuenta las restricciones de los roles.
- Usa este archivo como referencia principal para los nombres de tablas y atributos a la hora de crear migraciones, modelos Eloquent, Request Validations o Factories.
- Mantén la coherencia con los tipos de datos documentados en la propuesta original.
- Respeta en todo momento el flujo de trabajo de Git y PRs definido en `.agents/rules/git-workflow.md` (ramas originadas en `dev` y PRs dirigidos siempre hacia `dev`).

# Sistema de Gestión de Delivery - Backend

Una API REST robusta construida con **Laravel** para la gestión integral de pedidos de delivery en establecimientos gastronómicos. Este proyecto conforma el **Backend** del sistema, separando la lógica de negocio y el acceso a la base de datos de la capa de presentación (Frontend).

---

## 🚀 Descripción General

El Sistema de Gestión de Delivery tiene como objetivo permitir a los clientes explorar el menú, realizar pedidos y consultar su estado en tiempo real. Simultáneamente, el personal del local cuenta con herramientas para coordinar la preparación, asignación y distribución de las entregas.

### Funcionalidades Principales

- **Autenticación y Control de Acceso**: Registro e inicio de sesión del personal. Integración planificada con Google (OAuth).
- **Roles y Permisos**:
  - **Cliente**: Visualización de menú público, carrito de compras y seguimiento de órdenes.
  - **SuperAdministrador / Administrador**: Gestión de personal (CRUD), gestión del menú y operaciones globales.
  - **Cocinero**: Visualización de pedidos asignados y actualización de su estado de preparación.
  - **Repartidor**: Hojas de ruta de distribución y confirmación de entrega.
- **Gestión de Órdenes**: Flujo completo de estados del pedido (Pendiente, En Preparación, Listo, En Camino, Entregado, Cancelado).
- **Integraciones Avanzadas**: Notificaciones automáticas por Telegram a los clientes, encuestas de satisfacción y actualizaciones en tiempo real.

---

## 📋 Requisitos Previos

- **PHP**: 8.2 o superior
- **Composer**: Para la gestión de dependencias PHP
- **MySQL/MariaDB**: Sistema de base de datos relacional
- **Git**: Control de versiones

---

## 🔧 Instalación y Configuración

Sigue estos pasos para levantar el entorno de desarrollo del backend localmente:

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

Copia el archivo de ejemplo para crear tu propia configuración local:

```bash
cp .env.example .env
```

Abre el archivo `.env` y configura tu conexión a la base de datos:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=delivery_db
DB_USERNAME=tu_usuario
DB_PASSWORD=tu_contraseña
```

### 4. Generar la Clave de la Aplicación

```bash
php artisan key:generate
```

### 5. Ejecutar Migraciones (y Seeders)

Crea la estructura de la base de datos (asegúrate de que la base de datos ya exista en tu motor local):

```bash
php artisan migrate
```
*(Opcional: Si cuentas con seeders para cargar roles o datos de prueba, puedes ejecutar `php artisan migrate --seed`)*

### 6. Levantar el Servidor de Desarrollo

```bash
php artisan serve
```
El servidor estará disponible en `http://localhost:8000`.

---

## 🌿 Flujo de Trabajo (Git)

El equipo trabaja bajo el flujo documentado en `.agents/rules/git-workflow.md`:
- **Rama base de desarrollo**: `dev`
- **Formato de ramas**: `{nombre}/{seccion}/{alcance}`
  - Ejemplos: `benja/feature/auth-google`, `fabricio/fix/calculo-total`
- **Pull Requests**: Siempre dirigidos hacia `dev` (salvo hotfixes a `main`), con Conventional Commits y sin emojis en la descripción.
- **Releases a producción (`main`)**: Gestionados exclusivamente por el Team Leader (Benja) al validar entregas en `dev`.

---

## 👥 Equipo (Grupo 2)
- Valdez, Benjamín Ezequiel
- Alanis, Fabricio
- Berra, Agustina Inés
- Berra, Panelo Florencia María

Materia: **Programación IV**

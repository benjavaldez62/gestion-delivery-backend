# Flujo de Trabajo en GitHub (Git Workflow)

Para mantener el orden en el repositorio, todos los agentes de IA y desarrolladores deben seguir el siguiente flujo de trabajo:

## Ramas Principales
- `main`: Contiene el código en producción (estable). **No se debe pushear ni hacer commit directamente aquí.**
- `dev`: Rama principal de desarrollo. Todas las nuevas features y correcciones convergen aquí. **No se debe pushear directamente aquí.**

## Nomenclatura de Ramas
Crea siempre una rama nueva partiendo de `dev` para trabajar. Utiliza los siguientes prefijos según corresponda:
- `feature/<nombre-descriptivo>`: Para nuevas funcionalidades. Ej: `feature/auth-telegram`, `feature/crud-menus`.
- `fix/<nombre-descriptivo>`: Para correcciones de bugs. Ej: `fix/error-login`, `fix/calculo-total-pedido`.
- `hotfix/<nombre-descriptivo>`: Para correcciones urgentes en producción (estas son las únicas que pueden salir de `main`).

## Flujo de Pull Requests (PR)
1. **Desarrollo**: Escribe y prueba tu código localmente en tu rama (`feature/xxx`).
2. **Commits**: Realiza commits pequeños y descriptivos usando Conventional Commits (ej: `feat: agrega modelo de Pedido`, `fix: corrige validación de teléfono`).
3. **Pull Request**: Abre un PR hacia la rama `dev` (o `main` si es un hotfix).
4. **Revisión**: Espera la revisión (Code Review) de los compañeros.
5. **Merge**: Una vez aprobado, se realizará el merge (preferiblemente con "Squash and Merge").

## Directrices Específicas para la IA
- **NO hagas commits directamente a `dev` ni a `main`.**
- Si se te pide escribir código y subirlo, asegúrate de crear primero una rama apropiada (ej. `git checkout -b feature/nuevo-modelo`).
- Al ejecutar comandos de git, genera mensajes de commit claros y en español explicando *qué* y *por qué* se cambió.

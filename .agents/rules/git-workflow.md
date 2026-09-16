---
trigger: always_on
---

# Flujo de Trabajo en GitHub (Git Workflow)

Para mantener el orden en el repositorio, todos los agentes de IA y desarrolladores deben seguir el siguiente flujo de trabajo:

## Ramas Principales
- `main`: Contiene el código en producción (estable). **No se debe pushear ni hacer commit directamente aquí.**
- `dev`: Rama principal de desarrollo. Todas las nuevas features y correcciones convergen aquí. **No se debe pushear directamente aquí.**

## Nomenclatura de Ramas
Crea siempre una rama nueva partiendo de `dev` para trabajar (excepto hotfixes urgentes). La estructura obligatoria para los nombres de rama es:

```text
{nombre}/{seccion}/{alcance}
```

Donde:
- **`{nombre}`**: Nombre o identificador del desarrollador o agente (ej: `benja`, `fabricio`, `agustina`, `florencia`).
- **`{seccion}`**: Tipo o categoría del trabajo (`feature`, `fix`, `docs`, `refactor`, `chore`, `hotfix`).
- **`{alcance}`**: Descripción concisa en kebab-case de la funcionalidad o corrección (ej: `auth-google`, `crud-menus`, `calculo-total-pedido`).

### Ejemplos:
- `benja/feature/auth-google`: Nueva funcionalidad de autenticación Google iniciada por Benja.
- `fabricio/feature/crud-menus`: Alta, baja y modificación de menús.
- `agustina/fix/calculo-total-pedido`: Corrección en el cálculo del total de órdenes.
- `florencia/refactor/controlador-pedidos`: Refactorización de controladores de pedidos.
- `benja/hotfix/error-login-produccion`: Corrección urgente en producción (única que sale de `main`).

## Flujo de Pull Requests (PR)
1. **Desarrollo**: Escribe y prueba tu código localmente en tu rama (`{nombre}/{seccion}/{alcance}`).
2. **Commits**: Realiza commits pequeños y descriptivos usando Conventional Commits (ej: `feat: agrega modelo de Pedido`, `fix: corrige validación de teléfono`).
3. **Pull Request**: Abre un PR hacia la rama destino correspondiente:
   - **Rama Base (Target)**: Debe ser **`dev`** para todas las features y fixes. *(¡Atención! GitHub suele preseleccionar `main` por defecto; verificar siempre que sea `base: dev` <- `compare: tu-rama`).*
   - Los únicos PRs con destino a `main` son los de tipo `hotfix/*` o los releases desde `dev`.
4. **Formato del PR**:
   - **Título**: Seguir Conventional Commits sin emojis (ej: `feat: endpoint de checkout de pedidos`).
   - **Estilo**: Mantener un tono sobrio, claro y profesional. **No utilizar emojis** en el título ni en el cuerpo del PR ni en los mensajes de commit.
   - **Cuerpo / Descripción corta**: Utilizar la siguiente estructura concisa:
     ```markdown
     ### Descripcion
     Resumen breve de qué cambios introduce este PR y por qué son necesarios.

     ### Cambios principales
     - Detalle de cambios clave 1
     - Detalle de cambios clave 2

     ### Como probarlo / Verificacion
     Pasos para reproducir o testear localmente (comandos de test, endpoints, etc.).

     ### Checklist
     - [ ] Apunta a la rama `dev` (o `main` si es hotfix)
     - [ ] Codigo probado localmente
     - [ ] Sin credenciales ni datos sensibles commiteados
     ```
5. **Revisión**: Espera la revisión (Code Review) de los compañeros.
6. **Merge**: Una vez aprobado, se realizará el merge (preferiblemente con "Squash and Merge").
7. **Limpieza Post-Merge**:
   ```bash
   git checkout dev
   git pull origin dev
   git branch -d <nombre>/<seccion>/<alcance>
   ```

## Releases a Producción (`main`)
- **Responsabilidad exclusiva del Team Leader / Administrador**: Los pases de cambios desde `dev` hacia `main` (despliegues a producción o entregas de versión) son gestionados únicamente por el Team Leader (Benja).
- Ningún desarrollador ni agente de IA debe promover o fusionar cambios hacia `main` de manera autónoma.
- Los releases se realizan periódicamente o al cerrar hitos, una vez que las funcionalidades integradas en `dev` hayan sido validadas y se encuentren estables.

## Directrices Específicas para la IA y Automatizaciones
- **NO hagas commits directamente a `dev` ni a `main`.**
- **NO realices merges hacia `main`**: Toda integración hacia `main` queda reservada al Team Leader.
- Si se te pide escribir código y subirlo, asegúrate de crear primero una rama apropiada desde `dev` siguiendo el formato `{nombre}/{seccion}/{alcance}` (ej. `benja/feature/nuevo-modelo` o `ai/feature/nuevo-modelo`).
- Al ejecutar comandos de git, genera mensajes de commit claros y en español explicando *qué* y *por qué* se cambió.
- **Sin emojis**: No incluyas emojis en títulos de PR, descripciones ni mensajes de commit.
- **Creación automatizada de PRs**: Si se utiliza GitHub CLI (`gh`), especificar siempre la base branch explícitamente (`dev`) y sin emojis:
  ```bash
  gh pr create --base dev --title "tipo: descripcion corta" --body "..."
  ```
- Al sugerir o generar links de PR al usuario, recordar siempre comprobar que la rama base apunte a `dev`.


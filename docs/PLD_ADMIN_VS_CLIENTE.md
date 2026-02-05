# PLD: Dónde aplica cada flujo — Admin vs Operación vs Usuario

Este documento indica **en qué lado** (Admin, Operación/Back-office o Usuario) se usa cada validación y pantalla PLD, para que quede claro qué es funcional en cada contexto.

---

## Resumen rápido

| Lado | Descripción | Acceso |
|------|-------------|--------|
| **Admin** | Consola de administración (`admin/`). Configuración empresa, padrón PLD, responsable PLD, representación legal (gestión), expedientes PLD. | Solo usuarios con `$_SESSION['is_admin'] === true` |
| **Operación / Back-office** | Páginas en la raíz con `templates/header.php`: dashboard, clientes, operaciones PLD, conservación, check PLD. Gestión del día a día. | Usuarios autenticados (personal interno) |
| **Usuario (mi cuenta)** | Pantalla “Mi cuenta” donde el usuario ve y carga su **representación legal**. | Cualquier usuario autenticado (su propio perfil) |

El **“cliente”** en sentido regulatorio (sujeto del expediente, donante, etc.) **no tiene portal propio** en esta implementación: lo gestiona el personal desde Admin/Operación.

---

## 1. Lado ADMIN (carpeta `admin/`)

Todo lo que está bajo **`admin/`** y exige `is_admin === true`:

| Archivo | Qué hace | VAL-PLD / función PLD |
|---------|----------|------------------------|
| **admin/config.php** | Configuración empresa, **padrón PLD** (folio, estatus, fracciones activas), **revalidación** (VAL-PLD-002), **responsable PLD** (VAL-PLD-003). | `validatePatronPLD`, `checkRevalidacionDue`, `revalidatePatronPLD`, `validateResponsablePLD` |
| **admin/representacion_legal.php** | Alta/edición/baja de **representación legal** de usuarios (representante legal, apoderado, usuario autorizado). | VAL-PLD-004: `validateRepresentacionLegal` |
| **admin/expedientes_pld.php** | Listado y validación de **expedientes PLD** por cliente (completitud y actualización anual). | VAL-PLD-005, VAL-PLD-006: `validateExpedienteCompleto`, `validateActualizacionExpediente` |

**APIs llamadas desde Admin:**

- `api/designar_responsable_pld.php` (desde config o flujo de cliente) → VAL-PLD-003  
- `api/revalidate_patron_pld.php` (revalidación manual) → VAL-PLD-002  

---

## 2. Lado OPERACIÓN / BACK-OFFICE (raíz, uso interno)

Páginas en la **raíz** que usan `templates/header.php` y `templates/top_bar.php`. Las usa el personal para operar (no la consola Admin):

| Archivo | Qué hace | VAL-PLD / función PLD |
|---------|----------|------------------------|
| **index.php** | Dashboard: watermark PLD, estado revalidación, enlace a config PLD. | `validatePatronPLD`, `checkRevalidacionDue`, `checkHabilitadoPLD` |
| **check_pld.php** | Comprobación rápida de habilitación PLD; enlace a `admin/config.php#pld`. | VAL-PLD-001: `validatePatronPLD`, `checkHabilitadoPLD` |
| **cliente_nuevo.php** | Alta de cliente. Bloqueo si PLD no habilitado; enlace a config. | VAL-PLD-001: `checkHabilitadoPLD`, `validatePatronPLD` |
| **cliente_editar.php** | Edición de cliente. Middleware PLD y validación expediente. | VAL-PLD-001, 005, 006, 007 (según middleware) |
| **clientes.php** | Listado de clientes (gestión). | Depende de links a editar/detalle que cargan PLD |
| **operaciones_pld.php** | Registro y listado de **operaciones PLD**, avisos, informes de no operaciones. | VAL-PLD-001 (habilitación), 008–012 (avisos/umbrales) vía `api/registrar_operacion_pld.php`, `api/registrar_informe_no_operaciones.php` |
| **conservacion_pld.php** | **Conservación** de información (10 años), visitas de verificación. | VAL-PLD-013, VAL-PLD-014: `pld_conservacion.php` |

**APIs usadas desde Operación (y a veces desde Admin):**

| API | Uso principal | VAL-PLD |
|-----|----------------|--------|
| **api/save_client.php** | Guardar cliente (alta/edición) | 001, 005, 006, 007, responsable |
| **api/update_client.php** | Actualizar cliente | 001, 005, 006, 007 |
| **api/validate_person.php** | Validar persona/expediente | 005, 006 |
| **api/validate_expediente_pld.php** | Validar expediente | 005, 006 |
| **api/update_fecha_expediente.php** | Actualizar fecha expediente | 006 |
| **api/confirm_pld_selection.php** | Confirmar selección PLD en flujo cliente | 001, 005, 006, 007 |
| **api/beneficiario_controlador.php** | Beneficiario controlador | 007, 015 |
| **api/designar_responsable_pld.php** | Designar responsable PLD | 003 |
| **api/registrar_operacion_pld.php** | Registrar operación PLD (donativos, inmobiliario, etc.) | 001, 004, 008, 009, 010, 011, 013 |
| **api/registrar_informe_no_operaciones.php** | Informe de no operaciones | 012 |
| **api/get_operaciones_pld.php** | Listar operaciones | — |
| **api/get_informes_no_operaciones.php** | Listar informes | — |
| **api/get_conservacion_info.php** | Info conservación | 013 |
| **api/registrar_conservacion.php** | Registrar evidencia conservación | 013 |
| **api/registrar_visita_verificacion.php** | Registrar visita de verificación | 014 |
| **api/get_visitas_verificacion.php** | Listar visitas | — |

Todas estas APIs están del lado **operación/back-office** (y algunas también desde Admin); **ninguna está pensada para un portal de “cliente final”** (el sujeto del expediente).

---

## 3. Lado USUARIO (Mi cuenta)

| Archivo | Qué hace | VAL-PLD / función PLD |
|---------|----------|------------------------|
| **mi_cuenta.php** | El **usuario logueado** ve y sube su **representación legal** (documento de facultades). Validación de que tenga representación vigente. | VAL-PLD-004: `validateRepresentacionLegal($pdo, $userId)` |

Aquí “cliente” es el **usuario del sistema** (empleado/apoderado) que debe acreditar facultades para actuar; no el cliente regulatorio (titular del expediente).

---

## 4. Módulos por fracción (Admin vs Operación)

Los **módulos de fracción** (`pld_fraccion_xiii.php`, `pld_fraccion_v.php`, `pld_fraccion_v_bis.php`, `pld_fraccion_vi.php`) **no están colgados de una pantalla concreta** todavía: son **librerías** que debes usar en los flujos donde corresponda.

- **Admin**:  
  - En **admin/config.php** (o pantalla de configuración por fracción) puedes usar `requireFraccionXIIIActiva`, `requireFraccionVActiva`, etc., para validar que la fracción esté activa en el padrón.
- **Operación**:  
  - Al **registrar una operación** (p. ej. donativo, inmobiliario, metales/joyas) debes incluir el `config/pld_fraccion_*.php` que corresponda y llamar:
    - `requireFraccionXIIIActiva($pdo)` / `requireFraccionVActiva($pdo)` / etc.
    - `requiereExpedienteDonante($monto_uma)` / `requiereExpedienteInmobiliario()` / `requiereExpedienteMetalesJoyas($monto_uma)` según fracción.
    - `requireNoNegativaIdentificacion($pdo, $id_cliente)` (VAL-PLD-026) donde aplique.
    - `validateProhibicionEfectivoInmobiliario` / `validateProhibicionEfectivoMetalesJoyas` (VAL-PLD-027) donde aplique.
    - `getIdVulnerableFraccionXIII($pdo)` / etc. para pasar `id_fraccion` a `api/registrar_operacion_pld.php` y que los umbrales (aviso/expediente) sean los correctos.

Todo esto es **lado operación/back-office** (pantallas de registro de operaciones y APIs que las respaldan).

---

## 5. Herramientas y scripts (no interfaz)

| Archivo | Uso | Lado |
|---------|-----|------|
| **tools/revalidate_patron_pld.php** | Revalidar padrón por CLI | Admin/operación (script) |
| **api/revalidate_patron_pld.php** | Revalidar padrón vía API | Llamado desde Admin/operación |

---

## 6. Tabla resumen: ¿Admin o Cliente?

| Funcionalidad | Admin (`admin/`) | Operación (raíz) | Usuario (mi cuenta) |
|---------------|------------------|-------------------|----------------------|
| Configuración empresa / padrón PLD | ✅ config.php | Enlace desde index, check_pld, cliente_nuevo | ❌ |
| Revalidación periódica (VAL-PLD-002) | ✅ config.php, API revalidate | Enlace desde index | ❌ |
| Responsable PLD (VAL-PLD-003) | ✅ config.php, designar_responsable_pld | ✅ al dar de alta/editar cliente (save_client, etc.) | ❌ |
| Representación legal (VAL-PLD-004) | ✅ representacion_legal.php (gestión de todos) | Middleware en APIs que requieren facultades | ✅ mi_cuenta.php (el usuario sube la suya) |
| Expediente cliente (VAL-PLD-005, 006) | ✅ expedientes_pld.php (listado/validación) | ✅ save_client, update_client, validate_expediente_pld | ❌ |
| Negativa identificación (VAL-PLD-026) | — | ✅ En flujo de alta/operación (requireNoNegativaIdentificacion) | ❌ |
| Beneficiario controlador (007, 015) | — | ✅ save_client, update_client, beneficiario_controlador | ❌ |
| Operaciones PLD / Avisos (008–012) | — | ✅ operaciones_pld.php + registrar_operacion_pld, informe no operaciones | ❌ |
| Conservación / Visitas (013, 014) | — | ✅ conservacion_pld.php + APIs conservación/visitas | ❌ |
| Fracciones (XIII, V, V Bis, VI) | Opcional en config por fracción | ✅ Al registrar operación (incluir pld_fraccion_*.php y validaciones) | ❌ |

**Conclusión:**  
- **Admin**: configuración, padrón, revalidación, gestión de representación legal y expedientes PLD.  
- **Operación**: todo el flujo de clientes, operaciones PLD, avisos, conservación y uso de módulos por fracción.  
- **Cliente (usuario del sistema)**: solo **mi_cuenta.php** para su representación legal (VAL-PLD-004).  
- El **cliente regulatorio** (sujeto del expediente) no tiene portal; se gestiona siempre desde Admin/Operación.

Si más adelante añades un portal para el cliente final (por ejemplo solo consulta de su expediente), habría que añadir aquí una sección “Portal cliente (sujeto del expediente)” y asignar solo las pantallas/APIs que se expongan ahí.

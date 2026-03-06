# Bugs corregidos - Revisión de código

**Fecha:** Febrero 2025

## Resumen

Se realizó una revisión del código y se corrigieron los siguientes problemas detectados.

---

## 1. Severidad alta

### 1.1 api/get_client_details.php – Acceso a arrays sin validar fetch()

**Problema:** `$personaType` y `$apoType` podían ser `false` cuando el `fetch()` no devolvía filas, provocando un error al acceder a sus índices.

**Solución:**
- Validación de `$personaType` antes de usar; se lanza excepción si no existe.
- Validación de `$apoType`; si no existe, se asigna "N/A" y se continúa con el siguiente apoderado.

### 1.2 api/registrar_conservacion.php – Falta validación de ownership

**Problema:** Solo se validaba `id_cliente`. Un usuario podía registrar evidencias asociadas a operaciones o avisos de otros usuarios usando `id_operacion` o `id_aviso`.

**Solución:** Validación de ownership para:
- `id_operacion` → comprobando que la operación pertenezca a un cliente del usuario.
- `id_aviso` → comprobando que el aviso pertenezca a un cliente del usuario.

### 1.3 templates/top_bar.php – Riesgo de XSS

**Problema:** Datos dinámicos (nombre de usuario, tipo, mensaje y nombre de cliente en notificaciones) se insertaban en HTML sin escape.

**Solución:**
- Función `escapeHtml()` para sanitizar texto antes de insertarlo en el DOM.
- Uso de `textContent` donde es posible.
- Sanitización de `n.tipo`, `n.nombre_cliente`, `n.mensaje` en el panel de notificaciones.
- `id_notificacion` e `id_cliente` convertidos a entero para evitar inyección.

---

## 2. Severidad media

### 2.1 api/monitor_status.php – Sin verificación de sesión

**Problema:** Cualquiera podía llamar al endpoint sin autenticación.

**Solución:** Comprobación de `$_SESSION['user_id']` y respuesta 401 si no hay sesión.

### 2.2 config/pld_conservacion.php – DateTime con fecha inválida

**Problema:** `new DateTime($evidencia['fecha_vencimiento'])` podía lanzar excepción si el valor era null o inválido.

**Solución:** Comprobación de existencia y try-catch en ambos usos de la lógica de vencimiento.

### 2.3 api/update_client.php – Uso de fetch() sin validación

**Problema:** `$personaType = $type_stmt->fetch()` podía ser `false`, provocando error al acceder a sus índices.

**Solución:** Verificación de `$personaType` antes de usarlo; si no existe, se responde con error y se hace rollback.

---

## 3. Pendiente o de menor prioridad

- **api/confirm_pld_sejection.php:** `$table` en `SHOW COLUMNS FROM` debe validarse contra una lista permitida.
- **Código duplicado:** El patrón de verificación de admin se repite en varias APIs; se podría extraer a una función común.
- **api/get_eventos_criticos_pld.php:** El límite se concatena en la SQL (actualmente validado 1–500); podría pasarse como parámetro en prepared statement si se requiere mayor rigor.

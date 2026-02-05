# Análisis: Obligaciones (Ley, Reglamento, RCG) vs. Código EVE360

Este documento cruza el contenido del archivo **OBLIGACIONES articulos LEY Reglamento y reglas EVE360.docx** con lo ya implementado en el código y propone mejoras.

---

## Lo incorporado en código (referente al documento)

| Obligación (Ley/RCG) | Incorporación |
|----------------------|----------------|
| **Capacitación anual del REC** (Art. 20 Ley, RCG Art. 10) | ✅ **Implementado.** Migración `db/migrations/add_obligaciones_ley_rcg.sql`: en `clientes_responsable_pld` se añaden `fecha_ultima_capacitacion` y `vigencia_capacitacion`. En `config/pld_responsable_validation.php`: `validateResponsablePLD()` exige capacitación vigente (si la columna existe y no hay fecha o está vencida → RESTRICCION_USUARIO). Función `registrarCapacitacionRec($pdo, $id_responsable_pld, $fecha, $vigencia)` para registrar la capacitación. |
| **Clasificación de bajo riesgo** (Art. 17 y 12 RCG) | ✅ **Campos en BD.** Migración `add_obligaciones_ley_rcg.sql`: en `clientes` se añaden `clasificacion_bajo_riesgo` y `fecha_clasificacion_bajo_riesgo`. La lógica de expediente simplificado (Anexos 7/7-A/7 Bis) y uso de estos campos en validación queda para una siguiente fase. |
| **Cotejo de copias contra originales** (Art. 12 RCG) | ✅ **Campos en BD.** Migración `add_obligaciones_ley_rcg.sql`: en `clientes_documentos` se añaden `cotejado_contra_original` y `fecha_cotejo`. Falta uso en UI (marcar documento como cotejado). |

El resto de obligaciones del documento (alta/baja padrón, expediente por Anexo, avisos, conservación, confidencialidad, etc.) se mantiene como en el análisis siguiente: **ya está** cubierto en la medida indicada o queda como **pendiente/opcional**.

---

## 1. OBLIGACIÓN: Alta y registro en el Padrón PLD (SPPLD)

| Fuente | Obligación | ¿En código? | Dónde / observación |
|--------|------------|-------------|----------------------|
| **Art. 18 LFPIORPI** (fracc. IV Bis) | Alta, registro, modificación o baja en SPPLD | ✅ Sí | `config/pld_validation.php` → `validatePatronPLD()`; `admin/config.php` (folio, estatus, fracciones). VAL-PLD-001. |
| **Reglamento Art. 12 y 13** | Información por medios y formato oficial; baja cuando ya no se realicen AV | ✅ Parcial | Alta/estatus/fracciones en config; **no hay flujo explícito de “solicitud de baja”** ni recordatorio de seguir avisando hasta que surta efecto. |
| **RCG Art. 7 y 8** | SAT puede actualizar registro; notificación en 10 días; presentar Avisos sin perjuicio de actualización | ✅ Parcial | Revalidación trimestral (VAL-PLD-002) en `pld_revalidation.php`. **No** se modela “actualización por SAT” ni notificación 10 días. |

**Mejoras sugeridas:**  
- Pantalla o flujo de “Solicitud de baja al padrón” con aviso de obligación de seguir presentando avisos hasta que surta efecto.  
- Opcional: registro de “actualización realizada por SAT” (fecha, motivo) y alerta de plazo 10 días si se quiere alinear con RCG Art. 7.

---

## 2. OBLIGACIÓN: Representante Encargado del Cumplimiento y capacitación anual

| Fuente | Obligación | ¿En código? | Dónde / observación |
|--------|------------|-------------|----------------------|
| **Art. 20 LFPIORPI / RCG Art. 10** | Designación de Representante Encargado del Cumplimiento | ✅ Sí | `config/pld_responsable_validation.php` → `validateResponsablePLD()`. VAL-PLD-003. |
| **RCG Art. 10** | La persona REC **debe recibir anualmente capacitación** para el cumplimiento de la Ley | ✅ **Incorporado** | `clientes_responsable_pld.fecha_ultima_capacitacion`, `vigencia_capacitacion` (migración `add_obligaciones_ley_rcg.sql`). `validateResponsablePLD()` exige capacitación vigente; si no hay fecha o está vencida → RESTRICCION_USUARIO. `registrarCapacitacionRec($pdo, $id_responsable_pld, $fecha, $vigencia)` para dar de alta/actualizar. |

**Pendiente opcional:** Pantalla en Admin para registrar capacitación (fecha y, si aplica, evidencia) usando `registrarCapacitacionRec()`.

---

## 3. OBLIGACIÓN: Reserva y confidencialidad (Art. 38 LFPIORPI / Art. 37 RCG)

| Fuente | Obligación | ¿En código? | Dónde / observación |
|--------|------------|-------------|----------------------|
| **Art. 38 LFPIORPI** | Información y documentación soporte de Avisos, identidad de quien presenta y REC → **confidencial y reservada** (LGTAIP) | ⚠️ Implícito | No hay módulo específico “manejo de información reservada”. Control de acceso por sesión y permisos; no hay etiquetado “confidencial PLD” ni procedimiento documentado en sistema. |
| **Art. 37 RCG** | Documento con criterios, medidas y procedimientos (Art. 11, 17, 18, 35) a disposición de UIF/SAT | ⚠️ Parcial | Criterios de riesgo en `config_factores_riesgo`, `config_riesgo_rangos`; **no** hay “documento único Art. 37” versionado (políticas PLD) ni checklist de “puesto a disposición”. |

**Mejoras sugeridas:**  
- Política de confidencialidad en login o en sección PLD (texto fijo o en BD) y registro de aceptación.  
- Módulo “Documento Art. 37” (versión, fecha, archivo PDF) y registro de “puesto a disposición a UIF/SAT” (fecha, motivo) para auditoría.

---

## 4. OBLIGACIÓN: Expediente de identificación (Art. 12 RCG, Art. 18 LFPIORPI fracc. I)

| Fuente | Obligación | ¿En código? | Dónde / observación |
|--------|------------|-------------|----------------------|
| **Art. 12 RCG** | Expediente previo o durante el acto (o al establecer Relación de Negocios); requisitos I a VII; **Anexos 3, 4, 4 Bis, 5, 6, 6 Bis, 7, 7-A, 7 Bis, 7 Bis A y 8** según tipo | ✅ Parcial | `config/pld_expediente.php` → `validateExpedienteCompleto()`: datos básicos por tipo (física/moral/fideicomiso), identificaciones, direcciones, contactos, documentos. **No** hay checklist explícito por Anexo (3, 4, 4 Bis, 5, 6, etc.). |
| **Art. 18 RCG** (medios electrónicos) | Integrar **previamente** expediente; mecanismos de identificación; procedimientos en documento Art. 37; información integrada en expediente | ✅ Parcial | Se exige expediente antes de operaciones vía `requireExpedienteCompleto()`. **No** hay “mecanismos de identificación reforzados” ni trazabilidad identidad–documento–operación como en el doc. |
| Cotejo de copias contra originales (Art. 12) | Asegurar copias legibles y cotejarlas contra original o copia certificada | ✅ **Campos en BD** | `clientes_documentos.cotejado_contra_original`, `fecha_cotejo` (migración `add_obligaciones_ley_rcg.sql`). Pendiente: uso en pantalla de documentos (marcar como cotejado). |
| Conservación física (Art. 12) | Posibilidad de conservar en físico en archivo único | ⚠️ No | Solo conservación digital (conservacion_informacion_pld, evidencia en rutas). |

**Mejoras sugeridas:**  
- **Checklist por Anexo:** para cada tipo de persona (física, moral mexicana, 4 Bis, física extranjera, moral extranjera, etc.) definir en BD o config los “datos y documentos” de cada Anexo y validar en `validateExpedienteCompleto()` por `id_tipo_persona` y, si aplica, nacionalidad.  
- Uso en UI de `cotejado_contra_original` y `fecha_cotejo` en documentos (ya existen en BD).  
- Opcional: indicador “expediente conservado en físico” por cliente para no exigir carga de todos los documentos si se cumple Art. 12.

---

## 5. OBLIGACIÓN: Clientes de bajo riesgo y expediente simplificado (Art. 17 y 12 RCG, Anexos 7/7-A/7 Bis)

| Fuente | Obligación | ¿En código? | Dónde / observación |
|--------|------------|-------------|----------------------|
| **Art. 17 RCG** | Clientes/usuarios **de bajo Riesgo**: criterios y elementos en **documento Art. 37**; medidas simplificadas solo si cumplen Art. 17 y 34 | ✅ **Campos en BD** | `clientes.clasificacion_bajo_riesgo`, `fecha_clasificacion_bajo_riesgo` (migración `add_obligaciones_ley_rcg.sql`). Pendiente: usar en validación de expediente simplificado (Anexos 7/7-A/7 Bis) y migración a expediente completo. |
| **Art. 12 RCG** (fracc. V, V Bis) | Medidas simplificadas para morales/dependencias/entidades del Anexo 7-A considerados de bajo riesgo (Art. 17 y 34) | ⚠️ Parcial | Campos de clasificación ya existen; falta lógica de “expediente simplificado” y migración a completo ante cambio de condiciones. |
| Documento Art. 37 | Criterios de clasificación de bajo riesgo documentados | ⚠️ No | No existe “documento Art. 37” versionado en sistema. |

**Pendiente:** Usar `clasificacion_bajo_riesgo` en reglas de expediente simplificado; pantalla Admin “Criterios de bajo riesgo (Art. 37)” y versión del documento.

---

## 6. OBLIGACIÓN: Avisos (presentación, plazo día 17, sin perjuicio de actualización)

| Fuente | Obligación | ¿En código? | Dónde / observación |
|--------|------------|-------------|----------------------|
| Presentar Avisos (Ley/Reglamento/RCG) | Avisos por umbral, acumulación, sospechosa, listas; plazo día 17 mes siguiente; sin perjuicio de actualización padrón | ✅ Sí | `config/pld_avisos.php`: VAL-PLD-008 a 012; `calcularDeadlineAviso()`; `registrarOperacionPLD()`; informe de no operaciones. |
| Facilitad administrativa por RFC | Mencionado en doc (día 17 con facilidad) | ✅ Implementado | Deadline fijo día 17; no hay lógica adicional “por RFC” más allá de lo ya implementado. |

**Mejoras sugeridas:**  
- Si la autoridad publica criterios de “facilidad administrativa por RFC”, reflejarlos en cálculo de deadline (por ahora el código está alineado con “día 17”).  
- VAL-PLD-023 (aviso modificatorio una sola vez en 30 días): aún pendiente; añadir en `avisos_pld` control de “modificado” y validación de plazo.

---

## 7. OBLIGACIÓN: Conservación 10 años y disposición a la autoridad

| Fuente | Obligación | ¿En código? | Dónde / observación |
|--------|------------|-------------|----------------------|
| Conservación (Ley/RCG) | Expedientes, avisos e informes, documentación soporte por **al menos 10 años** | ✅ Sí | `config/pld_conservacion.php`; `conservacion_informacion_pld` con `fecha_vencimiento` (10 años). VAL-PLD-013. |
| Puesta a disposición UIF/SAT | Expedientes e información a disposición cuando lo requieran | ✅ Parcial | Visitas de verificación (VAL-PLD-014), eventos críticos. No hay “registro de requerimiento de autoridad” genérico más allá de visitas. |

**Mejoras sugeridas:**  
- Opcional: tabla “requerimientos_autoridad” (fecha, tipo, descripción, expedientes solicitados, fecha de atención) para trazar Art. 12 último párrafo.  
- Asegurar que la conservación cubra también “comprobantes de pago y documentación comercial” cuando aplique (doc. obligaciones).

---

## 8. OBLIGACIÓN: Intercambio de información (Art. 35 RCG)

| Fuente | Obligación | ¿En código? | Dónde / observación |
|--------|------------|-------------|----------------------|
| **Art. 35 RCG** | Intercambio de información entre quienes realizan AV; limitado a fortalecer medidas y procedimientos; conforme a I–IV | ❌ No | No hay módulo de “intercambio de información” con otros sujetos obligados. |

**Mejoras sugeridas:**  
- Solo implementar si la operación lo requiere (ej. grupos, redes). En ese caso: registro de intercambios (con quien, fecha, propósito, información designada) y límites según Art. 35.

---

## 9. Resumen: qué tenemos y qué mejorar

| Tema | Tenemos | Mejorar |
|------|---------|---------|
| Alta/baja padrón PLD | ✅ VAL-PLD-001, config, revalidación | Flujo explícito de baja; opcional notificación SAT 10 días |
| Responsable PLD | ✅ VAL-PLD-003 | **Capacitación anual REC** (fecha, vigencia, alerta) |
| Representación legal | ✅ VAL-PLD-004 | — |
| Expediente (integración y actualización) | ✅ VAL-PLD-005, 006; validación por tipo | **Checklist por Anexo (3, 4, 4 Bis, 5, 6…)**; cotejo contra original; opcional conservación física |
| Bajo riesgo / expediente simplificado | ⚠️ Solo nivel_riesgo genérico | **Clasificación “bajo riesgo” + expediente simplificado (Art. 17/12)**; documento Art. 37 |
| Avisos y umbrales | ✅ VAL-PLD-008 a 012 | VAL-PLD-023 (aviso modificatorio 30 días) |
| Conservación y visitas | ✅ VAL-PLD-013, 014 | Requerimientos autoridad; comprobantes/comercial |
| Confidencialidad / reserva | ⚠️ Implícito | **Documento Art. 37** versionado; política confidencialidad; etiquetado “reservado PLD” si se desea |
| Medios electrónicos (Art. 18 RCG) | ✅ Expediente previo | Mecanismos reforzados y trazabilidad identidad–documento–operación (opcional) |
| Intercambio información (Art. 35) | ❌ | Solo si aplica a tu operación |

---

## 10. Priorización sugerida de mejoras

1. **Alta prioridad (cumplimiento directo)**  
   - **Capacitación anual del REC** (Art. 20 / RCG 10): campos + validación + pantalla.  
   - **Expediente por Anexo**: checklist de datos y documentos según tipo de persona (Anexos 3, 4, 4 Bis, 5, 6) en validación de expediente.  
   - **Documento Art. 37**: versión, fecha, archivo; y opcional “criterios de bajo riesgo” para habilitar expediente simplificado.

2. **Prioridad media**  
   - Clasificación “bajo riesgo” y expediente simplificado (Art. 17 + 12): flags, reglas y migración a expediente completo.  
   - VAL-PLD-023: aviso modificatorio (una vez, 30 días).  
   - Flujo de “solicitud de baja al padrón” y recordatorio de avisos hasta que surta efecto.  
   - Cotejo de copias contra original (campo o paso en flujo de documentos).

3. **Prioridad baja / opcional**  
   - Confidencialidad (texto de política + aceptación).  
   - Registro de requerimientos de autoridad (más allá de visitas).  
   - Intercambio de información (Art. 35) solo si aplica.  
   - Trazabilidad reforzada para operaciones por medios electrónicos.

Con esto, el documento de obligaciones queda cruzado con el código y las mejoras quedan identificadas y priorizadas. Si quieres, el siguiente paso puede ser implementar una de las mejoras de alta prioridad (por ejemplo, capacitación anual REC o checklist por Anexo) en código concreto.

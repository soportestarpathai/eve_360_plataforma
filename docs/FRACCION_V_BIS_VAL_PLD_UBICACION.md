# Fracción V Bis — Recepción de Recursos para Desarrollo Inmobiliario
## Dónde se encuentra cada VAL-PLD

Fuente: LFPIORPI, Reglamento, RCG, Portal PLD SAT (SPPLD).

---

## CATEGORÍA A — HABILITACIÓN Y REGISTRO

| VAL | Nombre | Dónde está |
|-----|--------|------------|
| **VAL-PLD-001** | Validación de Alta en el Padrón PLD (Fracción V Bis activa) | **`config/pld_validation.php`** → `validatePatronPLD($pdo, $fraccionRequerida)`. Para V Bis: **`config/pld_fraccion_v_bis.php`** → `requireFraccionVBisActiva($pdo)` (llama a `validatePatronPLD($pdo, 'V Bis')`). Resultado: Falla → NO_HABILITADO_PLD; Baja → bloqueo operativo. |
| **VAL-PLD-002** | Revalidación Periódica de Alta (trimestral) | **`config/pld_revalidation.php`** → `checkRevalidacionDue($pdo)`, **`config/pld_validation.php`** → `revalidatePatronPLD($pdo)`. Detección de cambios de estatus o fracción. Baja confirmada → bloqueo operativo. |

---

## CATEGORÍA B — GOBERNANZA Y RESPONSABILIDAD

| VAL | Nombre | Dónde está |
|-----|--------|------------|
| **VAL-PLD-003** | Designación de Responsable PLD (personas morales y fideicomisos) | **`config/pld_responsable_validation.php`** → `validateResponsablePLD($pdo, $id_cliente)`. Uso: **`config/pld_middleware.php`**, **`admin/config.php`**, **`api/designar_responsable_pld.php`**. No registrado → RESTRICCION_USUARIO. |
| **VAL-PLD-004** | Representación Legal del Usuario | **`config/pld_representacion_legal.php`** → `validateRepresentacionLegal($pdo, $id_usuario, $id_cliente)`. Usuario con rol: representante legal, apoderado, usuario autorizado; evidencia documental. Falta evidencia → bloqueo de acción. |

---

## CATEGORÍA C — IDENTIFICACIÓN Y EXPEDIENTES

| VAL | Nombre | Dónde está |
|-----|--------|------------|
| **VAL-PLD-005** | Integración de Expediente del Cliente/Usuario que aporta recursos (V Bis: **siempre** obligatorio) | **`config/pld_expediente.php`** → `validateExpedienteCompleto($pdo, $id_cliente)`. Para V Bis: **`config/pld_fraccion_v_bis.php`** → `requiereExpedienteVBis()` (siempre `true`). Uso: `requireExpedienteCompleto()` antes de recibir recursos. Incompleto → IDENTIFICACION_INCOMPLETA. |
| **VAL-PLD-006** | Actualización Anual del Expediente | **`config/pld_expediente.php`** → `validateActualizacionExpediente($pdo, $id_cliente)` y dentro de `requireExpedienteCompleto()`. Vencido → bloqueo de nuevas operaciones. |
| **VAL-PLD-007** | Identificación del Beneficiario Controlador | **`config/pld_beneficiario_controlador.php`** → `validateBeneficiarioControlador($pdo, $id_cliente)`. Persona moral/fideicomiso → documentación; persona física → declaración expresa; constancia firmada. No identificado → bloqueo de operación. |
| **VAL-PLD-026** | Negativa de Identificación | **`config/pld_expediente.php`** → `hasNegativaIdentificacion($pdo, $id_cliente)`, `requireNoNegativaIdentificacion($pdo, $id_cliente, $returnJson)`. Campos en **`clientes`**: `negativa_identificacion_pld`, `fecha_negativa_identificacion_pld`, `evidencia_negativa_identificacion_pld` (migración **`db/migrations/add_negativa_identificacion_pld.sql`**). Resultado: OPERACION_RECHAZADA_PLD (no se recibe el recurso). |

---

## CATEGORÍA D — AVISOS Y UMBRALES

| VAL | Nombre | Dónde está |
|-----|--------|------------|
| **VAL-PLD-008** | Aviso por Umbral Individual (≥ 8,025 UMA) | **`config/pld_avisos.php`** → `validateAvisoUmbralIndividual($pdo, $id_cliente, $monto, $id_fraccion)`. Con `id_fraccion` de V Bis ( **`config/pld_fraccion_v_bis.php`** → `getIdVulnerableFraccionVBis($pdo)` ) se usa umbral 8,025. Migración **`db/migrations/add_umbrales_fraccion_v_bis.sql`**. Resultado: AVISO_REQUERIDO; límite día 17 del mes siguiente. |
| **VAL-PLD-009** | Aviso por Acumulación (6 meses, ≥ 8,025 UMA) | **`config/pld_avisos.php`** → `validateAvisoAcumulacion(..., $id_fraccion)`. Con `id_fraccion` V Bis. Resultado: GENERAR_AVISO. |
| **VAL-PLD-010** | Aviso por Operación Sospechosa (24 h) | **`config/pld_avisos.php`** → `validateAvisoSospechosa($pdo, $id_cliente, $fechaConocimiento)`. Resultado: AVISO_24H. |
| **VAL-PLD-011** | Aviso por Listas Restringidas | **`config/pld_avisos.php`** → `validateAvisoListasRestringidas($pdo, $id_cliente, $fechaConocimiento)`. Match UIF/ONU. Resultado: AVISO_24H. |
| **VAL-PLD-012** | Informe de No Operaciones | **`config/pld_avisos.php`** → `validateInformeNoOperaciones($pdo, $mes, $anio)`. Uso: **`api/registrar_informe_no_operaciones.php`**, **`operaciones_pld.php`**. No presentado → INCUMPLIMIENTO_PLD. |
| **VAL-PLD-023** | Aviso Modificatorio (una modificación en 30 días del acuse) | *Pendiente de implementación*: en **`avisos_pld`** control de “modificado” y plazo 30 días; validar en flujo de modificación. Resultado fuera de plazo: MODIFICACION_NO_PERMITIDA. |

---

## CATEGORÍA E — CONSERVACIÓN Y AUDITORÍA

| VAL | Nombre | Dónde está |
|-----|--------|------------|
| **VAL-PLD-013** | Conservación de Información (10 años) | **`config/pld_conservacion.php`** → `validateConservacionInformacion()`, `registrarConservacion()`. Expedientes, avisos e informes, evidencia soporte. Falta evidencia → EXPEDIENTE_INCOMPLETO. |
| **VAL-PLD-014** | Atención a Visitas de Verificación | **`config/pld_conservacion.php`** → `validateExpedientesDisponibles()`, `registrarVisitaVerificacion()`, `registrarEventoCriticoPLD()`. Acceso a expedientes, evidencia disponible. No disponible → evento crítico. Tabla: **`db/migrations/add_eventos_criticos_pld.sql`**. |
| **VAL-PLD-019** | Atención a Requerimientos de Autoridad (beneficiario controlador) | Consistente con **`config/pld_conservacion.php`** y **`config/pld_beneficiario_controlador.php`**. Información disponible, evidencia conservada. No atendido → INCUMPLIMIENTO_REQUERIMIENTO_AUTORIDAD. |

---

## CATEGORÍA F — BENEFICIARIO CONTROLADOR (SOCIEDADES)

| VAL | Nombre | Dónde está |
|-----|--------|------------|
| **VAL-PLD-015** | Identificación y Registro del Beneficiario Controlador | **`config/pld_beneficiario_controlador.php`** → `validateBeneficiarioControlador($pdo, $id_cliente)`. Registro completo, evidencia soporte, actualización vigente. Falta → bloqueo y observación regulatoria. |
| **VAL-PLD-020** | Registro en Sistemas Electrónicos Oficiales | *Pendiente de implementación*: registrar BC en sistemas externos cuando aplique; consistencia con expediente interno. Falta registro → OBSERVACION_REGULATORIA. |

---

## CATEGORÍA H — PREVENCIÓN DE SANCIONES

| VAL | Nombre | Dónde está |
|-----|--------|------------|
| **VAL-PLD-028** | Detección de Riesgo Sancionable | **`config/pld_fraccion_v_bis.php`** → `registrarRiesgoSancionableVBis($pdo, $trigger, $detalle)`. Triggers: omisión de avisos, aviso extemporáneo, aviso sin formalidades, incumplimiento art. 33 Bis/33 Ter. Actualiza **`config_empresa.riesgo_sancion_administrativa`** = 1 (campo en **`add_pld_validations_fields.sql`**). Resultado: RIESGO_SANCION_ADMINISTRATIVA; escalamiento interno. |

---

## Resumen por archivo (Fracción V Bis)

| Archivo | VAL-PLD |
|---------|---------|
| **`config/pld_fraccion_v_bis.php`** | 001 (Fracción V Bis activa), 005 (expediente siempre), 008/009 umbral 8025, **028** (riesgo sancionable) |
| **`config/pld_validation.php`** | 001 (padrón + fracción) |
| **`config/pld_revalidation.php`** | 002 |
| **`config/pld_responsable_validation.php`** | 003 |
| **`config/pld_representacion_legal.php`** | 004 |
| **`config/pld_expediente.php`** | 005, 006, **026** (negativa identificación) |
| **`config/pld_beneficiario_controlador.php`** | 007, 015 |
| **`config/pld_avisos.php`** | 008, 009, 010, 011, 012 |
| **`config/pld_conservacion.php`** | 013, 014, 019 |
| **`db/migrations/add_umbrales_fraccion_v_bis.sql`** | Umbrales 8,025 UMA para Fracción V Bis |
| **`db/migrations/add_negativa_identificacion_pld.sql`** | Campos VAL-PLD-026 en `clientes` |

**Uso recomendado en flujo de recepción de recursos (V Bis):**

1. `require_once 'config/pld_fraccion_v_bis.php';`
2. `requireFraccionVBisActiva($pdo)` — validar padrón con Fracción V Bis activa.
3. `requireExpedienteCompleto($pdo, $id_cliente)` — expediente siempre obligatorio.
4. `requireNoNegativaIdentificacion($pdo, $id_cliente)` — VAL-PLD-026.
5. Registrar operación en `operaciones_pld` con `id_fraccion = getIdVulnerableFraccionVBis($pdo)` para que avisos usen umbral 8,025 UMA.
6. Ante omisión de aviso, aviso extemporáneo, sin formalidades o incumplimiento art. 33 Bis/33 Ter: `registrarRiesgoSancionableVBis($pdo, 'Omisión de avisos', $detalle)` (o el trigger que aplique).

Pendiente de implementación explícita: **VAL-PLD-023** (aviso modificatorio 30 días), **VAL-PLD-020** (registro BC en sistemas electrónicos oficiales).

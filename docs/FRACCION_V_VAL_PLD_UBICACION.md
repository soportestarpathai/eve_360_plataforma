# Fracción V — Desarrollo · Comercialización · Intermediación de Inmuebles
## Dónde se encuentra cada VAL-PLD

Fuente: LFPIORPI, Reglamento, RCG, Portal PLD SAT (SPPLD).

---

## CATEGORÍA A — HABILITACIÓN Y REGISTRO

| VAL | Nombre | Dónde está |
|-----|--------|------------|
| **VAL-PLD-001** | Validación de Alta en el Padrón PLD (Fracción V activa) | **`config/pld_validation.php`** → `validatePatronPLD($pdo, $fraccionRequerida)`. Para inmobiliario: **`config/pld_fraccion_v.php`** → `requireFraccionVActiva($pdo)` (llama a `validatePatronPLD($pdo, 'V')`). |
| **VAL-PLD-002** | Revalidación Periódica de Alta (cada 3 meses; modalidad Desarrollo/Comercialización/Intermediación) | **`config/pld_revalidation.php`** → `checkRevalidacionDue($pdo)`, `revalidatePatronPLD($pdo)` en **`config/pld_validation.php`**. La detección de cambios de modalidad puede extenderse en revalidación según `fracciones_activas` en `config_empresa`. |

---

## CATEGORÍA B — GOBERNANZA Y RESPONSABILIDAD

| VAL | Nombre | Dónde está |
|-----|--------|------------|
| **VAL-PLD-003** | Designación de Responsable PLD | **`config/pld_responsable_validation.php`** → `validateResponsablePLD($pdo, $id_cliente)`. Uso: **`config/pld_middleware.php`**, **`admin/config.php`**, **`api/designar_responsable_pld.php`**. |
| **VAL-PLD-004** | Representación Legal del Usuario | **`config/pld_representacion_legal.php`** → `validateRepresentacionLegal($pdo, $id_usuario, $id_cliente)`. Uso: **`config/pld_middleware.php`**, **`admin/representacion_legal.php`**. |

---

## CATEGORÍA C — IDENTIFICACIÓN Y EXPEDIENTES

| VAL | Nombre | Dónde está |
|-----|--------|------------|
| **VAL-PLD-005** | Integración de Expediente del Cliente/Usuario (Fracción V: **siempre** obligatorio) | **`config/pld_expediente.php`** → `validateExpedienteCompleto($pdo, $id_cliente)`. Para Fracción V: **`config/pld_fraccion_v.php`** → `requiereExpedienteInmobiliario()` (siempre `true`). Uso: `requireExpedienteCompleto()` antes de operaciones inmobiliarias. |
| **VAL-PLD-006** | Actualización Anual del Expediente | **`config/pld_expediente.php`** → `validateActualizacionExpediente($pdo, $id_cliente)` y dentro de `requireExpedienteCompleto()`. |
| **VAL-PLD-007** | Identificación de Beneficiario Controlador | **`config/pld_beneficiario_controlador.php`** → `validateBeneficiarioControlador($pdo, $id_cliente)`. |
| **VAL-PLD-026** | Negativa de Identificación del Cliente/Usuario | **`config/pld_expediente.php`** → `hasNegativaIdentificacion($pdo, $id_cliente)`, `requireNoNegativaIdentificacion($pdo, $id_cliente, $returnJson)`. Campos: **`clientes.negativa_identificacion_pld`**, `fecha_negativa_identificacion_pld`, `evidencia_negativa_identificacion_pld` → migración **`db/migrations/add_negativa_identificacion_pld.sql`**. Resultado: bloqueo → `OPERACION_RECHAZADA_PLD`. |

---

## CATEGORÍA D — AVISOS Y UMBRALES

| VAL | Nombre | Dónde está |
|-----|--------|------------|
| **VAL-PLD-008** | Aviso por Umbral Individual (≥ 8,025 UMA) | **`config/pld_avisos.php`** → `validateAvisoUmbralIndividual($pdo, $id_cliente, $monto, $id_fraccion)`. Umbral 8,025 para Fracción V: **`db/migrations/add_umbrales_fraccion_v.sql`** y **`config/pld_fraccion_v.php`** → `getUmbralAvisoInmobiliario()`. |
| **VAL-PLD-009** | Aviso por Acumulación (6 meses, ≥ 8,025 UMA) | **`config/pld_avisos.php`** → `validateAvisoAcumulacion(..., $id_fraccion)`. Con `id_fraccion` de Fracción V se usa umbral 8,025 desde `cat_vulnerables`. |
| **VAL-PLD-010** | Aviso por Operación Sospechosa (24 h) | **`config/pld_avisos.php`** → `validateAvisoSospechosa($pdo, $id_cliente, $fechaConocimiento)`. Uso en **`registrarOperacionPLD()`**. |
| **VAL-PLD-011** | Aviso por Listas Restringidas (24 h) | **`config/pld_avisos.php`** → `validateAvisoListasRestringidas($pdo, $id_cliente, $fechaConocimiento)`. |
| **VAL-PLD-012** | Informe de No Operaciones | **`config/pld_avisos.php`** → `validateInformeNoOperaciones($pdo, $mes, $anio)`. Uso: **`api/registrar_informe_no_operaciones.php`**, **`operaciones_pld.php`**. |
| **VAL-PLD-016** | Excepción Primera Venta con Sistema Financiero | *Pendiente de implementación específica*: validar primera venta del inmueble + recursos de banca de desarrollo/organismos públicos/sistema financiero (pago total) → `AVISO_NO_REQUERIDO`. Lógica puede añadirse en **`config/pld_avisos.php`** o módulo inmobiliario. |
| **VAL-PLD-022** | Aviso por Acumulación Inmobiliaria | Misma lógica que VAL-PLD-009 (acumulación 6 meses, 8,025 UMA); implementado en **`config/pld_avisos.php`** → `validateAvisoAcumulacion()` con `id_fraccion` V. |
| **VAL-PLD-023** | Aviso Modificatorio Inmobiliario (una modificación en 30 días del acuse) | *Pendiente de implementación*: en **`avisos_pld`** añadir control de “modificado” y plazo 30 días; validar en flujo de modificación de avisos. |

---

## CATEGORÍA E — CONSERVACIÓN Y AUDITORÍA

| VAL | Nombre | Dónde está |
|-----|--------|------------|
| **VAL-PLD-013** | Conservación de Información (10 años) | **`config/pld_conservacion.php`** → `validateConservacionInformacion()`, `registrarConservacion()`. Uso: **`config/pld_middleware.php`**, **`config/pld_avisos.php`** (registro en `registrarOperacionPLD`). |
| **VAL-PLD-014** | Atención a Visitas de Verificación | **`config/pld_conservacion.php`** → `validateExpedientesDisponibles()`, `registrarVisitaVerificacion()`, `registrarEventoCriticoPLD()`. Tabla: **`db/migrations/add_eventos_criticos_pld.sql`**. |
| **VAL-PLD-018** | Atención a Visitas (Refuerzo) | Mismo flujo que VAL-PLD-014; evento crítico cuando no atendido → **`config/pld_conservacion.php`** → `registrarEventoCriticoPLD()` con tipo apropiado. |
| **VAL-PLD-019** | Atención a Requerimientos (Beneficiario Controlador) | Atender requerimientos de autoridad sobre BC; consistente con conservación y **`config/pld_beneficiario_controlador.php`** (evidencia y actualización). *Refuerzo operativo/documental según procedimiento interno.* |
| **VAL-PLD-025** | Detección de Riesgo Sancionable | Flags en **`config_empresa`**: `aviso_requerido`, `aviso_24h`, `incumplimiento_pld`, `riesgo_sancion_administrativa` (migración **`add_pld_validations_fields.sql`**). Activar `riesgo_sancion_administrativa` cuando: aviso omitido, extemporáneo, sin formalidades, uso de efectivo prohibido. Lógica puede centralizarse en **`config/pld_avisos.php`** o en flujo de operaciones. |

---

## CATEGORÍA F — BENEFICIARIO CONTROLADOR

| VAL | Nombre | Dónde está |
|-----|--------|------------|
| **VAL-PLD-015** | Identificación y Registro del Beneficiario Controlador | **`config/pld_beneficiario_controlador.php`** → `validateBeneficiarioControlador($pdo, $id_cliente)`. |
| **VAL-PLD-020** | Registro de BC en Sistemas Externos | *Pendiente de implementación*: consistencia entre expediente interno y registro externo; campo o tabla de “registro externo realizado” y validación en flujo de BC. |

---

## CATEGORÍA G — RESTRICCIÓN DE EFECTIVO (Fracción V)

| VAL | Nombre | Dónde está |
|-----|--------|------------|
| **VAL-PLD-027** | Prohibición de Operaciones en Efectivo o Metales Preciosos (≥ 8,025 UMA) | **`config/pld_fraccion_v.php`** → `validateProhibicionEfectivoInmobiliario($monto_uma, $forma_pago)`. Constantes: `PLD_FRACCION_V_UMA_RESTRICCION_EFECTIVO` = 8025, `PLD_FRACCION_V_FORMAS_PAGO_PROHIBIDAS`. Resultado: `permitido === false` → `RESTRICCION_EFECTIVO`; operación no ejecutable. |

---

## Resumen por archivo (Fracción V)

| Archivo | VAL-PLD |
|---------|---------|
| **`config/pld_fraccion_v.php`** | 001 (Fracción V activa), 005 (expediente siempre), 008/009 umbral 8025, **027** (prohibición efectivo) |
| **`config/pld_validation.php`** | 001 (padrón + fracción) |
| **`config/pld_revalidation.php`** | 002 |
| **`config/pld_responsable_validation.php`** | 003 |
| **`config/pld_representacion_legal.php`** | 004 |
| **`config/pld_expediente.php`** | 005, 006, **026** (negativa identificación) |
| **`config/pld_beneficiario_controlador.php`** | 007, 015 |
| **`config/pld_avisos.php`** | 008, 009, 010, 011, 012 (022 mismo que 009) |
| **`config/pld_conservacion.php`** | 013, 014, 018 |
| **`db/migrations/add_umbrales_fraccion_v.sql`** | Umbrales 8,025 UMA para Fracción V |
| **`db/migrations/add_negativa_identificacion_pld.sql`** | Campos VAL-PLD-026 en `clientes` |

Implementación pendiente explícita: **VAL-PLD-016** (excepción primera venta), **VAL-PLD-023** (aviso modificatorio 30 días), **VAL-PLD-020** (registro BC externo). VAL-PLD-025 (riesgo sancionable) está soportado por flags en `config_empresa`; falta centralizar los triggers en el flujo de avisos/operaciones.

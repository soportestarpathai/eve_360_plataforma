# Fracción VI — Metales preciosos, piedras preciosas, joyas o relojes
## Dónde se encuentra cada VAL-PLD

Fuente: LFPIORPI, Reglamento, RCG, Portal PLD SAT (SPPLD). Aplicación del Tronco Común PLD con validaciones de producto.

---

## CATEGORÍA A — HABILITACIÓN Y REGISTRO

| VAL | Nombre | Dónde está |
|-----|--------|------------|
| **VAL-PLD-001** | Validación de Alta en el Padrón PLD (Fracción VI activa) | **`config/pld_validation.php`** → `validatePatronPLD($pdo, $fraccionRequerida)`. Para VI: **`config/pld_fraccion_vi.php`** → `requireFraccionVIActiva($pdo)` (llama a `validatePatronPLD($pdo, 'VI')`). Registro en padrón, estatus vigente, Fracción VI asociada. Falla → NO_HABILITADO_PLD; Baja → bloqueo de consultas y envío de avisos. |
| **VAL-PLD-002** | Revalidación Periódica de Alta (trimestral) | **`config/pld_revalidation.php`** → `checkRevalidacionDue($pdo)`, **`config/pld_validation.php`** → `revalidatePatronPLD($pdo)`. Detección de cambios de estatus o fracción. Baja confirmada → bloqueo operativo. |

---

## CATEGORÍA B — GOBERNANZA Y RESPONSABILIDAD

| VAL | Nombre | Dónde está |
|-----|--------|------------|
| **VAL-PLD-003** | Designación de Responsable PLD (personas morales y fideicomisos) | **`config/pld_responsable_validation.php`** → `validateResponsablePLD($pdo, $id_cliente)`. Responsable registrado, designación vigente. No registrado → RESTRICCION_USUARIO. |
| **VAL-PLD-004** | Representación Legal del Usuario | **`config/pld_representacion_legal.php`** → `validateRepresentacionLegal($pdo, $id_usuario, $id_cliente)`. Usuario con rol: representante legal, apoderado, usuario autorizado; evidencia documental cargada. Falta evidencia → bloqueo de acción. |

---

## CATEGORÍA C — IDENTIFICACIÓN Y EXPEDIENTES

| VAL | Nombre | Dónde está |
|-----|--------|------------|
| **VAL-PLD-005** | Integración de Expediente del Cliente/Usuario (Fracción VI: obligatorio cuando **monto_operacion ≥ 805 UMA**) | **`config/pld_expediente.php`** → `validateExpedienteCompleto($pdo, $id_cliente)`. Para VI: **`config/pld_fraccion_vi.php`** → `requiereExpedienteMetalesJoyas($monto_uma)` (true cuando monto ≥ 805 UMA). Umbral en **`db/migrations/add_umbrales_fraccion_vi.sql`** (`umbral_expediente_uma` = 805). Incompleto → IDENTIFICACION_INCOMPLETA. |
| **VAL-PLD-006** | Actualización Anual del Expediente | **`config/pld_expediente.php`** → `validateActualizacionExpediente($pdo, $id_cliente)` y dentro de `requireExpedienteCompleto()`. En relaciones de negocios, última actualización ≤ 12 meses. Vencido → bloqueo de nuevas operaciones. |
| **VAL-PLD-007** | Identificación del Beneficiario Controlador | **`config/pld_beneficiario_controlador.php`** → `validateBeneficiarioControlador($pdo, $id_cliente)`. Persona moral/fideicomiso → documentación; persona física → declaración expresa; constancia firmada. No identificado → bloqueo de operación. |
| **VAL-PLD-026** | Negativa de Identificación | **`config/pld_expediente.php`** → `hasNegativaIdentificacion($pdo, $id_cliente)`, `requireNoNegativaIdentificacion($pdo, $id_cliente, $returnJson)`. Negativa registrada, evidencia de solicitud. Resultado: OPERACION_RECHAZADA_PLD. Campos en **`clientes`** (migración **`add_negativa_identificacion_pld.sql`**). |

---

## CATEGORÍA D — AVISOS Y UMBRALES

| VAL | Nombre | Dónde está |
|-----|--------|------------|
| **VAL-PLD-008** | Aviso por Umbral Individual (≥ 1,605 UMA) | **`config/pld_avisos.php`** → `validateAvisoUmbralIndividual($pdo, $id_cliente, $monto, $id_fraccion)`. Con `id_fraccion` = **`config/pld_fraccion_vi.php`** → `getIdVulnerableFraccionVI($pdo)` se usa umbral 1,605 desde `cat_vulnerables`. Migración **`add_umbrales_fraccion_vi.sql`**. Resultado: AVISO_REQUERIDO; límite día 17 del mes siguiente (facilidad administrativa por RFC). |
| **VAL-PLD-009** | Aviso por Acumulación (6 meses; solo actos en supuestos de identificación) | **`config/pld_avisos.php`** → `validateAvisoAcumulacion(..., $id_fraccion)`. Suma acumulada ≥ umbral de aviso (1,605 UMA), ventana móvil 6 meses. Considerar únicamente actos que se ubiquen en supuestos de identificación (≥ 805 UMA). Resultado: GENERAR_AVISO. |
| **VAL-PLD-010** | Aviso por Operación Sospechosa (24 h) | **`config/pld_avisos.php`** → `validateAvisoSospechosa($pdo, $id_cliente, $fechaConocimiento)`. Marca de sospecha, fecha de conocimiento. Resultado: AVISO_24H. |
| **VAL-PLD-011** | Aviso por Listas Restringidas | **`config/pld_avisos.php`** → `validateAvisoListasRestringidas($pdo, $id_cliente, $fechaConocimiento)`. Match UIF/ONU. Resultado: AVISO_24H. |
| **VAL-PLD-012** | Informe de No Operaciones | **`config/pld_avisos.php`** → `validateInformeNoOperaciones($pdo, $mes, $anio)`. Periodo sin operaciones avisables, fecha límite válida. No presentado → INCUMPLIMIENTO_PLD. |
| **VAL-PLD-023** | Aviso Modificatorio (una modificación en 30 días del acuse) | *Pendiente de implementación*: aviso original existente, no modificado previamente, dentro de 30 días del acuse. Fuera de plazo → MODIFICACION_NO_PERMITIDA. |

---

## CATEGORÍA E — CONSERVACIÓN Y AUDITORÍA

| VAL | Nombre | Dónde está |
|-----|--------|------------|
| **VAL-PLD-013** | Conservación de Información (10 años) | **`config/pld_conservacion.php`** → `validateConservacionInformacion()`, `registrarConservacion()`. Expedientes de clientes/usuarios, avisos e informes, comprobantes de pago y documentación comercial. Falta evidencia → EXPEDIENTE_INCOMPLETO. |
| **VAL-PLD-014** | Atención a Visitas de Verificación | **`config/pld_conservacion.php`** → `validateExpedientesDisponibles()`, `registrarVisitaVerificacion()`, `registrarEventoCriticoPLD()`. Acceso a expedientes, evidencia disponible. No disponible → evento crítico. Tabla: **`db/migrations/add_eventos_criticos_pld.sql`**. |
| **VAL-PLD-019** | Atención a Requerimientos de Autoridad (beneficiario controlador) | Consistente con **`config/pld_conservacion.php`** y **`config/pld_beneficiario_controlador.php`**. Información disponible, evidencia conservada. No atendido → INCUMPLIMIENTO_REQUERIMIENTO_AUTORIDAD. |

---

## CATEGORÍA F — BENEFICIARIO CONTROLADOR (SOCIEDADES)

| VAL | Nombre | Dónde está |
|-----|--------|------------|
| **VAL-PLD-015** | Identificación y Registro del Beneficiario Controlador | **`config/pld_beneficiario_controlador.php`** → `validateBeneficiarioControlador($pdo, $id_cliente)`. Sociedades mercantiles: registro completo, evidencia soporte, actualización vigente. Falta → bloqueo y observación regulatoria. |
| **VAL-PLD-020** | Registro del BC en Sistemas Externos | *Pendiente de implementación*: registrar información del BC en sistemas electrónicos oficiales cuando aplique; registro externo realizado, consistencia con expediente interno. Falta → OBSERVACION_REGULATORIA. |

---

## CATEGORÍA G — RESTRICCIÓN DE EFECTIVO

| VAL | Nombre | Dónde está |
|-----|--------|------------|
| **VAL-PLD-027** | Prohibición de Operaciones en Efectivo o Metales Preciosos (≥ 3,210 UMA) | **`config/pld_fraccion_vi.php`** → `validateProhibicionEfectivoMetalesJoyas($monto_uma, $forma_pago)`. Constantes: `PLD_FRACCION_VI_UMA_RESTRICCION_EFECTIVO` = 3210, `PLD_FRACCION_VI_FORMAS_PAGO_PROHIBIDAS`. Validaciones: monto_operacion ≥ 3,210 UMA y forma de pago = efectivo / metales preciosos. Resultado: RESTRICCION_EFECTIVO; operación no ejecutable. |

---

## CATEGORÍA H — PREVENCIÓN DE SANCIONES

| VAL | Nombre | Dónde está |
|-----|--------|------------|
| **VAL-PLD-028** | Detección de Riesgo Sancionable | **`config/pld_fraccion_vi.php`** → `registrarRiesgoSancionableVI($pdo, $trigger, $detalle)`. Triggers: omisión de avisos, aviso extemporáneo, aviso sin formalidades, incumplimiento art. 33, 33 Bis y 33 Ter, operaciones prohibidas (art. 32 LFPIORPI). Actualiza **`config_empresa.riesgo_sancion_administrativa`** = 1. Resultado: RIESGO_SANCION_ADMINISTRATIVA; escalamiento interno; posible suspensión temporal de operaciones. |

---

## Resumen por archivo (Fracción VI)

| Archivo | VAL-PLD |
|---------|---------|
| **`config/pld_fraccion_vi.php`** | 001 (Fracción VI activa), 005 (expediente ≥ 805 UMA), 008/009 umbral 1605, **027** (prohibición efectivo ≥ 3210), **028** (riesgo sancionable) |
| **`config/pld_validation.php`** | 001 (padrón + fracción) |
| **`config/pld_revalidation.php`** | 002 |
| **`config/pld_responsable_validation.php`** | 003 |
| **`config/pld_representacion_legal.php`** | 004 |
| **`config/pld_expediente.php`** | 005, 006, **026** (negativa identificación) |
| **`config/pld_beneficiario_controlador.php`** | 007, 015 |
| **`config/pld_avisos.php`** | 008, 009, 010, 011, 012 |
| **`config/pld_conservacion.php`** | 013, 014, 019 |
| **`db/migrations/add_umbrales_fraccion_vi.sql`** | Umbrales: expediente 805, aviso/acumulación 1605 UMA para Fracción VI |
| **`db/migrations/add_negativa_identificacion_pld.sql`** | Campos VAL-PLD-026 en `clientes` |

**Uso recomendado en flujo Fracción VI (metales, joyas, relojes):**

1. `require_once 'config/pld_fraccion_vi.php';`
2. `requireFraccionVIActiva($pdo)` — validar padrón con Fracción VI activa.
3. Calcular `monto_uma` (monto / valor UMA). Si `requiereExpedienteMetalesJoyas($monto_uma)` → `requireExpedienteCompleto($pdo, $id_cliente)`.
4. `requireNoNegativaIdentificacion($pdo, $id_cliente)` — VAL-PLD-026.
5. `validateProhibicionEfectivoMetalesJoyas($monto_uma, $forma_pago)` — VAL-PLD-027; si `!$result['permitido']` → bloquear operación (RESTRICCION_EFECTIVO).
6. Registrar en `operaciones_pld` con `id_fraccion = getIdVulnerableFraccionVI($pdo)` para avisos con umbral 1,605 UMA.
7. Ante triggers de VAL-PLD-028: `registrarRiesgoSancionableVI($pdo, 'Omisión de avisos', $detalle)` (u otro trigger: aviso extemporáneo, sin formalidades, incumplimiento art. 33/33 Bis/33 Ter, operaciones prohibidas art. 32).

Pendiente de implementación explícita: **VAL-PLD-023** (aviso modificatorio 30 días), **VAL-PLD-020** (registro BC en sistemas externos).

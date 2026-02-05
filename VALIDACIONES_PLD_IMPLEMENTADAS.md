# Validaciones PLD Implementadas

Este documento describe todas las validaciones PLD (Prevención de Lavado de Dinero) implementadas en el sistema EVE 360.

## Resumen de Validaciones

### CATEGORÍA A — HABILITACIÓN Y REGISTRO

#### ✅ VAL-PLD-001 | Validación de Alta en el Padrón PLD
- **Archivo**: `config/pld_validation.php`
- **Estado**: ✅ Implementado
- **Descripción**: Valida que el sujeto obligado esté dado de alta en el Portal PLD del SAT
- **Validaciones**:
  - Existe registro en el padrón (folio)
  - Estatus vigente
  - Fracciones activas asociadas
- **Resultado**: Falla → `NO_HABILITADO_PLD`

#### ✅ VAL-PLD-002 | Revalidación Periódica de Alta
- **Archivo**: `config/pld_revalidation.php`
- **Estado**: ✅ Implementado
- **Descripción**: Revalidación cada 3 meses del estatus en el padrón
- **Validaciones**:
  - Solicitud de confirmación cada 3 meses
  - Detección de cambios de estatus o fracción
- **Resultado**: Modificación confirmada → actualizar configuración | Baja confirmada → bloqueo operativo

#### ✅ VAL-PLD-003 | Designación de Responsable PLD
- **Archivo**: `config/pld_responsable_validation.php`
- **Estado**: ✅ Implementado
- **Descripción**: Valida que personas morales y fideicomisos tengan responsable PLD designado
- **Validaciones**:
  - Responsable PLD designado
  - Registrado en el sistema
- **Resultado**: Falta → `RESTRICCION_USUARIO`

### CATEGORÍA B — REPRESENTACIÓN LEGAL

#### ✅ VAL-PLD-004 | Representación Legal del Usuario
- **Archivo**: `config/pld_representacion_legal.php`
- **Estado**: ✅ Implementado
- **Descripción**: Valida que quien actúa en nombre de la entidad tenga facultades documentadas
- **Validaciones**:
  - Usuario registrado como: Representante legal, Apoderado, o Usuario autorizado
  - Evidencia documental cargada
- **Resultado**: Falta de evidencia → bloqueo de acción

### CATEGORÍA C — IDENTIFICACIÓN Y EXPEDIENTES

#### ✅ VAL-PLD-005 | Integración de Expediente de Identificación
- **Archivo**: `config/pld_expediente.php`
- **Estado**: ✅ Implementado
- **Descripción**: Valida que el expediente único esté completo
- **Validaciones**:
  - Datos completos según tipo de persona
  - Documentos requeridos cargados
  - Asociación a la operación (según umbral de fracción)
- **Resultado**: Incompleto → `IDENTIFICACION_INCOMPLETA`

#### ✅ VAL-PLD-006 | Actualización Anual del Expediente
- **Archivo**: `config/pld_expediente.php`
- **Estado**: ✅ Implementado
- **Descripción**: Valida que el expediente se actualice al menos 1 vez al año
- **Validaciones**:
  - Fecha última actualización ≤ 12 meses
- **Resultado**: Vencido → bloqueo de nuevas operaciones

#### ✅ VAL-PLD-007 | Identificación de Beneficiario Controlador
- **Archivo**: `config/pld_beneficiario_controlador.php`
- **Estado**: ✅ Implementado
- **Descripción**: Valida identificación de beneficiario controlador cuando aplique
- **Validaciones**:
  - Persona moral/fideicomiso → documentación obligatoria
  - Persona física → declaración correspondiente
- **Resultado**: No identificado → bloqueo de operación

### CATEGORÍA D — AVISOS Y UMBRALES

#### ✅ VAL-PLD-008 | Aviso por Umbral Individual
- **Archivo**: `config/pld_avisos.php`
- **Estado**: ✅ Implementado
- **Descripción**: Valida operaciones que superen el umbral configurado
- **Validaciones**:
  - Monto ≥ umbral configurado (UMAs)
  - Fecha de operación válida
- **Resultado**: Rebase → `AVISO_REQUERIDO` | Deadline → día 17 del mes siguiente

#### ✅ VAL-PLD-009 | Aviso por Acumulación (6 meses)
- **Archivo**: `config/pld_avisos.php`
- **Estado**: ✅ Implementado
- **Descripción**: Valida acumulación por tipo de acto en ventana móvil de 6 meses
- **Validaciones**:
  - Suma acumulada en ventana de 6 meses
  - Cómputo desde la primera operación
- **Resultado**: Rebase → `GENERAR_AVISO`

#### ✅ VAL-PLD-010 | Aviso por Operación Sospechosa
- **Archivo**: `config/pld_avisos.php`
- **Estado**: ✅ Implementado
- **Descripción**: Valida aviso en 24 horas ante indicios de posible ilícito
- **Validaciones**:
  - Marca de sospecha
  - Registro de fecha de conocimiento
- **Resultado**: Activación → `AVISO_24H`

#### ✅ VAL-PLD-011 | Aviso por Listas Restringidas
- **Archivo**: `config/pld_avisos.php`
- **Estado**: ✅ Implementado
- **Descripción**: Valida aviso cuando hay match en listas restringidas
- **Validaciones**:
  - Match contra listas configuradas
  - Fecha de conocimiento registrada
- **Resultado**: Match → `AVISO_24H`

#### ✅ VAL-PLD-012 | Informe de No Operaciones
- **Archivo**: `config/pld_avisos.php`
- **Estado**: ✅ Implementado
- **Descripción**: Valida presentación de informe cuando no hubo operaciones avisables
- **Validaciones**:
  - Periodo sin operaciones
  - Fecha límite válida (día 17 del mes siguiente)
- **Resultado**: No presentado → `INCUMPLIMIENTO_PLD`

### CATEGORÍA E — CONSERVACIÓN Y AUDITORÍA

#### ✅ VAL-PLD-013 | Conservación de Información
- **Archivo**: `config/pld_conservacion.php`
- **Estado**: ✅ Implementado
- **Descripción**: Valida que la información se conserve por al menos 10 años
- **Validaciones**:
  - Evidencia asociada
  - Plazo vigente (10 años)
  - Cambios y ediciones registrados
- **Resultado**: Falta de evidencia → `EXPEDIENTE_INCOMPLETO`

#### ✅ VAL-PLD-014 | Atención a Visitas de Verificación
- **Archivo**: `config/pld_conservacion.php`
- **Estado**: ✅ Implementado
- **Descripción**: Valida capacidad de atención a requerimientos de autoridad
- **Validaciones**:
  - Acceso a expedientes
  - Evidencia disponible
- **Resultado**: No disponible → evento crítico

### CATEGORÍA F — BENEFICIARIO CONTROLADOR (SOCIEDADES)

#### ✅ VAL-PLD-015 | Identificación y Registro del Beneficiario Controlador
- **Archivo**: `config/pld_beneficiario_controlador.php`
- **Estado**: ✅ Implementado
- **Descripción**: Valida registro completo del beneficiario controlador para sociedades
- **Validaciones**:
  - Registro completo
  - Evidencia soporte
  - Actualización vigente
- **Resultado**: Falta → bloqueo y observación regulatoria

## Uso del Middleware

### Función Centralizada

```php
require_once __DIR__ . '/config/pld_middleware.php';

// Validar todas las reglas PLD antes de una operación
validatePLDOperation($pdo, $id_cliente, $id_usuario, $validaciones, $returnJson);
```

### Parámetros

- `$pdo`: Conexión a la base de datos
- `$id_cliente`: ID del cliente (opcional, si aplica)
- `$id_usuario`: ID del usuario (opcional, si aplica)
- `$validaciones`: Array de códigos de validación a ejecutar (vacío = todas)
- `$returnJson`: Si es `true`, retorna JSON. Si es `false`, lanza excepción

### Ejemplo de Uso

```php
// Validar todas las reglas PLD
try {
    validatePLDOperation($pdo, $id_cliente, $id_usuario);
    // Continuar con la operación
} catch (Exception $e) {
    // Manejar error
}

// Validar solo reglas específicas
validatePLDOperation($pdo, $id_cliente, $id_usuario, ['VAL-PLD-001', 'VAL-PLD-005']);
```

## Funciones Individuales

### VAL-PLD-004: Representación Legal

```php
require_once __DIR__ . '/config/pld_representacion_legal.php';

// Validar representación legal
$result = validateRepresentacionLegal($pdo, $id_usuario, $id_cliente);

// Registrar representación legal
$result = registrarRepresentacionLegal($pdo, [
    'id_usuario' => $id_usuario,
    'id_cliente' => $id_cliente,
    'tipo_representacion' => 'representante_legal',
    'documento_facultades' => $ruta_documento,
    'fecha_vencimiento' => $fecha_vencimiento
]);

// Bloquear si no hay representación
requireRepresentacionLegal($pdo, $id_usuario, $id_cliente);
```

### VAL-PLD-005 y VAL-PLD-006: Expediente

```php
require_once __DIR__ . '/config/pld_expediente.php';

// Validar expediente completo
$result = validateExpedienteCompleto($pdo, $id_cliente);

// Validar actualización anual
$result = validateActualizacionExpediente($pdo, $id_cliente);

// Actualizar fecha de expediente
actualizarFechaExpediente($pdo, $id_cliente);

// Bloquear si expediente incompleto o vencido
requireExpedienteCompleto($pdo, $id_cliente);
```

### VAL-PLD-007 y VAL-PLD-015: Beneficiario Controlador

```php
require_once __DIR__ . '/config/pld_beneficiario_controlador.php';

// Validar beneficiario controlador
$result = validateBeneficiarioControlador($pdo, $id_cliente);

// Registrar beneficiario
$result = registrarBeneficiarioControlador($pdo, [
    'id_cliente' => $id_cliente,
    'tipo_persona' => 'moral',
    'nombre_completo' => 'Nombre Completo',
    'rfc' => 'RFC123456789',
    'documento_identificacion' => $ruta_documento
]);

// Bloquear si no está identificado
requireBeneficiarioControlador($pdo, $id_cliente);
```

### VAL-PLD-008 a VAL-PLD-012: Avisos

```php
require_once __DIR__ . '/config/pld_avisos.php';

// Validar aviso por umbral individual
$result = validateAvisoUmbralIndividual($pdo, $id_cliente, $monto, $id_fraccion);

// Validar aviso por acumulación
$result = validateAvisoAcumulacion($pdo, $id_cliente, $monto, $fecha_operacion, $id_fraccion, $tipo_acto);

// Validar aviso por operación sospechosa
$result = validateAvisoSospechosa($pdo, $id_cliente, $fecha_conocimiento);

// Validar aviso por listas restringidas
$result = validateAvisoListasRestringidas($pdo, $id_cliente, $fecha_conocimiento);

// Validar informe de no operaciones
$result = validateInformeNoOperaciones($pdo, $mes, $anio);
```

### VAL-PLD-013 y VAL-PLD-014: Conservación

```php
require_once __DIR__ . '/config/pld_conservacion.php';

// Registrar conservación
$result = registrarConservacion($pdo, [
    'id_cliente' => $id_cliente,
    'tipo_evidencia' => 'expediente',
    'ruta_evidencia' => $ruta_archivo
]);

// Validar conservación
$result = validateConservacion($pdo, $id_cliente);

// Registrar visita de verificación
$result = registrarVisitaVerificacion($pdo, [
    'fecha_visita' => '2025-02-01',
    'autoridad' => 'SAT',
    'expedientes_solicitados' => [$id_cliente1, $id_cliente2]
]);

// Validar expedientes disponibles
$result = validateExpedientesDisponibles($pdo, [$id_cliente1, $id_cliente2]);
```

## Migraciones de Base de Datos

Ejecutar la migración para crear todas las tablas necesarias:

```sql
SOURCE db/migrations/add_pld_validations_fields.sql;
```

O ejecutar manualmente desde phpMyAdmin o cliente MySQL.

## Integración en Puntos Críticos

Las validaciones deben integrarse en:

1. **Creación de clientes**: `api/save_client.php`
   - VAL-PLD-005, VAL-PLD-006, VAL-PLD-007

2. **Actualización de clientes**: `api/update_client.php`
   - VAL-PLD-005, VAL-PLD-006, VAL-PLD-007

3. **Operaciones PLD**: Cualquier endpoint que registre operaciones
   - VAL-PLD-001, VAL-PLD-003, VAL-PLD-004, VAL-PLD-005, VAL-PLD-006, VAL-PLD-008, VAL-PLD-009

4. **Consultas PLD**: `api/validate_person.php`
   - VAL-PLD-001, VAL-PLD-004

5. **Avisos PLD**: Endpoints de generación de avisos
   - VAL-PLD-008, VAL-PLD-009, VAL-PLD-010, VAL-PLD-011, VAL-PLD-012

## Códigos de Error

- `NO_HABILITADO_PLD`: Sujeto obligado no habilitado
- `RESTRICCION_USUARIO`: Falta responsable PLD
- `FALTA_REPRESENTACION_LEGAL`: Falta representación legal documentada
- `IDENTIFICACION_INCOMPLETA`: Expediente incompleto
- `EXPEDIENTE_VENCIDO`: Expediente requiere actualización
- `BENEFICIARIO_CONTROLADOR_NO_IDENTIFICADO`: Beneficiario controlador no identificado
- `AVISO_REQUERIDO`: Aviso requerido por umbral
- `GENERAR_AVISO`: Aviso requerido por acumulación
- `AVISO_24H`: Aviso 24 horas requerido
- `INCUMPLIMIENTO_PLD`: Incumplimiento PLD
- `EXPEDIENTE_INCOMPLETO`: Falta evidencia para conservación

## Notas Importantes

1. Todas las validaciones son bloqueantes por defecto
2. Las validaciones se ejecutan en cascada (si una falla, se detiene)
3. Los flags se actualizan automáticamente en la base de datos
4. Las fechas de deadline se calculan automáticamente (día 17 del mes siguiente)
5. La conservación es automática al registrar operaciones/avisos

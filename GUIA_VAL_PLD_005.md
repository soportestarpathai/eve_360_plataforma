# Guía: VAL-PLD-005 - Integración de Expediente de Identificación

## ¿Qué es VAL-PLD-005?

La validación VAL-PLD-005 asegura que cada cliente/usuario tenga un expediente único de identificación completo. Este expediente es obligatorio para operaciones PLD y debe contener todos los datos y documentos requeridos.

## ¿Cómo revisar el expediente?

### Opción 1: Panel de Administración

1. **Acceder al panel:**
   - Ve a: `http://localhost:8080/eve_360_plataforma/admin/expedientes_pld.php`
   - O desde el menú lateral: **Administración > Expedientes PLD**

2. **Ver el estado de todos los clientes:**
   - La tabla muestra:
     - **Cliente**: Nombre del cliente
     - **Alias / No. Contrato**: Identificadores
     - **Tipo**: Tipo de persona (Física, Moral, Fideicomiso)
     - **Completitud**: Estado de completitud del expediente
     - **Actualización**: Estado de actualización anual
     - **Estado**: Estado general (Válido, IDENTIFICACION_INCOMPLETA, etc.)

3. **Acciones disponibles:**
   - **Ver**: Ver detalles completos del expediente
   - **Validar**: Ejecutar validación VAL-PLD-005 y VAL-PLD-006
   - **Actualizar Fecha**: Marcar expediente como actualizado hoy

### Opción 2: Validación Programática

```php
require_once __DIR__ . '/config/pld_expediente.php';

// Validar completitud del expediente (VAL-PLD-005)
$result = validateExpedienteCompleto($pdo, $id_cliente);

if ($result['completo']) {
    echo "Expediente completo";
} else {
    echo "Expediente incompleto. Faltantes:";
    print_r($result['faltantes']);
}
```

## ¿Qué se valida en el expediente?

### 1. Datos Básicos (según tipo de persona)

#### Persona Física:
- ✅ Nombre
- ✅ Apellido paterno
- ✅ Apellido materno (opcional)

#### Persona Moral:
- ✅ Razón social

#### Fideicomiso:
- ✅ Número de fideicomiso
- ✅ Institución fiduciaria

### 2. Identificaciones Oficiales
- ✅ Al menos una identificación registrada
- Tipos comunes: RFC, CURP, Pasaporte, etc.

### 3. Direcciones
- ✅ Al menos una dirección registrada
- Debe incluir: calle, colonia, código postal, etc.

### 4. Contactos
- ✅ Al menos un contacto registrado
- Tipos: Teléfono, Email, etc.

### 5. Documentos de Soporte
- ✅ Al menos un documento con archivo cargado
- Tipos: Identificación, Comprobante de domicilio, etc.

## Condiciones de Aplicación

### Permanente vs. Por Umbral

El expediente puede ser requerido:

1. **Siempre**: Para ciertas fracciones, el expediente es obligatorio desde la primera operación
2. **Al rebasar umbral**: Para otras fracciones, solo se requiere cuando se supera un monto en UMAs

### Configuración por Fracción

Cada fracción de actividad vulnerable puede tener:
- `es_siempre_identificacion = 1`: Expediente siempre requerido
- `monto_identificacion`: Monto en UMAs que detona el requerimiento

## Resultado de la Validación

### ✅ Expediente Completo
- **Estado**: `expediente_completo = 1`
- **Flag**: `identificacion_incompleta = 0`
- **Acción**: Permite operaciones PLD

### ❌ Expediente Incompleto
- **Estado**: `expediente_completo = 0`
- **Flag**: `identificacion_incompleta = 1`
- **Código**: `IDENTIFICACION_INCOMPLETA`
- **Acción**: **BLOQUEA operaciones PLD**

## Integración en el Sistema

### Bloquear operación si expediente incompleto

```php
require_once __DIR__ . '/config/pld_middleware.php';

// Antes de permitir una operación PLD
requireExpedienteCompleto($pdo, $id_cliente);
// Si el expediente está incompleto, se bloquea automáticamente
```

### Validar antes de crear operación

```php
require_once __DIR__ . '/config/pld_expediente.php';

// Verificar si el expediente está completo
$result = validateExpedienteCompleto($pdo, $id_cliente);

if (!$result['completo']) {
    // Mostrar mensaje al usuario indicando qué falta
    echo "Expediente incompleto. Faltantes:";
    foreach ($result['faltantes'] as $faltante) {
        echo "- " . $faltante . "\n";
    }
    // Bloquear operación
    exit;
}
```

### Usar validación centralizada

```php
require_once __DIR__ . '/config/pld_middleware.php';

// Validar todas las reglas PLD (incluye VAL-PLD-005)
validatePLDOperation($pdo, $id_cliente, null, ['VAL-PLD-005']);
```

## Actualización de Fecha de Expediente

Cuando se actualiza información del cliente, se debe actualizar la fecha:

```php
require_once __DIR__ . '/config/pld_expediente.php';

// Actualizar fecha de última actualización
actualizarFechaExpediente($pdo, $id_cliente);
```

Esto es importante para VAL-PLD-006 (Actualización Anual).

## Ejemplo de Respuesta de Validación

### Expediente Completo:
```json
{
    "completo": true,
    "bloqueado": false,
    "razon": "Expediente completo",
    "faltantes": [],
    "codigo": null
}
```

### Expediente Incompleto:
```json
{
    "completo": false,
    "bloqueado": true,
    "razon": "Expediente incompleto",
    "faltantes": [
        "Identificaciones oficiales",
        "Direcciones",
        "Documentos de soporte"
    ],
    "codigo": "IDENTIFICACION_INCOMPLETA"
}
```

## Relación con VAL-PLD-006

VAL-PLD-005 valida la **completitud** del expediente, mientras que VAL-PLD-006 valida que se haya **actualizado en los últimos 12 meses**.

Ambas validaciones son necesarias para que un expediente sea válido.

## Preguntas Frecuentes

### ¿Qué pasa si falta un documento?
El sistema marca el expediente como incompleto y bloquea operaciones PLD hasta que se complete.

### ¿Puedo tener múltiples identificaciones?
Sí, el sistema requiere al menos una, pero puedes tener varias.

### ¿El expediente se actualiza automáticamente?
No, debes llamar a `actualizarFechaExpediente()` cuando se modifique información del cliente.

### ¿Qué documentos son obligatorios?
Depende de la fracción y el umbral. Consulta la configuración de la fracción para saber si es "siempre" o "por umbral".

## Archivos Relacionados

- `config/pld_expediente.php` - Lógica de validación VAL-PLD-005 y VAL-PLD-006
- `admin/expedientes_pld.php` - Interfaz de gestión
- `config/pld_middleware.php` - Middleware de bloqueo
- `db/migrations/add_pld_validations_fields.sql` - Estructura de BD

## Flujo de Validación

1. **Usuario intenta operación PLD**
2. **Sistema valida expediente** (VAL-PLD-005)
3. **Si está incompleto**:
   - Marca flag `identificacion_incompleta = 1`
   - Bloquea operación
   - Muestra qué falta
4. **Si está completo**:
   - Valida actualización (VAL-PLD-006)
   - Si está actualizado: Permite operación
   - Si está vencido: Bloquea operación

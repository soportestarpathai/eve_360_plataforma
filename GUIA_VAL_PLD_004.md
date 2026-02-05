# Guía: VAL-PLD-004 - Representación Legal del Usuario

## ¿Qué es VAL-PLD-004?

La validación VAL-PLD-004 asegura que cualquier usuario que actúe en nombre de la entidad tenga facultades documentadas. Esto es un requisito obligatorio para operaciones PLD.

## ¿Cómo revisar la representación legal?

### Opción 1: Panel de Administración

1. **Acceder al panel:**
   - Ve a: `http://localhost:8080/eve_360_plataforma/admin/representacion_legal.php`
   - O desde el menú lateral: **Administración > Representación Legal**

2. **Ver el estado de todos los usuarios:**
   - La tabla muestra:
     - **Usuario**: Nombre del usuario
     - **Email**: Correo de login
     - **Representaciones**: Cantidad de representaciones registradas
     - **Con Documento**: Cuántas tienen documento cargado
     - **Estado**: 
       - ✅ **Válido**: Tiene representación con documento vigente
       - ⚠️ **Incompleto**: Tiene representación pero falta documento
       - ❌ **Sin registro**: No tiene representación registrada
       - 🔴 **Vencido**: El documento de facultades está vencido

3. **Acciones disponibles:**
   - **Agregar**: Registrar nueva representación legal
   - **Ver**: Ver todas las representaciones de un usuario
   - **Validar**: Ejecutar validación VAL-PLD-004 para ese usuario

### Opción 2: Validación Programática

```php
require_once __DIR__ . '/config/pld_representacion_legal.php';

// Validar un usuario específico
$result = validateRepresentacionLegal($pdo, $id_usuario, $id_cliente);

if ($result['valido'] && !$result['bloqueado']) {
    echo "Usuario válido: " . $result['razon'];
} else {
    echo "Usuario NO válido: " . $result['razon'];
    echo "Detalles: " . print_r($result['detalles'], true);
}
```

## ¿Cómo registrar representación legal?

### Desde el Panel de Administración

1. Haz clic en **"Agregar"** en la fila del usuario
2. Completa el formulario:
   - **Tipo de Representación** (requerido):
     - Representante Legal
     - Apoderado
     - Usuario Autorizado
   - **Cliente** (opcional):
     - Dejar vacío = Representación general (aplica a todos)
     - Seleccionar cliente = Representación específica
   - **Documento de Facultades** (requerido):
     - Subir PDF, JPG o PNG
     - Debe ser el documento que acredita las facultades
   - **Fecha de Vencimiento** (opcional):
     - Si el documento tiene fecha de vencimiento
3. Haz clic en **"Guardar"**

### Programáticamente

```php
require_once __DIR__ . '/config/pld_representacion_legal.php';

$result = registrarRepresentacionLegal($pdo, [
    'id_usuario' => 1,
    'id_cliente' => null, // null = general, o ID específico
    'tipo_representacion' => 'representante_legal', // o 'apoderado', 'usuario_autorizado'
    'documento_facultades' => 'uploads/representacion_legal/rep_1_1234567890.pdf',
    'fecha_vencimiento' => '2026-12-31' // opcional
]);

if ($result['success']) {
    echo "Representación registrada: ID " . $result['id_representacion'];
}
```

## Validaciones que se realizan

1. **¿Tiene representación registrada?**
   - Si NO → Bloquea operación
   - Si SÍ → Continúa

2. **¿Tiene documento de facultades cargado?**
   - Si NO → Bloquea operación
   - Si SÍ → Continúa

3. **¿El documento existe físicamente?**
   - Si NO → Bloquea operación
   - Si SÍ → Continúa

4. **¿El documento está vencido?**
   - Si SÍ → Bloquea operación
   - Si NO → ✅ Usuario válido

## Integración en el sistema

### Bloquear operación si no hay representación

```php
require_once __DIR__ . '/config/pld_middleware.php';

// Antes de permitir una operación PLD
requireRepresentacionLegal($pdo, $id_usuario, $id_cliente);
// Si no hay representación válida, se bloquea automáticamente
```

### Usar validación centralizada

```php
require_once __DIR__ . '/config/pld_middleware.php';

// Validar todas las reglas PLD (incluye VAL-PLD-004)
validatePLDOperation($pdo, $id_cliente, $id_usuario, ['VAL-PLD-004']);
```

## Tipos de Representación

### 1. Representante Legal
- Persona que representa legalmente a la entidad
- Requiere poder notarial o acta constitutiva

### 2. Apoderado
- Persona con poder para actuar en nombre de la entidad
- Requiere poder notarial específico

### 3. Usuario Autorizado
- Usuario con autorización específica para operaciones PLD
- Requiere documento de autorización

## Condiciones de Aplicación

- **Permanente**: Algunos usuarios siempre requieren representación legal
- **Al rebasar umbral**: Cuando la operación supera cierto monto en UMAs

## Resultado de la Validación

- ✅ **Válido**: Usuario puede operar
- ❌ **Bloqueado**: 
  - Código: `FALTA_REPRESENTACION_LEGAL`
  - Mensaje: Razón específica del bloqueo
  - Acción requerida: Registrar representación legal con documento

## Ejemplo de Respuesta de Validación

```json
{
    "valido": true,
    "bloqueado": false,
    "razon": "Representación legal válida",
    "detalles": {
        "id_usuario": 1,
        "representaciones_validas": 2,
        "tipos": ["representante_legal", "apoderado"]
    }
}
```

O si hay error:

```json
{
    "valido": false,
    "bloqueado": true,
    "razon": "Falta evidencia documental de facultades",
    "tipo_requerido": "documento_facultades",
    "detalles": {
        "id_usuario": 1,
        "representaciones_sin_documento": 1
    }
}
```

## Preguntas Frecuentes

### ¿Un usuario puede tener múltiples representaciones?
Sí, un usuario puede tener:
- Representación general (sin cliente específico)
- Representaciones específicas por cliente
- Diferentes tipos de representación

### ¿Qué pasa si el documento vence?
El sistema detecta automáticamente documentos vencidos y bloquea las operaciones hasta que se renueve.

### ¿Puedo tener representación sin documento?
No, el documento de facultades es obligatorio. Sin él, la representación no es válida.

### ¿La representación es por cliente o general?
Puede ser ambas:
- **General**: Aplica a todos los clientes
- **Específica**: Solo para un cliente determinado

## Archivos Relacionados

- `config/pld_representacion_legal.php` - Lógica de validación
- `admin/representacion_legal.php` - Interfaz de gestión
- `config/pld_middleware.php` - Middleware de bloqueo
- `db/migrations/add_pld_validations_fields.sql` - Estructura de BD

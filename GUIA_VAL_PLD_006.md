# Guía: VAL-PLD-006 - Actualización Anual del Expediente

## ¿Qué es VAL-PLD-006?

La validación VAL-PLD-006 asegura que en relaciones de negocio continuas, el expediente de identificación se actualice al menos 1 vez al año (cada 12 meses).

## ¿Cómo revisar la actualización del expediente?

### Opción 1: Detalle del Cliente

1. **Acceder al detalle:**
   - Ve a la lista de clientes: `/clientes.php`
   - Haz clic en "Ver Detalles" del cliente
   - O accede directamente: `/cliente_detalle.php?id=X`

2. **Ver el estado:**
   - En la sección **"Estado del Expediente PLD"** verás:
     - **Completitud (VAL-PLD-005)**: Si el expediente está completo
     - **Actualización (VAL-PLD-006)**: Si está actualizado o vencido
     - Si está vencido, verás cuántos días lleva vencido

3. **Actualizar fecha:**
   - Si el expediente está vencido, verás un botón **"Actualizar Fecha"**
   - Al hacer clic, se marca el expediente como actualizado hoy
   - Esto es útil cuando se modifica información del cliente

### Opción 2: Tabla de Clientes

En la lista de clientes (`/clientes.php`), la columna **"Expediente PLD"** muestra:
- ✅ **Completo**: Expediente completo y actualizado
- ⚠️ **Vencido**: Requiere actualización (más de 12 meses)
- ❌ **Incompleto**: Falta información

### Opción 3: Validación Programática

```php
require_once __DIR__ . '/config/pld_expediente.php';

// Validar actualización (VAL-PLD-006)
$result = validateActualizacionExpediente($pdo, $id_cliente);

if ($result['actualizado']) {
    echo "Expediente actualizado";
} else {
    echo "Expediente vencido: " . $result['dias_vencido'] . " días";
}
```

## Actualización Automática

### Al Crear Cliente
Cuando se crea un nuevo cliente, la fecha se actualiza automáticamente.

### Al Actualizar Cliente
Cuando se modifica información del cliente, la fecha se actualiza automáticamente.

### Actualización Manual
Si necesitas actualizar la fecha sin modificar datos:
- Usa el botón "Actualizar Fecha" en el detalle del cliente
- O llama a la API: `api/update_fecha_expediente.php`

## Validación

### Criterio
- **Actualizado**: Última actualización ≤ 12 meses (365 días)
- **Vencido**: Última actualización > 12 meses

### Cálculo
```php
$diasTranscurridos = diferencia entre hoy y fecha_ultima_actualizacion_expediente
$actualizado = $diasTranscurridos <= 365
```

## Resultado de la Validación

### ✅ Expediente Actualizado
- **Estado**: `actualizado = true`
- **Acción**: Permite operaciones PLD

### ❌ Expediente Vencido
- **Estado**: `actualizado = false`
- **Bloqueado**: `bloqueado = true`
- **Código**: `EXPEDIENTE_VENCIDO`
- **Acción**: **BLOQUEA nuevas operaciones PLD**

## Integración en el Sistema

### Bloqueo Automático en Operaciones PLD

La validación se ejecuta automáticamente en:
- Creación de clientes
- Actualización de clientes
- Operaciones PLD críticas (a través de `requireExpedienteCompleto()`)

### Función de Bloqueo

```php
require_once __DIR__ . '/config/pld_middleware.php';

// Bloquea si expediente incompleto O vencido
requireExpedienteCompleto($pdo, $id_cliente);
// Valida tanto VAL-PLD-005 como VAL-PLD-006
```

### Validación Centralizada

```php
require_once __DIR__ . '/config/pld_middleware.php';

// Validar todas las reglas PLD (incluye VAL-PLD-006)
validatePLDOperation($pdo, $id_cliente, null, ['VAL-PLD-006']);
```

## Actualizar Fecha Manualmente

### Desde la Interfaz

1. Ve al detalle del cliente
2. En la sección "Estado del Expediente PLD"
3. Si está vencido, haz clic en **"Actualizar Fecha"**
4. Confirma la acción

### Desde la API

```php
require_once __DIR__ . '/config/pld_expediente.php';

// Actualizar fecha de última actualización
actualizarFechaExpediente($pdo, $id_cliente);
```

### Endpoint API

```javascript
fetch('api/update_fecha_expediente.php', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
    },
    body: 'id_cliente=' + idCliente
})
.then(response => response.json())
.then(data => {
    console.log(data);
});
```

## Ejemplo de Respuesta de Validación

### Expediente Actualizado:
```json
{
    "actualizado": true,
    "bloqueado": false,
    "razon": "Expediente actualizado",
    "dias_vencido": 0,
    "fecha_ultima_actualizacion": "2025-01-15"
}
```

### Expediente Vencido:
```json
{
    "actualizado": false,
    "bloqueado": true,
    "razon": "Expediente vencido (requiere actualización anual)",
    "dias_vencido": 45,
    "fecha_ultima_actualizacion": "2024-01-01"
}
```

## Relación con VAL-PLD-005

VAL-PLD-005 valida la **completitud** del expediente, mientras que VAL-PLD-006 valida la **actualización**.

Ambas validaciones son necesarias:
- ✅ Expediente completo (VAL-PLD-005)
- ✅ Expediente actualizado (VAL-PLD-006)
- = ✅ Expediente válido para operaciones PLD

## Preguntas Frecuentes

### ¿Cuándo se actualiza automáticamente?
- Al crear un nuevo cliente
- Al modificar información del cliente (save/update)

### ¿Qué pasa si el expediente está vencido?
El sistema bloquea nuevas operaciones PLD hasta que se actualice.

### ¿Cómo actualizar sin modificar datos?
Usa el botón "Actualizar Fecha" en el detalle del cliente o llama a la API.

### ¿Se actualiza al modificar cualquier campo?
Sí, cualquier modificación del cliente actualiza la fecha automáticamente.

### ¿Qué cuenta como "actualización"?
Cualquier cambio en:
- Datos básicos del cliente
- Identificaciones
- Direcciones
- Contactos
- Documentos
- Apoderados

## Archivos Relacionados

- `config/pld_expediente.php` - Lógica de validación VAL-PLD-005 y VAL-PLD-006
- `api/update_fecha_expediente.php` - Endpoint para actualizar fecha
- `cliente_detalle.php` - Interfaz para revisar y actualizar
- `api/save_client.php` - Actualización automática al crear
- `api/update_client.php` - Actualización automática al modificar
- `config/pld_middleware.php` - Middleware de bloqueo

## Flujo de Validación

1. **Usuario intenta operación PLD**
2. **Sistema valida expediente**:
   - ¿Está completo? (VAL-PLD-005)
   - ¿Está actualizado? (VAL-PLD-006)
3. **Si está vencido**:
   - Bloquea operación
   - Muestra días vencidos
   - Requiere actualización
4. **Si está actualizado**:
   - Permite operación

## Notas Importantes

- La actualización es **automática** al modificar el cliente
- El bloqueo es **automático** si está vencido
- La fecha se actualiza a **hoy** cuando se modifica el cliente
- El plazo es de **12 meses (365 días)** desde la última actualización

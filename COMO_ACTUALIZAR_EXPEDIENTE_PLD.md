# Cómo Actualizar el Expediente PLD

## Formas de Actualizar el Expediente PLD

Hay **3 formas** de actualizar el expediente PLD:

---

## 1. Actualización Automática (Recomendada)

### ¿Cuándo se actualiza automáticamente?

El expediente se actualiza **automáticamente** cuando modificas **cualquier información** del cliente:

- ✅ Al **crear** un nuevo cliente
- ✅ Al **editar** datos del cliente:
  - Información básica (nombre, apellidos, razón social)
  - Identificaciones (RFC, CURP, etc.)
  - Direcciones
  - Contactos (email, teléfono)
  - Documentos
  - Apoderados

### ¿Qué se actualiza?

- **Fecha de última actualización**: Se actualiza a la fecha actual
- **Estado de completitud**: Se valida automáticamente si está completo
- **Estado de actualización**: Se marca como actualizado

### Pasos para actualizar automáticamente:

1. **Ir al detalle del cliente**
   - Desde la lista de clientes, haz clic en "Ver Detalles"
   - O accede a: `/cliente_detalle.php?id=[ID_CLIENTE]`

2. **Hacer clic en "Editar Cliente"**
   - Botón azul en la parte superior derecha

3. **Modificar cualquier campo**
   - Puede ser cualquier dato: nombre, dirección, contacto, etc.

4. **Guardar los cambios**
   - Al guardar, la fecha se actualiza automáticamente

5. **Verificar el estado**
   - Regresa al detalle del cliente
   - La sección "Estado del Expediente PLD" mostrará la nueva fecha

---

## 2. Actualización Manual (Botón "Actualizar Fecha")

### ¿Cuándo usar esta opción?

Usa esta opción cuando:
- ✅ Ya revisaste y verificaste que toda la información está correcta
- ✅ No necesitas modificar datos, solo actualizar la fecha
- ✅ El expediente está completo pero vencido

### Pasos para actualizar manualmente:

1. **Ir al detalle del cliente**
   - `/cliente_detalle.php?id=[ID_CLIENTE]`

2. **Ir a la sección "Estado del Expediente PLD"**
   - Se encuentra después de "Apoderados" y antes de "Nacionalidades"

3. **Hacer clic en "Actualizar Fecha"**
   - Botón verde con icono de calendario
   - Solo aparece si el expediente está vencido

4. **Confirmar la acción**
   - Aparecerá un diálogo de confirmación
   - Haz clic en "Sí, actualizar"

5. **Verificar**
   - La fecha se actualiza a hoy
   - El estado cambiará a "Expediente actualizado"

---

## 3. Actualización desde la API (Para desarrolladores)

### Endpoint:

```
POST api/update_fecha_expediente.php
```

### Parámetros:

```javascript
{
    id_cliente: [ID_DEL_CLIENTE]
}
```

### Ejemplo con JavaScript:

```javascript
fetch('api/update_fecha_expediente.php', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
    },
    body: 'id_cliente=' + clientId
})
.then(response => response.json())
.then(data => {
    if (data.status === 'success') {
        console.log('Fecha actualizada:', data.fecha_actualizada);
    }
});
```

### Ejemplo con PHP:

```php
require_once __DIR__ . '/config/pld_expediente.php';

// Actualizar fecha de última actualización
actualizarFechaExpediente($pdo, $id_cliente);
```

---

## ¿Qué se Actualiza Exactamente?

### Campo en la Base de Datos:

```sql
UPDATE clientes 
SET fecha_ultima_actualizacion_expediente = CURDATE() 
WHERE id_cliente = ?
```

### Validación Automática:

Después de actualizar, el sistema valida:
- ✅ **VAL-PLD-005**: Completitud del expediente
- ✅ **VAL-PLD-006**: Actualización (debe ser ≤ 12 meses)

---

## Resolver Expediente Incompleto (VAL-PLD-005)

Si el expediente está **incompleto**, necesitas:

### 1. Identificar qué falta:

- Haz clic en el botón **"Validar"** en la sección del expediente
- Se mostrará una lista de elementos faltantes

### 2. Completar la información:

**Para Persona Física:**
- ✅ Nombre completo
- ✅ Apellidos
- ✅ RFC
- ✅ CURP (si aplica)
- ✅ Al menos una identificación
- ✅ Al menos una dirección
- ✅ Al menos un contacto

**Para Persona Moral:**
- ✅ Razón social
- ✅ RFC
- ✅ Al menos una identificación
- ✅ Al menos una dirección
- ✅ Al menos un contacto
- ✅ Representante legal designado

### 3. Guardar los cambios:

- Al guardar, se actualiza automáticamente la fecha
- Se valida la completitud

---

## Resolver Expediente Vencido (VAL-PLD-006)

Si el expediente está **vencido** (>12 meses):

### Opción 1: Actualizar Fecha Manualmente
- Usa el botón "Actualizar Fecha"
- Solo si toda la información está correcta

### Opción 2: Modificar Información
- Edita cualquier dato del cliente
- La fecha se actualiza automáticamente

---

## Verificación del Estado

### Después de actualizar, verifica:

1. **Estado General:**
   - ✅ "Expediente Válido para Operaciones PLD" (verde)
   - ❌ "Expediente NO Válido" (rojo)

2. **Completitud (VAL-PLD-005):**
   - ✅ "Expediente completo" (verde)
   - ❌ "Expediente incompleto" (rojo) + lista de faltantes

3. **Actualización (VAL-PLD-006):**
   - ✅ "Expediente actualizado" (verde) + fecha
   - ❌ "Expediente vencido" (rojo) + días vencidos

---

## Preguntas Frecuentes

### ¿Se actualiza automáticamente al crear un cliente?
**Sí**, al crear un nuevo cliente, la fecha se establece automáticamente.

### ¿Qué pasa si actualizo la fecha pero el expediente sigue incompleto?
El expediente seguirá bloqueando operaciones PLD hasta que se complete toda la información requerida.

### ¿Puedo actualizar la fecha sin modificar datos?
**Sí**, usando el botón "Actualizar Fecha" en el detalle del cliente.

### ¿Cuánto tiempo tengo antes de que se venza?
El expediente debe actualizarse **al menos 1 vez al año** (365 días).

### ¿Qué pasa si no actualizo el expediente?
El sistema **bloqueará nuevas operaciones PLD** hasta que se actualice.

---

## Flujo Completo de Actualización

```
1. Cliente necesita actualización
   ↓
2. Opción A: Modificar datos → Actualización automática
   Opción B: Usar botón "Actualizar Fecha" → Actualización manual
   ↓
3. Sistema valida:
   - ¿Está completo? (VAL-PLD-005)
   - ¿Está actualizado? (VAL-PLD-006)
   ↓
4. Si ambas son SÍ:
   ✅ Expediente válido → Operaciones PLD permitidas
   ↓
5. Si alguna es NO:
   ❌ Expediente inválido → Operaciones PLD bloqueadas
```

---

## Archivos Relacionados

- `config/pld_expediente.php` - Lógica de validación y actualización
- `api/update_fecha_expediente.php` - Endpoint para actualización manual
- `api/save_client.php` - Actualización automática al crear
- `api/update_client.php` - Actualización automática al modificar
- `cliente_detalle.php` - Interfaz para ver y actualizar

---

## Notas Importantes

⚠️ **La actualización de fecha NO completa información faltante**
- Solo actualiza la fecha de última actualización
- Si falta información, debes completarla manualmente

⚠️ **El bloqueo es automático**
- Si el expediente está incompleto o vencido, las operaciones PLD se bloquean automáticamente
- No se pueden realizar operaciones hasta resolver los problemas

✅ **La actualización es automática al modificar**
- Cualquier cambio en el cliente actualiza la fecha
- No necesitas hacer nada adicional

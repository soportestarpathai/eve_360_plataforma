# ¿Qué se Necesita para Completar el Expediente PLD?

## Resumen

Para que el expediente PLD esté **completo** (VAL-PLD-005), el cliente debe tener:

### ✅ 1. Datos Básicos (Según Tipo de Persona)

**Persona Física:**
- ✅ Nombre (en `clientes_fisicas.nombre`)
- ✅ Apellido Paterno (en `clientes_fisicas.apellido_paterno`)
- ⚠️ Apellido Materno (opcional)

**Persona Moral:**
- ✅ Razón Social (en `clientes_morales.razon_social`)

**Fideicomiso:**
- ✅ Número de Fideicomiso (en `clientes_fideicomisos.numero_fideicomiso`)

---

### ✅ 2. Identificaciones Oficiales

**Requisito:** Al menos 1 identificación activa

**Tabla:** `clientes_identificaciones`
- Debe tener `id_status = 1` (si la columna existe)
- Ejemplos de identificaciones:
  - RFC
  - CURP
  - Pasaporte
  - Licencia de conducir
  - Credencial de elector
  - Otros documentos oficiales

**Cómo agregar:**
- En `cliente_editar.php` → Sección "Identificaciones"
- Agregar al menos una identificación
- Asegurarse de que esté activa (`id_status = 1`)

---

### ✅ 3. Direcciones

**Requisito:** Al menos 1 dirección activa

**Tabla:** `clientes_direcciones`
- Debe tener `id_status = 1` (si la columna existe)
- Debe incluir:
  - Calle
  - Colonia
  - Código Postal
  - Ciudad
  - Estado
  - País

**Cómo agregar:**
- En `cliente_editar.php` → Sección "Direcciones"
- Agregar al menos una dirección
- Asegurarse de que esté activa (`id_status = 1`)

---

### ✅ 4. Contactos

**Requisito:** Al menos 1 contacto activo

**Tabla:** `clientes_contactos`
- Debe tener `id_status = 1` (si la columna existe)
- Tipos de contacto:
  - Email
  - Teléfono fijo
  - Teléfono celular
  - Otros

**Cómo agregar:**
- En `cliente_editar.php` → Sección "Contactos"
- Agregar al menos un contacto (email o teléfono)
- Asegurarse de que esté activo (`id_status = 1`)

---

### ✅ 5. Documentos de Soporte

**Requisito:** Al menos 1 documento con archivo cargado

**Tabla:** `clientes_documentos`
- Debe tener `id_status = 1`
- Debe tener `ruta` no vacía (archivo cargado)
- Ejemplos de documentos:
  - KYC (Know Your Customer)
  - Identificación oficial escaneada
  - Comprobante de domicilio
  - Otros documentos requeridos

**Cómo agregar:**
- En `cliente_editar.php` → Sección "Documentos"
- Subir al menos un documento
- Asegurarse de que esté activo (`id_status = 1`)

---

## Verificación en la Base de Datos

### Script SQL para verificar qué tiene un cliente:

```sql
-- Reemplaza [ID_CLIENTE] con el ID del cliente
SET @id_cliente = [ID_CLIENTE];

-- 1. Datos básicos
SELECT 'Datos Básicos' as seccion,
       CASE 
           WHEN EXISTS(SELECT 1 FROM clientes_fisicas WHERE id_cliente = @id_cliente) THEN 'Persona Física'
           WHEN EXISTS(SELECT 1 FROM clientes_morales WHERE id_cliente = @id_cliente) THEN 'Persona Moral'
           WHEN EXISTS(SELECT 1 FROM clientes_fideicomisos WHERE id_cliente = @id_cliente) THEN 'Fideicomiso'
           ELSE 'NO DEFINIDO'
       END as tipo,
       CASE 
           WHEN EXISTS(SELECT 1 FROM clientes_fisicas WHERE id_cliente = @id_cliente AND nombre IS NOT NULL AND nombre != '' AND apellido_paterno IS NOT NULL AND apellido_paterno != '') THEN 'COMPLETO'
           WHEN EXISTS(SELECT 1 FROM clientes_morales WHERE id_cliente = @id_cliente AND razon_social IS NOT NULL AND razon_social != '') THEN 'COMPLETO'
           WHEN EXISTS(SELECT 1 FROM clientes_fideicomisos WHERE id_cliente = @id_cliente AND numero_fideicomiso IS NOT NULL AND numero_fideicomiso != '') THEN 'COMPLETO'
           ELSE 'INCOMPLETO'
       END as estado

UNION ALL

-- 2. Identificaciones
SELECT 'Identificaciones' as seccion,
       COUNT(*) as cantidad,
       CASE WHEN COUNT(*) > 0 THEN 'TIENE' ELSE 'FALTA' END as estado
FROM clientes_identificaciones
WHERE id_cliente = @id_cliente
  AND (id_status = 1 OR id_status IS NULL)

UNION ALL

-- 3. Direcciones
SELECT 'Direcciones' as seccion,
       COUNT(*) as cantidad,
       CASE WHEN COUNT(*) > 0 THEN 'TIENE' ELSE 'FALTA' END as estado
FROM clientes_direcciones
WHERE id_cliente = @id_cliente
  AND (id_status = 1 OR id_status IS NULL)

UNION ALL

-- 4. Contactos
SELECT 'Contactos' as seccion,
       COUNT(*) as cantidad,
       CASE WHEN COUNT(*) > 0 THEN 'TIENE' ELSE 'FALTA' END as estado
FROM clientes_contactos
WHERE id_cliente = @id_cliente
  AND (id_status = 1 OR id_status IS NULL)

UNION ALL

-- 5. Documentos
SELECT 'Documentos' as seccion,
       COUNT(*) as cantidad,
       CASE WHEN COUNT(*) > 0 THEN 'TIENE' ELSE 'FALTA' END as estado
FROM clientes_documentos
WHERE id_cliente = @id_cliente
  AND id_status = 1
  AND ruta IS NOT NULL
  AND ruta != '';
```

---

## Cómo Completar el Expediente

### Paso 1: Ir a Editar Cliente

1. Ve al detalle del cliente: `/cliente_detalle.php?id=[ID_CLIENTE]`
2. Haz clic en el botón "Editar Cliente"

### Paso 2: Completar Cada Sección

#### Sección: Identificaciones
- Haz clic en "Agregar Identificación"
- Selecciona el tipo (RFC, CURP, etc.)
- Ingresa el número
- Guarda

#### Sección: Direcciones
- Haz clic en "Agregar Dirección"
- Completa: Calle, Colonia, CP, Ciudad, Estado, País
- Guarda

#### Sección: Contactos
- Haz clic en "Agregar Contacto"
- Selecciona tipo (Email, Teléfono)
- Ingresa el dato
- Guarda

#### Sección: Documentos
- Haz clic en "Agregar Documento"
- Selecciona tipo (KYC, Identificación, etc.)
- Sube el archivo
- Guarda

### Paso 3: Guardar y Validar

1. Haz clic en "Guardar Cliente"
2. Regresa al detalle del cliente
3. Haz clic en "Validar" en la sección del expediente PLD
4. Verifica que aparezca "Expediente completo"

---

## Verificación Rápida

### Desde la Interfaz:

1. **Detalle del Cliente** → Sección "Estado del Expediente PLD"
2. Haz clic en **"Validar"**
3. Verás la lista de elementos faltantes (si hay)

### Desde la Base de Datos:

Ejecuta el script SQL de verificación arriba, reemplazando `[ID_CLIENTE]` con el ID del cliente.

---

## Notas Importantes

⚠️ **id_status = 1**
- Los registros deben estar activos (`id_status = 1`)
- Si los datos existen pero tienen `id_status = 0`, se consideran faltantes

⚠️ **Documentos con Archivo**
- Los documentos deben tener un archivo cargado (`ruta` no vacía)
- No cuenta solo el registro, debe tener el archivo

✅ **Actualización Automática**
- Al guardar el cliente, la fecha se actualiza automáticamente
- La validación se ejecuta automáticamente

---

## Solución de Problemas

### "Expediente incompleto" pero no muestra faltantes

**Causa:** Los datos existen pero están inactivos (`id_status = 0`)

**Solución:**
1. Ejecuta: `db/migrations/fix_expediente_validation.sql`
2. O activa manualmente los registros:
   ```sql
   UPDATE clientes_identificaciones SET id_status = 1 WHERE id_cliente = [ID];
   UPDATE clientes_direcciones SET id_status = 1 WHERE id_cliente = [ID];
   UPDATE clientes_contactos SET id_status = 1 WHERE id_cliente = [ID];
   UPDATE clientes_documentos SET id_status = 1 WHERE id_cliente = [ID];
   ```

### Error: "Column not found: id_status"

**Causa:** La columna `id_status` no existe en alguna tabla

**Solución:**
1. Ejecuta: `db/migrations/fix_expediente_validation.sql`
2. Este script agrega `id_status` solo si no existe

---

## Checklist de Completitud

Para cada cliente, verifica:

- [ ] Datos básicos completos (nombre/apellidos o razón social)
- [ ] Al menos 1 identificación activa
- [ ] Al menos 1 dirección activa
- [ ] Al menos 1 contacto activo
- [ ] Al menos 1 documento con archivo cargado
- [ ] Todos los registros con `id_status = 1` (si aplica)

Si todos los items están marcados → **Expediente Completo** ✅

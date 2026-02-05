# Instrucciones: Migración de Base de Datos para Expediente PLD

## ¿Qué campos se necesitan?

Para que funcionen **VAL-PLD-005** y **VAL-PLD-006**, necesitas estos campos en la tabla `clientes`:

1. **`fecha_ultima_actualizacion_expediente`** (DATE)
   - Almacena la fecha de última actualización del expediente
   - Usado para VAL-PLD-006 (validación de actualización anual)

2. **`identificacion_incompleta`** (TINYINT)
   - Flag: 1 = Expediente incompleto, 0 = Completo
   - Usado para VAL-PLD-005

3. **`expediente_completo`** (TINYINT)
   - Flag: 1 = Expediente completo, 0 = Incompleto
   - Usado para VAL-PLD-005

---

## Paso 1: Verificar si los campos ya existen

Ejecuta este script en tu base de datos:

```sql
SELECT 
    COLUMN_NAME,
    COLUMN_TYPE,
    IS_NULLABLE,
    COLUMN_DEFAULT,
    COLUMN_COMMENT
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'clientes'
  AND COLUMN_NAME IN (
    'fecha_ultima_actualizacion_expediente',
    'identificacion_incompleta',
    'expediente_completo'
  )
ORDER BY COLUMN_NAME;
```

### Resultado esperado:

- **Si NO aparecen resultados**: Los campos no existen, necesitas ejecutar la migración
- **Si aparecen 3 campos**: Los campos ya existen, no necesitas hacer nada

---

## Paso 2: Ejecutar la migración (si los campos no existen)

### Opción A: Usar el script SQL (Recomendado)

1. Abre phpMyAdmin o tu cliente MySQL
2. Selecciona tu base de datos
3. Ve a la pestaña "SQL"
4. Copia y pega el contenido de: `db/migrations/add_expediente_fields_only.sql`
5. Haz clic en "Ejecutar"

### Opción B: Ejecutar comandos individuales

Si prefieres ejecutar los comandos uno por uno:

```sql
-- Campo 1: Fecha de última actualización
ALTER TABLE `clientes` 
ADD COLUMN `fecha_ultima_actualizacion_expediente` DATE DEFAULT NULL 
COMMENT 'Fecha última actualización del expediente (VAL-PLD-006)';

-- Campo 2: Flag de identificación incompleta
ALTER TABLE `clientes` 
ADD COLUMN `identificacion_incompleta` TINYINT(1) DEFAULT 0 
COMMENT 'Flag: 1 = Expediente incompleto (VAL-PLD-005)';

-- Campo 3: Flag de expediente completo
ALTER TABLE `clientes` 
ADD COLUMN `expediente_completo` TINYINT(1) DEFAULT 0 
COMMENT 'Flag: 1 = Expediente completo';
```

---

## Paso 3: Verificar que se crearon correctamente

Ejecuta de nuevo el script de verificación del Paso 1. Deberías ver los 3 campos.

---

## Paso 4: Inicializar datos existentes (Opcional)

Si ya tienes clientes en la base de datos, puedes inicializar las fechas:

```sql
-- Establecer fecha de actualización para clientes existentes
UPDATE `clientes` 
SET `fecha_ultima_actualizacion_expediente` = CURDATE()
WHERE `fecha_ultima_actualizacion_expediente` IS NULL
  AND `id_status` = 1; -- Solo clientes activos
```

**Nota:** Esto establecerá la fecha de hoy para todos los clientes activos. Si prefieres usar la fecha de apertura del cliente, usa:

```sql
UPDATE `clientes` 
SET `fecha_ultima_actualizacion_expediente` = `fecha_apertura`
WHERE `fecha_ultima_actualizacion_expediente` IS NULL
  AND `fecha_apertura` IS NOT NULL
  AND `id_status` = 1;
```

---

## Solución de Problemas

### Error: "Duplicate column name"

**Causa:** El campo ya existe en la tabla.

**Solución:** No necesitas hacer nada, los campos ya están creados.

### Error: "Table 'clientes' doesn't exist"

**Causa:** El nombre de la tabla es diferente.

**Solución:** Verifica el nombre correcto de tu tabla de clientes y ajusta el script.

### Error de sintaxis

**Causa:** Puede ser un problema con las comillas o la versión de MySQL.

**Solución:** Usa el script `add_expediente_fields_only.sql` que maneja estos casos automáticamente.

---

## Archivos Disponibles

1. **`db/migrations/check_expediente_fields.sql`**
   - Script para verificar si los campos existen

2. **`db/migrations/add_expediente_fields_only.sql`**
   - Script seguro para agregar los campos (verifica antes de crear)

3. **`db/migrations/add_pld_validations_fields.sql`**
   - Script completo con todas las validaciones PLD (VAL-PLD-004 a VAL-PLD-015)

---

## Después de la Migración

Una vez ejecutada la migración:

1. ✅ Los campos estarán disponibles
2. ✅ El sistema validará automáticamente los expedientes
3. ✅ La fecha se actualizará automáticamente al modificar clientes
4. ✅ Podrás ver el estado del expediente en el detalle del cliente

---

## ¿Necesitas ayuda?

Si tienes problemas ejecutando la migración:

1. Verifica que tienes permisos de ALTER TABLE
2. Revisa los logs de error de MySQL
3. Asegúrate de estar usando la base de datos correcta
4. Verifica la versión de MySQL (debe ser 5.7+ o 8.0+)

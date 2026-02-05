# Guía: VAL-PLD-008 | Aviso por Umbral Individual

## Resumen

**VAL-PLD-008** requiere que las operaciones que superen el umbral configurado (en UMAs) sean avisadas al SPPLD antes del día 17 del mes siguiente.

---

## ¿Cuándo Aplica?

### ✅ Requiere Aviso:
- **Monto ≥ umbral configurado** (en UMAs)
- **Fecha de operación válida**

### ❌ NO requiere aviso:
- Monto < umbral configurado

---

## Cálculo del Umbral

### Fórmula:
```
Umbral MXN = Umbral UMA × Valor UMA
```

### Ejemplo:
- Umbral configurado: **1000 UMAs**
- Valor UMA actual: **$108.57 MXN**
- Umbral en MXN: **$108,570.00 MXN**

Si una operación es ≥ $108,570.00, **requiere aviso**.

---

## Deadline (Fecha Límite)

### Regla:
**Día 17 del mes siguiente** a la fecha de operación

### Ejemplos:
- Operación: **15 de enero 2025** → Deadline: **17 de febrero 2025**
- Operación: **28 de febrero 2025** → Deadline: **17 de marzo 2025**

---

## Cómo Registrar una Operación PLD

### API Endpoint:
```
POST api/registrar_operacion_pld.php
```

### Datos Requeridos:
```json
{
    "id_cliente": 123,
    "monto": 150000.00,
    "fecha_operacion": "2025-01-15",
    "id_fraccion": 1,  // Opcional
    "tipo_operacion": "Venta",  // Opcional
    "es_sospechosa": 0,  // Opcional
    "match_listas_restringidas": 0  // Opcional
}
```

### Respuesta Exitosa:
```json
{
    "status": "success",
    "message": "Operación registrada correctamente",
    "id_operacion": 456,
    "id_aviso": 789,  // Solo si requiere aviso
    "requiere_aviso": true,
    "tipo_aviso": "umbral_individual",
    "fecha_deadline": "2025-02-17",
    "validacion_umbral": {
        "requiere_aviso": true,
        "monto": 150000.00,
        "monto_uma": 1382.15,
        "umbral_uma": 1000.0,
        "umbral_mxn": 108570.00,
        "fecha_deadline": "2025-02-17",
        "codigo": "AVISO_REQUERIDO"
    }
}
```

---

## Validación Automática

Al registrar una operación, el sistema:

1. **Obtiene el valor UMA actual** desde `indicadores`
2. **Obtiene el umbral configurado**:
   - Primero busca umbral específico de la fracción (`cat_vulnerables.umbral_aviso_uma`)
   - Si no existe, usa umbral general (`config_empresa.umbral_aviso_uma`)
   - Si no existe, usa default: **1000 UMAs**
3. **Calcula si requiere aviso**: `monto >= (umbral_uma × valor_uma)`
4. **Si requiere aviso**:
   - Calcula deadline (día 17 del mes siguiente)
   - Registra la operación en `operaciones_pld`
   - Crea un aviso pendiente en `avisos_pld`
   - Retorna `AVISO_REQUERIDO`

---

## Ver Avisos Pendientes

### API Endpoint:
```
GET api/get_avisos_pld.php
```

### Parámetros Opcionales:
- `id_cliente`: Filtrar por cliente específico
- `estatus`: `pendiente`, `presentado`, `vencido`
- `tipo_aviso`: `umbral_individual`, `acumulacion`, etc.

### Ejemplo:
```
GET api/get_avisos_pld.php?estatus=pendiente
```

### Respuesta:
```json
{
    "status": "success",
    "avisos": [
        {
            "id_aviso": 789,
            "id_cliente": 123,
            "cliente_nombre": "Empresa ABC",
            "tipo_aviso": "umbral_individual",
            "fecha_operacion": "2025-01-15",
            "monto": 150000.00,
            "fecha_deadline": "2025-02-17",
            "estatus": "pendiente",
            "estatus_real": "pendiente"
        }
    ],
    "contadores": {
        "pendientes": 5,
        "presentados": 12,
        "vencidos": 2,
        "total": 19
    }
}
```

---

## Base de Datos

### Tabla: `operaciones_pld`

**Campos principales:**
- `id_operacion` (PK)
- `id_cliente` (FK)
- `id_fraccion` (FK, opcional)
- `tipo_operacion`
- `monto` (DECIMAL 15,2)
- `monto_uma` (DECIMAL 15,2)
- `fecha_operacion` (DATE)
- `requiere_aviso` (TINYINT 1)
- `tipo_aviso` (ENUM)
- `fecha_deadline_aviso` (DATE)
- `id_aviso_generado` (FK a `avisos_pld`)
- `id_status` (1=Activo, 0=Inactivo)

### Tabla: `avisos_pld`

**Campos principales:**
- `id_aviso` (PK)
- `id_cliente` (FK)
- `tipo_aviso` (ENUM: `umbral_individual`, `acumulacion`, etc.)
- `fecha_operacion` (DATE)
- `fecha_conocimiento` (DATETIME, para avisos 24H)
- `monto` (DECIMAL 15,2)
- `folio_sppld` (VARCHAR 100, folio del aviso en SPPLD)
- `fecha_presentacion` (DATE)
- `fecha_deadline` (DATE)
- `estatus` (ENUM: `pendiente`, `presentado`, `vencido`)
- `id_status` (1=Activo, 0=Inactivo)

---

## Configuración de Umbrales

### Umbral por Fracción:
```sql
UPDATE cat_vulnerables 
SET umbral_aviso_uma = 1000.0 
WHERE id_vulnerable = 1;
```

### Umbral General (si no hay fracción específica):
```sql
UPDATE config_empresa 
SET umbral_aviso_uma = 1000.0 
WHERE id_config = 1;
```

---

## Archivos Relacionados

- `config/pld_avisos.php` - Lógica de validación y registro
- `api/registrar_operacion_pld.php` - API para registrar operaciones
- `api/get_avisos_pld.php` - API para obtener avisos
- `db/migrations/add_pld_validations_fields.sql` - Estructura de tablas

---

## Flujo Completo

1. **Usuario registra operación PLD**
   - Llama a `api/registrar_operacion_pld.php`
   - Sistema valida umbral automáticamente

2. **Si requiere aviso:**
   - Se crea registro en `operaciones_pld`
   - Se crea aviso pendiente en `avisos_pld`
   - Se retorna `AVISO_REQUERIDO` con deadline

3. **Usuario debe presentar aviso:**
   - Antes del deadline (día 17 del mes siguiente)
   - En el SPPLD del SAT
   - Actualizar `avisos_pld.estatus = 'presentado'`
   - Guardar `folio_sppld` y `fecha_presentacion`

4. **Sistema detecta avisos vencidos:**
   - Si `fecha_deadline < CURDATE()` y `estatus = 'pendiente'`
   - Se marca como `vencido`
   - Genera alerta de incumplimiento

---

## Notas Importantes

⚠️ **Obligatorio Presentar Aviso**
- Si una operación supera el umbral, DEBE presentarse el aviso antes del deadline
- No presentar el aviso puede resultar en sanciones

⚠️ **Deadline Estricto**
- El día 17 del mes siguiente es la fecha límite
- No hay prórroga automática

✅ **Múltiples Tipos de Aviso**
- Una operación puede requerir múltiples avisos:
  - Umbral individual (VAL-PLD-008)
  - Acumulación (VAL-PLD-009)
  - Sospechosa (VAL-PLD-010)
  - Listas restringidas (VAL-PLD-011)

---

## Solución de Problemas

### "Operación no registrada"

**Causa:** Datos incompletos o monto inválido

**Solución:**
1. Verificar que `id_cliente` y `monto` estén presentes
2. Verificar que `monto > 0`

### "No se calcula el umbral correctamente"

**Causa:** Falta valor UMA o umbral configurado

**Solución:**
1. Verificar que exista registro en `indicadores` con nombre que contenga "UMA"
2. Verificar que exista `umbral_aviso_uma` en `config_empresa` o `cat_vulnerables`

### "Aviso no se genera automáticamente"

**Causa:** Error en la validación o inserción

**Solución:**
1. Revisar logs de error (`error_log`)
2. Verificar que las tablas `operaciones_pld` y `avisos_pld` existan
3. Verificar permisos de escritura en la base de datos

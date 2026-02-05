# Guía: VAL-PLD-013 - Conservación de Información

## Generalidad

La información debe conservarse por al menos **10 años**. Esto incluye:
- Expedientes de identificación
- Documentos asociados
- Avisos presentados
- Operaciones registradas
- Cambios y ediciones realizadas

---

## Validaciones

### 1. Evidencia Asociada
- ✅ Verifica que los archivos de evidencia existan físicamente
- ✅ Valida que la ruta del archivo sea accesible
- ❌ Si falta evidencia → `EXPEDIENTE_INCOMPLETO`

### 2. Plazo Vigente
- ✅ Calcula fecha de vencimiento: **10 años desde la fecha de creación**
- ✅ Valida que el plazo esté vigente (no haya vencido)
- ❌ Si está vencida → `EXPEDIENTE_INCOMPLETO`

### 3. Cambios y Ediciones
- ✅ Detecta modificaciones en la evidencia
- ✅ Registra fecha de última modificación
- ✅ Mantiene historial de cambios

---

## Resultado

### ✅ Si está completo:
```
✅ Conservación de Información Válida
Total: X evidencias disponibles
Plazo vigente: Todas las evidencias están dentro del plazo de 10 años
```

### ❌ Si está incompleto:
```
❌ Conservación de Información Incompleta
[Descripción del problema]
- Evidencias faltantes: X
- Evidencias vencidas: Y
Código: EXPEDIENTE_INCOMPLETO
```

---

## Cómo Revisar VAL-PLD-013

### 1. Acceder a la Página de Conservación

1. En el menú principal, busca **"Conservación PLD"**
2. O accede directamente a: `conservacion_pld.php`

### 2. Verificar Estado General

En la página verás:

- **Estadísticas Rápidas:**
  - Total de evidencias registradas
  - Evidencias disponibles (archivo existe y no vencida)
  - Evidencias faltantes (archivo no existe)
  - Evidencias vencidas (plazo de 10 años expirado)

### 3. Revisar Evidencias por Cliente

1. Usa el filtro **"Cliente"** para ver evidencias de un cliente específico
2. La tabla mostrará:
   - Fecha de creación
   - Tipo de evidencia (expediente, documento, aviso, operación, cambio)
   - Archivo asociado (con enlace para descargar)
   - Fecha de vencimiento
   - Días restantes
   - Estado (Disponible, Faltante, Vencida)

### 4. Revisar Evidencias por Tipo

1. Usa el filtro **"Tipo de Evidencia"** para filtrar por:
   - Expediente
   - Documento
   - Aviso
   - Operación
   - Cambio

### 5. Revisar Evidencias por Estado

1. Usa el filtro **"Estado"** para ver:
   - **Disponible**: Archivo existe y plazo vigente
   - **Faltante**: Archivo no existe
   - **Vencida**: Plazo de 10 años expirado

---

## Registrar Nueva Evidencia

### Desde la UI:

1. Haz clic en **"Registrar Evidencia"**
2. Completa el formulario:
   - **Cliente** (opcional): Selecciona el cliente asociado
   - **Tipo de Evidencia** *: Selecciona el tipo
   - **ID Operación** (opcional): Si está asociada a una operación PLD
   - **ID Aviso** (opcional): Si está asociada a un aviso PLD
   - **Archivo de Evidencia** *: Sube el archivo
3. Haz clic en **"Registrar Evidencia"**

### Automáticamente:

El sistema puede registrar evidencia automáticamente cuando:
- Se crea una operación PLD (si hay documentos asociados)
- Se genera un aviso PLD (si hay documentos asociados)

**Nota:** Por ahora, el registro automático está preparado pero se recomienda registrar manualmente desde la UI para mayor control.

---

## API Endpoints

### Obtener Evidencias:

```
GET api/get_conservacion_info.php
```

**Parámetros opcionales:**
- `id_cliente`: Filtrar por cliente
- `id_operacion`: Filtrar por operación
- `id_aviso`: Filtrar por aviso
- `tipo_evidencia`: Filtrar por tipo
- `expediente_incompleto`: 1 para ver solo incompletas

**Ejemplo:**
```
GET api/get_conservacion_info.php?id_cliente=123&tipo_evidencia=expediente
```

**Respuesta:**
```json
{
    "status": "success",
    "evidencias": [
        {
            "id_conservacion": 1,
            "id_cliente": 123,
            "cliente_nombre": "Empresa ABC",
            "tipo_evidencia": "expediente",
            "ruta_evidencia": "uploads/conservacion/20250121_123456_abc123.pdf",
            "fecha_creacion": "2025-01-21 10:30:00",
            "fecha_vencimiento": "2035-01-21",
            "estado": "disponible",
            "archivo_existe": true,
            "esta_vencida": false,
            "dias_restantes": 3650
        }
    ],
    "validacion": {
        "valido": true,
        "expediente_incompleto": false,
        "disponibles": 1,
        "total": 1
    },
    "estadisticas": {
        "total": 1,
        "disponibles": 1,
        "faltantes": 0,
        "vencidas": 0
    }
}
```

### Registrar Evidencia:

```
POST api/registrar_conservacion.php
```

**Body (FormData):**
- `tipo_evidencia` *: expediente, documento, aviso, operacion, cambio
- `archivo_evidencia` *: Archivo a subir
- `id_cliente` (opcional): ID del cliente
- `id_operacion` (opcional): ID de la operación
- `id_aviso` (opcional): ID del aviso

**Respuesta:**
```json
{
    "status": "success",
    "message": "Evidencia registrada para conservación",
    "id_conservacion": 1,
    "fecha_vencimiento": "2035-01-21"
}
```

---

## Base de Datos

### Tabla: `conservacion_informacion_pld`

**Campos principales:**
- `id_conservacion` (PK)
- `id_cliente` (FK, opcional)
- `id_operacion` (FK, opcional)
- `id_aviso` (FK, opcional)
- `tipo_evidencia` (ENUM: expediente, documento, aviso, operacion, cambio)
- `ruta_evidencia` (TEXT): Ruta del archivo
- `fecha_creacion` (DATETIME)
- `fecha_vencimiento` (DATE): 10 años desde creación
- `expediente_incompleto` (TINYINT 1): Flag si falta evidencia o está vencida
- `id_status` (TINYINT 1)

---

## Bloqueo de Operaciones

Si la conservación de información está incompleta (`EXPEDIENTE_INCOMPLETO`), se puede bloquear:

- ✅ Operaciones PLD (si se integra con middleware)
- ✅ Generación de avisos (si se integra con middleware)

**Nota:** Por ahora, el bloqueo está disponible en el middleware pero no está activado por defecto en todas las operaciones. Se puede activar según necesidad.

---

## Alertas y Notificaciones

El sistema muestra alertas cuando:

1. **Evidencias Faltantes:**
   - Archivo no existe en la ruta registrada
   - Se marca como `expediente_incompleto = 1`

2. **Evidencias Vencidas:**
   - Fecha de vencimiento < fecha actual
   - Se marca como `expediente_incompleto = 1`

3. **Próximas a Vencer:**
   - Menos de 1 año restante (se puede configurar)

---

## Mejores Prácticas

1. **Registrar inmediatamente:**
   - Registra evidencia tan pronto como se crea una operación o aviso
   - No esperes a que se requiera para una auditoría

2. **Verificar periódicamente:**
   - Revisa mensualmente las evidencias próximas a vencer
   - Renueva evidencias que estén por vencer

3. **Organizar archivos:**
   - Usa nombres descriptivos para los archivos
   - Mantén una estructura de carpetas organizada

4. **Backup:**
   - Realiza backups periódicos de la carpeta `uploads/conservacion/`
   - Verifica que los backups estén completos

---

## Troubleshooting

### Problema: "Archivo no encontrado"

**Causa:** El archivo fue movido o eliminado del servidor.

**Solución:**
1. Verifica que el archivo exista en la ruta registrada
2. Si fue movido, actualiza la ruta en la base de datos
3. Si fue eliminado, sube el archivo nuevamente

### Problema: "Evidencia vencida"

**Causa:** Han pasado más de 10 años desde la fecha de creación.

**Solución:**
1. Si la evidencia aún es relevante, renueva el registro
2. Si ya no es necesaria, puedes marcarla como inactiva (`id_status = 0`)

### Problema: "No puedo subir archivo"

**Causa:** Permisos de escritura o tamaño de archivo.

**Solución:**
1. Verifica permisos de la carpeta `uploads/conservacion/`
2. Verifica límite de tamaño de archivo en PHP (`upload_max_filesize`)
3. Verifica espacio en disco

---

## Integración con Otras Validaciones

VAL-PLD-013 se relaciona con:

- **VAL-PLD-005**: Expediente de identificación (debe conservarse)
- **VAL-PLD-008 a VAL-PLD-012**: Avisos e informes (deben conservarse)
- **VAL-PLD-014**: Visitas de verificación (requieren evidencias disponibles)

---

## Contacto y Soporte

Para dudas o problemas con VAL-PLD-013, consulta:
- Documentación técnica en `config/pld_conservacion.php`
- API endpoints en `api/get_conservacion_info.php` y `api/registrar_conservacion.php`
- Interfaz de usuario en `conservacion_pld.php`

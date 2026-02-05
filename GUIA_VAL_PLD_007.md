# Guía: VAL-PLD-007 | Identificación de Beneficiario Controlador

## Resumen

**VAL-PLD-007** requiere identificar al beneficiario controlador cuando aplica, mediante un formato establecido y solicitud de documentación.

---

## ¿Cuándo Aplica?

### ✅ Aplica para:
- **Personas Morales** (Sociedades, Asociaciones, etc.)
- **Fideicomisos**

### ❌ NO aplica para:
- **Personas Físicas** (puede requerir declaración pero no es obligatorio)

---

## Requisitos por Tipo de Persona

### Persona Moral / Fideicomiso:
- ✅ **Documentación obligatoria**
- ✅ Al menos 1 beneficiario controlador registrado
- ✅ Documento de identificación cargado
- ✅ Datos completos (nombre, RFC, porcentaje de participación)

### Persona Física:
- ⚠️ **Declaración correspondiente** (opcional, no bloquea)

---

## Cómo Gestionar Beneficiarios Controladores

### Desde el Detalle del Cliente:

1. **Ir al detalle del cliente** (`/cliente_detalle.php?id=[ID_CLIENTE]`)

2. **Verificar si aplica:**
   - La sección "Beneficiario Controlador" solo aparece si el cliente es **Persona Moral** o **Fideicomiso**

3. **Agregar Beneficiario:**
   - Haz clic en el botón **"Agregar"** en la sección de Beneficiario Controlador
   - Completa el formulario:
     - Tipo de Persona (Física o Moral)
     - Nombre Completo *
     - RFC
     - Porcentaje de Participación (%)
     - Documento de Identificación (requerido para persona moral)
     - Declaración Jurada (requerido para persona física)
   - Guarda

4. **Editar/Eliminar:**
   - Usa los botones de editar (lápiz) o eliminar (basura) en la tabla

---

## Validación Automática

El sistema valida automáticamente:

1. **Si es requerido:**
   - Verifica si el cliente es persona moral o fideicomiso

2. **Si está identificado:**
   - Verifica que exista al menos 1 beneficiario activo

3. **Si está completo:**
   - Verifica que cada beneficiario tenga:
     - Nombre completo
     - RFC (para persona moral)
     - Documentación según tipo:
       - Persona moral: Documento de identificación
       - Persona física: Declaración jurada

---

## Bloqueo de Operaciones

Si el beneficiario controlador **NO está identificado** o está **incompleto**, se bloquean:

- ✅ Confirmación de selección PLD (`api/confirm_pld_selection.php`)
- ✅ Creación de clientes nuevos (si es moral/fideicomiso)
- ✅ Actualización de clientes (si es moral/fideicomiso)

**NO se bloquean:**
- Consultas PLD (pueden ser necesarias para completar el expediente)

---

## Estado en la Interfaz

En el detalle del cliente verás:

### ✅ Si está identificado:
```
✅ Beneficiario Controlador Identificado
Total: X beneficiario(s)
```

### ❌ Si NO está identificado:
```
❌ Beneficiario Controlador NO Identificado
[Descripción del problema]
```

### ℹ️ Si no aplica:
```
ℹ️ No aplica para este tipo de cliente (persona física)
```

---

## Tabla de Beneficiarios

La tabla muestra:
- **Tipo**: Física o Moral
- **Nombre**: Nombre completo
- **RFC**: RFC del beneficiario
- **Participación**: Porcentaje de participación
- **Documentación**: ✅ o ❌ según tenga documentación
- **Acciones**: Editar / Eliminar

---

## API Endpoints

### Listar beneficiarios:
```
GET api/beneficiario_controlador.php?id_cliente=[ID]
```

### Crear/Actualizar:
```
POST api/beneficiario_controlador.php
Body: FormData con:
- id_cliente
- id_beneficiario (opcional, para actualizar)
- tipo_persona
- nombre_completo
- rfc
- porcentaje_participacion
- documento_identificacion (archivo)
- declaracion_jurada (archivo)
```

### Eliminar:
```
DELETE api/beneficiario_controlador.php?id_beneficiario=[ID]
```

---

## Base de Datos

### Tabla: `clientes_beneficiario_controlador`

**Campos:**
- `id_beneficiario` (PK)
- `id_cliente` (FK)
- `tipo_persona` (ENUM: 'fisica', 'moral')
- `nombre_completo`
- `rfc`
- `porcentaje_participacion`
- `documento_identificacion` (TEXT - ruta al archivo)
- `declaracion_jurada` (TEXT - ruta al archivo)
- `fecha_registro`
- `fecha_ultima_actualizacion`
- `id_status` (1=Activo, 0=Inactivo)

---

## Archivos Relacionados

- `config/pld_beneficiario_controlador.php` - Lógica de validación
- `api/beneficiario_controlador.php` - API endpoints
- `cliente_detalle.php` - Interfaz de gestión
- `config/pld_middleware.php` - Integración en operaciones PLD

---

## Notas Importantes

⚠️ **Obligatorio para Morales y Fideicomisos**
- Si el cliente es persona moral o fideicomiso, DEBE tener al menos 1 beneficiario controlador identificado
- Sin esto, las operaciones PLD se bloquean

⚠️ **Documentación Requerida**
- Persona moral: Documento de identificación obligatorio
- Persona física: Declaración jurada obligatoria

✅ **Múltiples Beneficiarios**
- Puede haber más de un beneficiario controlador
- Todos deben estar completos para que la validación pase

---

## Solución de Problemas

### "Beneficiario Controlador NO Identificado"

**Causa:** No hay beneficiarios registrados o están incompletos

**Solución:**
1. Agregar al menos 1 beneficiario controlador
2. Completar todos los campos requeridos
3. Subir la documentación correspondiente

### Error al guardar beneficiario

**Causa:** Puede ser problema de permisos de escritura en la carpeta de uploads

**Solución:**
1. Verificar permisos de la carpeta `uploads/beneficiarios/`
2. Verificar que el servidor tenga permisos de escritura

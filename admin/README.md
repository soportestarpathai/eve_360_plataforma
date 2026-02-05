# Panel de Administración - EVE 360

## Propósito

El panel de administración (`/admin`) es exclusivamente para **control y configuración de la plataforma**, no para gestión operativa de clientes o usuarios.

## Estructura del Admin

### ✅ Funciones Correctas del Admin

1. **Dashboard (`index.php`)**
   - Estadísticas de la plataforma
   - Uso de licencias de usuarios
   - Uso de API
   - Actividad reciente (bitácora)
   - Vista general del sistema

2. **Usuarios del Sistema (`users.php`)**
   - Gestión de usuarios que acceden a la plataforma
   - Crear, editar, eliminar usuarios
   - Asignar permisos y roles
   - Activar/desactivar usuarios
   - ⚠️ **NO es para gestionar clientes** (los clientes se gestionan en `/clientes.php`)

3. **Configuración (`config.php`)**
   - Configuración general de la empresa
   - Configuración PLD del sujeto obligado:
     - Padrón PLD (VAL-PLD-001, VAL-PLD-002)
     - Responsables PLD (VAL-PLD-003) - Configuración de designación
   - Configuración de menús
   - Parámetros del sistema

4. **Módulos (`modules.php` / `modulos.php`)**
   - Habilitar/deshabilitar módulos del sistema
   - Control de funcionalidades disponibles

5. **Reportes (`reports.php` / `reportes.php`)**
   - Catálogo de tipos de reporte
   - Configuración de reportes del sistema

### ❌ Funciones que NO deben estar en el Admin

1. **Gestión de Clientes**
   - Los clientes se gestionan en `/clientes.php`
   - Los expedientes PLD se revisan en `/cliente_detalle.php`
   - ❌ `expedientes_pld.php` - **ELIMINADO del menú** (ya está en cliente_detalle.php)

2. **Representación Legal de Usuarios**
   - Cada usuario gestiona su propia representación en `/mi_cuenta.php`
   - ❌ `representacion_legal.php` - **ELIMINADO del menú** (ya está en mi_cuenta.php)

## Separación de Responsabilidades

### Admin = Control de la Plataforma
- Configuración del sistema
- Gestión de usuarios del sistema
- Parámetros globales
- Módulos y funcionalidades
- Reportes del sistema

### Aplicación Principal = Operación PLD
- Gestión de clientes
- Expedientes de identificación
- Operaciones PLD
- Avisos y reportes PLD
- Representación legal (cada usuario en su cuenta)

## Archivos en el Admin

```
admin/
├── index.php              ✅ Dashboard del admin
├── users.php              ✅ Usuarios del sistema
├── config.php             ✅ Configuración de la plataforma
├── modules.php            ✅ Gestión de módulos
├── modulos.php            ✅ (alias)
├── reports.php            ✅ Catálogo de reportes
├── reportes.php           ✅ (alias)
├── login.php              ✅ Login del admin
├── logout.php             ✅ Logout
├── send_code.php          ✅ Envío de código de acceso
├── header.php             ✅ Header común
├── expedientes_pld.php    ⚠️ Vista de auditoría (no en menú)
└── representacion_legal.php ⚠️ Vista de auditoría (no en menú)
```

## Notas Importantes

- Los archivos `expedientes_pld.php` y `representacion_legal.php` pueden mantenerse como **vistas de auditoría** pero **NO están en el menú** porque la gestión principal está en la aplicación.
- Si se necesita acceso de auditoría, se puede agregar un enlace directo o mantenerlos para uso interno.
- El admin **NO debe** tener funciones de gestión operativa de clientes.

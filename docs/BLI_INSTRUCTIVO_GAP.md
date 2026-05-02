# BLI (Fracción IX) - Análisis de Gap vs `instructivo_bli.xlsx`

Fecha: 2026-04-19  
Fuente revisada: `instructivo_bli.xlsx` (hoja `F. IX Blindaje`)

## 1) Estado actual de implementación

Archivos actuales BLI:
- `operacion_bli.php`
- `api/registrar_aviso_bli.php`
- `config/pld_fraccion_ix.php`
- `config/bli_catalogos.php`
- `config/bli_xml_helper.php`

Cobertura actual:
- Alta operativa base (cliente, aviso, detalle simple, liquidación simple).
- Registro PLD con umbrales IX:
  - Identificación: 2,410 UMA
  - Aviso/acumulación: 4,815 UMA
- XML base generado con namespace BLI.

## 2) Campos/estructuras del instructivo BLI que hoy faltan o están parciales

## 2.1 Nivel aviso (cabecera)
- `modificatorio` completo:
  - `folio_modificacion`
  - `descripcion_modificacion`
  - Estado: **parcial** (API actual no lo procesa como estructura completa BLI).

## 2.2 Persona del aviso
- `persona_aviso` con soporte completo a:
  - `tipo_persona`:
    - `persona_fisica`
    - `persona_moral` + `representante_apoderado`
    - `fideicomiso` + `apoderado_delegado`
  - `tipo_domicilio`:
    - `nacional`
    - `extranjero`
  - `telefono`
  - Estado: **faltante** en captura BLI actual (hoy la API BLI no exige ni estructura completa como en instructivo).

## 2.3 Dueño beneficiario
- `dueno_beneficiario` (opcional) con `tipo_persona` simple:
  - `persona_fisica` / `persona_moral` / `fideicomiso`
  - Estado: **faltante** en BLI actual.

## 2.4 Detalle de operación BLI (estructura específica)
- `detalle_operaciones/datos_operacion`:
  - `fecha_operacion`
  - `codigo_postal`
  - `tipo_operacion`
  - `tipo_bien`
    - `datos_vehiculo_terrestre`:
      - `marca_fabricante`, `modelo`, `anio`, `vin`, `repuve`, `placas`, `estado_bien`, `nivel_blindaje`
    - `datos_inmueble`:
      - `tipo_inmueble`, `codigo_postal`, `datos_parte_blindada`
      - `parte_blindada`, `nivel_blindaje`
  - `datos_liquidacion`:
    - `fecha_pago`, `instrumento_monetario`, `moneda`, `monto_operacion`
  - Estado: **parcial** (actualmente sólo se captura un set reducido y no existe rama completa vehículo/inmueble del instructivo).

## 3) Reglas de negocio relevantes detectadas en instructivo

1. `tipo_alerta`:
- Debe existir en catálogo UIF.
- Si `prioridad = 2`, `tipo_alerta` debe ser distinto de `100`.

2. `tipo_operacion`:
- Debe pertenecer al catálogo UIF de Tipo de Operación para BLI.

3. `estado_bien`, `nivel_blindaje`, `tipo_inmueble`, `parte_blindada`:
- Deben existir en sus catálogos UIF respectivos.

4. `moneda` vs `instrumento_monetario`:
- Si instrumento = 13 o 14, moneda debe estar entre 159 y 179.
- Si instrumento distinto de 13/14, moneda no debe estar entre 159 y 179.

5. Códigos postales:
- Deben existir en SEPOMEX (regla ya usada en otras fracciones y parcialmente en BLI).

## 4) Catálogos requeridos para cerrar BLI al 100%

Compartidos (ya reutilizables):
- País
- Actividad económica
- Giro mercantil
- Moneda
- Instrumento monetario

Específicos BLI (faltan definir/confirmar oficialmente en código):
- Tipo de alerta BLI (clave completa UIF).
- Tipo de operación BLI.
- Tipo de bien BLI.
- Estado del bien (vehículo terrestre blindado).
- Tipo de blindaje.
- Tipo de inmueble.
- Parte de inmueble.

## 5) Conclusión

BLI está **operable en modo base**, pero para quedar **alineado al instructivo BLI** se requiere ampliar:
- formulario,
- validaciones API,
- payload XML BLI,
- y catálogos específicos BLI.

Este gap es normal: el módulo actual sirve para operación inicial, pero aún no representa el 100% de la estructura XML definida en el instructivo.


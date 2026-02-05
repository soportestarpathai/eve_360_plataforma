-- Script para verificar qué tiene y qué falta en el expediente PLD de un cliente
-- Uso: Reemplaza [ID_CLIENTE] con el ID del cliente que quieres verificar

SET @id_cliente = [ID_CLIENTE]; -- ⚠️ CAMBIA ESTE VALOR

-- ============================================
-- RESUMEN DEL EXPEDIENTE PLD
-- ============================================

SELECT 
    '=== RESUMEN EXPEDIENTE PLD ===' as info,
    @id_cliente as id_cliente;

-- 1. DATOS BÁSICOS
SELECT 
    '1. DATOS BÁSICOS' as seccion,
    CASE 
        WHEN EXISTS(SELECT 1 FROM clientes_fisicas WHERE id_cliente = @id_cliente) THEN 'Persona Física'
        WHEN EXISTS(SELECT 1 FROM clientes_morales WHERE id_cliente = @id_cliente) THEN 'Persona Moral'
        WHEN EXISTS(SELECT 1 FROM clientes_fideicomisos WHERE id_cliente = @id_cliente) THEN 'Fideicomiso'
        ELSE 'NO DEFINIDO'
    END as tipo_persona,
    CASE 
        WHEN EXISTS(
            SELECT 1 FROM clientes_fisicas 
            WHERE id_cliente = @id_cliente 
            AND nombre IS NOT NULL AND nombre != '' 
            AND apellido_paterno IS NOT NULL AND apellido_paterno != ''
        ) THEN '✅ COMPLETO'
        WHEN EXISTS(
            SELECT 1 FROM clientes_morales 
            WHERE id_cliente = @id_cliente 
            AND razon_social IS NOT NULL AND razon_social != ''
        ) THEN '✅ COMPLETO'
        WHEN EXISTS(
            SELECT 1 FROM clientes_fideicomisos 
            WHERE id_cliente = @id_cliente 
            AND numero_fideicomiso IS NOT NULL AND numero_fideicomiso != ''
        ) THEN '✅ COMPLETO'
        ELSE '❌ INCOMPLETO'
    END as estado;

-- 2. IDENTIFICACIONES
SELECT 
    '2. IDENTIFICACIONES' as seccion,
    COUNT(*) as cantidad_total,
    SUM(CASE WHEN (id_status = 1 OR id_status IS NULL) THEN 1 ELSE 0 END) as cantidad_activas,
    CASE 
        WHEN SUM(CASE WHEN (id_status = 1 OR id_status IS NULL) THEN 1 ELSE 0 END) > 0 THEN '✅ TIENE'
        ELSE '❌ FALTA'
    END as estado
FROM clientes_identificaciones
WHERE id_cliente = @id_cliente;

-- Detalle de identificaciones
SELECT 
    '   Detalle:' as detalle,
    ci.id_cliente_identificacion,
    ti.nombre as tipo,
    ci.numero_identificacion,
    CASE WHEN ci.id_status = 1 THEN 'Activa' ELSE 'Inactiva' END as estado
FROM clientes_identificaciones ci
LEFT JOIN cat_tipo_identificacion ti ON ci.id_tipo_identificacion = ti.id_tipo_identificacion
WHERE ci.id_cliente = @id_cliente
ORDER BY ci.id_cliente_identificacion;

-- 3. DIRECCIONES
SELECT 
    '3. DIRECCIONES' as seccion,
    COUNT(*) as cantidad_total,
    SUM(CASE WHEN (id_status = 1 OR id_status IS NULL) THEN 1 ELSE 0 END) as cantidad_activas,
    CASE 
        WHEN SUM(CASE WHEN (id_status = 1 OR id_status IS NULL) THEN 1 ELSE 0 END) > 0 THEN '✅ TIENE'
        ELSE '❌ FALTA'
    END as estado
FROM clientes_direcciones
WHERE id_cliente = @id_cliente;

-- Detalle de direcciones
SELECT 
    '   Detalle:' as detalle,
    cd.id_cliente_direccion,
    CONCAT(cd.calle, ', ', cd.colonia, ', CP: ', cd.codigo_postal) as direccion,
    CASE WHEN cd.id_status = 1 THEN 'Activa' ELSE 'Inactiva' END as estado
FROM clientes_direcciones cd
WHERE cd.id_cliente = @id_cliente
ORDER BY cd.id_cliente_direccion;

-- 4. CONTACTOS
SELECT 
    '4. CONTACTOS' as seccion,
    COUNT(*) as cantidad_total,
    SUM(CASE WHEN (id_status = 1 OR id_status IS NULL) THEN 1 ELSE 0 END) as cantidad_activos,
    CASE 
        WHEN SUM(CASE WHEN (id_status = 1 OR id_status IS NULL) THEN 1 ELSE 0 END) > 0 THEN '✅ TIENE'
        ELSE '❌ FALTA'
    END as estado
FROM clientes_contactos
WHERE id_cliente = @id_cliente;

-- Detalle de contactos
SELECT 
    '   Detalle:' as detalle,
    cc.id_cliente_contacto,
    tc.nombre as tipo,
    cc.dato_contacto,
    CASE WHEN cc.id_status = 1 THEN 'Activo' ELSE 'Inactivo' END as estado
FROM clientes_contactos cc
LEFT JOIN cat_tipo_contacto tc ON cc.id_tipo_contacto = tc.id_tipo_contacto
WHERE cc.id_cliente = @id_cliente
ORDER BY cc.id_cliente_contacto;

-- 5. DOCUMENTOS
SELECT 
    '5. DOCUMENTOS' as seccion,
    COUNT(*) as cantidad_total,
    SUM(CASE WHEN id_status = 1 AND ruta IS NOT NULL AND ruta != '' THEN 1 ELSE 0 END) as cantidad_con_archivo,
    CASE 
        WHEN SUM(CASE WHEN id_status = 1 AND ruta IS NOT NULL AND ruta != '' THEN 1 ELSE 0 END) > 0 THEN '✅ TIENE'
        ELSE '❌ FALTA'
    END as estado
FROM clientes_documentos
WHERE id_cliente = @id_cliente;

-- Detalle de documentos
SELECT 
    '   Detalle:' as detalle,
    cd.id_cliente_documento,
    cd.descripcion,
    CASE WHEN cd.ruta IS NOT NULL AND cd.ruta != '' THEN '✅ Con archivo' ELSE '❌ Sin archivo' END as tiene_archivo,
    CASE WHEN cd.id_status = 1 THEN 'Activo' ELSE 'Inactivo' END as estado
FROM clientes_documentos cd
WHERE cd.id_cliente = @id_cliente
ORDER BY cd.id_cliente_documento;

-- ============================================
-- RESUMEN FINAL
-- ============================================

SELECT 
    '=== RESUMEN FINAL ===' as info,
    CASE 
        WHEN EXISTS(
            SELECT 1 FROM clientes_fisicas 
            WHERE id_cliente = @id_cliente 
            AND nombre IS NOT NULL AND nombre != '' 
            AND apellido_paterno IS NOT NULL AND apellido_paterno != ''
        ) OR EXISTS(
            SELECT 1 FROM clientes_morales 
            WHERE id_cliente = @id_cliente 
            AND razon_social IS NOT NULL AND razon_social != ''
        ) OR EXISTS(
            SELECT 1 FROM clientes_fideicomisos 
            WHERE id_cliente = @id_cliente 
            AND numero_fideicomiso IS NOT NULL AND numero_fideicomiso != ''
        ) THEN '✅'
        ELSE '❌'
    END as datos_basicos,
    CASE 
        WHEN (SELECT COUNT(*) FROM clientes_identificaciones WHERE id_cliente = @id_cliente AND (id_status = 1 OR id_status IS NULL)) > 0 THEN '✅'
        ELSE '❌'
    END as identificaciones,
    CASE 
        WHEN (SELECT COUNT(*) FROM clientes_direcciones WHERE id_cliente = @id_cliente AND (id_status = 1 OR id_status IS NULL)) > 0 THEN '✅'
        ELSE '❌'
    END as direcciones,
    CASE 
        WHEN (SELECT COUNT(*) FROM clientes_contactos WHERE id_cliente = @id_cliente AND (id_status = 1 OR id_status IS NULL)) > 0 THEN '✅'
        ELSE '❌'
    END as contactos,
    CASE 
        WHEN (SELECT COUNT(*) FROM clientes_documentos WHERE id_cliente = @id_cliente AND id_status = 1 AND ruta IS NOT NULL AND ruta != '') > 0 THEN '✅'
        ELSE '❌'
    END as documentos;

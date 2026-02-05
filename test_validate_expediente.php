<?php
/**
 * Script de prueba para verificar qué falta en el expediente
 * Uso: php test_validate_expediente.php [id_cliente]
 */

session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/pld_expediente.php';

$id_cliente = $argv[1] ?? $_GET['id'] ?? null;

if (!$id_cliente) {
    die("Uso: php test_validate_expediente.php [id_cliente]\n");
}

echo "=== Análisis Detallado del Expediente PLD ===\n";
echo "ID Cliente: $id_cliente\n\n";

try {
    // Obtener datos del cliente
    $stmt = $pdo->prepare("SELECT c.*, tp.nombre as tipo_persona_nombre, tp.es_fisica, tp.es_moral, tp.es_fideicomiso
                           FROM clientes c
                           LEFT JOIN cat_tipo_persona tp ON c.id_tipo_persona = tp.id_tipo_persona
                           WHERE c.id_cliente = ?");
    $stmt->execute([$id_cliente]);
    $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$cliente) {
        die("ERROR: Cliente no encontrado\n");
    }
    
    echo "--- Información del Cliente ---\n";
    echo "Tipo de Persona: {$cliente['tipo_persona_nombre']}\n";
    echo "Es Física: " . ($cliente['es_fisica'] > 0 ? 'SÍ' : 'NO') . "\n";
    echo "Es Moral: " . ($cliente['es_moral'] > 0 ? 'SÍ' : 'NO') . "\n";
    echo "Es Fideicomiso: " . ($cliente['es_fideicomiso'] > 0 ? 'SÍ' : 'NO') . "\n\n";
    
    // Verificar datos básicos
    echo "--- 1. Datos Básicos ---\n";
    if ($cliente['es_fisica'] > 0) {
        $stmt = $pdo->prepare("SELECT * FROM clientes_fisicas WHERE id_cliente = ?");
        $stmt->execute([$id_cliente]);
        $fisica = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$fisica) {
            echo "❌ NO existe registro en clientes_fisicas\n";
        } else {
            echo "✓ Existe registro en clientes_fisicas\n";
            echo "  - Nombre: " . ($fisica['nombre'] ?? 'VACÍO') . "\n";
            echo "  - Apellido Paterno: " . ($fisica['apellido_paterno'] ?? 'VACÍO') . "\n";
            echo "  - Apellido Materno: " . ($fisica['apellido_materno'] ?? 'VACÍO') . "\n";
            
            if (empty($fisica['nombre']) || empty($fisica['apellido_paterno'])) {
                echo "❌ FALTA: Datos básicos de persona física (nombre, apellidos)\n";
            } else {
                echo "✓ Datos básicos completos\n";
            }
        }
    } elseif ($cliente['es_moral'] > 0) {
        $stmt = $pdo->prepare("SELECT * FROM clientes_morales WHERE id_cliente = ?");
        $stmt->execute([$id_cliente]);
        $moral = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$moral) {
            echo "❌ NO existe registro en clientes_morales\n";
        } else {
            echo "✓ Existe registro en clientes_morales\n";
            echo "  - Razón Social: " . ($moral['razon_social'] ?? 'VACÍO') . "\n";
            
            if (empty($moral['razon_social'])) {
                echo "❌ FALTA: Datos básicos de persona moral (razón social)\n";
            } else {
                echo "✓ Datos básicos completos\n";
            }
        }
    } elseif ($cliente['es_fideicomiso'] > 0) {
        $stmt = $pdo->prepare("SELECT * FROM clientes_fideicomisos WHERE id_cliente = ?");
        $stmt->execute([$id_cliente]);
        $fideicomiso = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$fideicomiso) {
            echo "❌ NO existe registro en clientes_fideicomisos\n";
        } else {
            echo "✓ Existe registro en clientes_fideicomisos\n";
            echo "  - Número Fideicomiso: " . ($fideicomiso['numero_fideicomiso'] ?? 'VACÍO') . "\n";
            
            if (empty($fideicomiso['numero_fideicomiso'])) {
                echo "❌ FALTA: Datos básicos de fideicomiso (número de fideicomiso)\n";
            } else {
                echo "✓ Datos básicos completos\n";
            }
        }
    }
    echo "\n";
    
    // Verificar identificaciones
    echo "--- 2. Identificaciones ---\n";
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM clientes_identificaciones WHERE id_cliente = ? AND id_status = 1");
    $stmt->execute([$id_cliente]);
    $identificaciones = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Identificaciones activas: {$identificaciones['count']}\n";
    if ($identificaciones['count'] == 0) {
        echo "❌ FALTA: Identificaciones oficiales\n";
    } else {
        echo "✓ Tiene identificaciones\n";
        // Mostrar detalles
        $stmt = $pdo->prepare("SELECT ci.*, ti.nombre as tipo_nombre 
                               FROM clientes_identificaciones ci
                               LEFT JOIN cat_tipo_identificacion ti ON ci.id_tipo_identificacion = ti.id_tipo_identificacion
                               WHERE ci.id_cliente = ? AND ci.id_status = 1");
        $stmt->execute([$id_cliente]);
        $ids = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($ids as $id) {
            echo "  - {$id['tipo_nombre']}: {$id['numero_identificacion']}\n";
        }
    }
    echo "\n";
    
    // Verificar direcciones
    echo "--- 3. Direcciones ---\n";
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM clientes_direcciones WHERE id_cliente = ? AND id_status = 1");
    $stmt->execute([$id_cliente]);
    $direcciones = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Direcciones activas: {$direcciones['count']}\n";
    if ($direcciones['count'] == 0) {
        echo "❌ FALTA: Direcciones\n";
    } else {
        echo "✓ Tiene direcciones\n";
    }
    echo "\n";
    
    // Verificar contactos
    echo "--- 4. Contactos ---\n";
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM clientes_contactos WHERE id_cliente = ? AND id_status = 1");
    $stmt->execute([$id_cliente]);
    $contactos = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Contactos activos: {$contactos['count']}\n";
    if ($contactos['count'] == 0) {
        echo "❌ FALTA: Contactos (teléfono, email)\n";
    } else {
        echo "✓ Tiene contactos\n";
        // Mostrar detalles
        $stmt = $pdo->prepare("SELECT cc.*, tc.nombre as tipo_nombre 
                               FROM clientes_contactos cc
                               LEFT JOIN cat_tipo_contacto tc ON cc.id_tipo_contacto = tc.id_tipo_contacto
                               WHERE cc.id_cliente = ? AND cc.id_status = 1");
        $stmt->execute([$id_cliente]);
        $contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($contacts as $c) {
            echo "  - {$c['tipo_nombre']}: {$c['dato_contacto']}\n";
        }
    }
    echo "\n";
    
    // Verificar documentos
    echo "--- 5. Documentos ---\n";
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM clientes_documentos 
                           WHERE id_cliente = ? AND id_status = 1 AND ruta IS NOT NULL AND ruta != ''");
    $stmt->execute([$id_cliente]);
    $documentos = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Documentos con archivo: {$documentos['count']}\n";
    if ($documentos['count'] == 0) {
        echo "❌ FALTA: Documentos de soporte\n";
    } else {
        echo "✓ Tiene documentos\n";
        // Mostrar detalles
        $stmt = $pdo->prepare("SELECT * FROM clientes_documentos 
                               WHERE id_cliente = ? AND id_status = 1 AND ruta IS NOT NULL AND ruta != ''");
        $stmt->execute([$id_cliente]);
        $docs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($docs as $doc) {
            echo "  - {$doc['descripcion']}: {$doc['ruta']}\n";
        }
    }
    echo "\n";
    
    // Ejecutar validación completa
    echo "--- Resultado de la Validación ---\n";
    $result = validateExpedienteCompleto($pdo, $id_cliente);
    echo "Completo: " . ($result['completo'] ? 'SÍ ✓' : 'NO ✗') . "\n";
    echo "Razón: {$result['razon']}\n";
    if (!empty($result['faltantes'])) {
        echo "\nElementos Faltantes:\n";
        foreach ($result['faltantes'] as $index => $faltante) {
            echo "  " . ($index + 1) . ". $faltante\n";
        }
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Archivo: " . $e->getFile() . "\n";
    echo "Línea: " . $e->getLine() . "\n";
}

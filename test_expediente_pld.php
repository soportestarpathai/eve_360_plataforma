<?php
/**
 * Script de prueba para validar expediente PLD
 * Uso: php test_expediente_pld.php [id_cliente]
 */

session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/pld_expediente.php';

$id_cliente = $argv[1] ?? $_GET['id'] ?? null;

if (!$id_cliente) {
    die("Uso: php test_expediente_pld.php [id_cliente]\n");
}

echo "=== Prueba de Validación de Expediente PLD ===\n";
echo "ID Cliente: $id_cliente\n\n";

try {
    // Verificar conexión
    if (!isset($pdo) || $pdo === null) {
        die("ERROR: No hay conexión a la base de datos\n");
    }
    echo "✓ Conexión a BD: OK\n";
    
    // Verificar que el cliente existe
    $stmt = $pdo->prepare("SELECT id_cliente, no_contrato FROM clientes WHERE id_cliente = ?");
    $stmt->execute([$id_cliente]);
    $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$cliente) {
        die("ERROR: Cliente no encontrado\n");
    }
    echo "✓ Cliente encontrado: {$cliente['no_contrato']}\n\n";
    
    // Validar completitud (VAL-PLD-005)
    echo "--- VAL-PLD-005: Validación de Completitud ---\n";
    $resultCompleto = validateExpedienteCompleto($pdo, $id_cliente);
    echo "Completo: " . ($resultCompleto['completo'] ? 'SÍ' : 'NO') . "\n";
    echo "Razón: {$resultCompleto['razon']}\n";
    if (!empty($resultCompleto['faltantes'])) {
        echo "Faltantes:\n";
        foreach ($resultCompleto['faltantes'] as $faltante) {
            echo "  - $faltante\n";
        }
    }
    echo "\n";
    
    // Validar actualización (VAL-PLD-006)
    echo "--- VAL-PLD-006: Validación de Actualización ---\n";
    $resultActualizacion = validateActualizacionExpediente($pdo, $id_cliente);
    echo "Actualizado: " . ($resultActualizacion['actualizado'] ? 'SÍ' : 'NO') . "\n";
    echo "Razón: {$resultActualizacion['razon']}\n";
    if (isset($resultActualizacion['dias_vencido'])) {
        echo "Días vencido: " . ($resultActualizacion['dias_vencido'] ?? 0) . "\n";
    }
    if (isset($resultActualizacion['fecha_ultima_actualizacion'])) {
        echo "Última actualización: {$resultActualizacion['fecha_ultima_actualizacion']}\n";
    }
    echo "\n";
    
    // Resultado general
    echo "--- Resultado General ---\n";
    $valido = $resultCompleto['completo'] && $resultActualizacion['actualizado'];
    echo "Expediente válido: " . ($valido ? 'SÍ ✓' : 'NO ✗') . "\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Archivo: " . $e->getFile() . "\n";
    echo "Línea: " . $e->getLine() . "\n";
    echo "\nTrace:\n" . $e->getTraceAsString() . "\n";
}

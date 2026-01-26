<?php
/**
 * Script de Revalidación Periódica PLD - VAL-PLD-002
 * 
 * Uso:
 * - Ejecutar manualmente: php tools/revalidate_patron_pld.php
 * - Ejecutar vía cron cada 3 meses: 0 0 1 */3 * php /ruta/al/proyecto/tools/revalidate_patron_pld.php
 * - Ejecutar vía cron diario para verificar: 0 9 * * * php /ruta/al/proyecto/tools/revalidate_patron_pld.php
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/pld_validation.php';
require_once __DIR__ . '/../config/pld_revalidation.php';
require_once __DIR__ . '/../config/logger.php';

$logger = Logger::getInstance();

echo "=== Revalidación Periódica PLD (VAL-PLD-002) ===\n";
echo "Fecha: " . date('Y-m-d H:i:s') . "\n\n";

try {
    // 1. Verificar si la revalidación está vencida
    $revalidationStatus = checkRevalidationDue($pdo);
    
    echo "Estado de Revalidación:\n";
    echo "  - " . $revalidationStatus['mensaje'] . "\n";
    echo "  - Días restantes/vencidos: " . $revalidationStatus['dias_restantes'] . "\n";
    echo "  - Requiere revalidación: " . ($revalidationStatus['requiere_revalidacion'] ? 'SÍ' : 'NO') . "\n\n";
    
    if (!$revalidationStatus['requiere_revalidacion']) {
        echo "La revalidación está vigente. No se requiere acción.\n";
        exit(0);
    }
    
    // 2. Obtener datos actuales del padrón
    // NOTA: En producción, esto debería consultar el Portal PLD del SAT o una API
    // Por ahora, se asume que los datos se ingresan manualmente o vía admin
    
    echo "NOTA: Este script requiere que los datos del padrón se actualicen manualmente\n";
    echo "      o vía la interfaz de administración.\n\n";
    
    echo "Para revalidar:\n";
    echo "1. Accede a admin/config.php\n";
    echo "2. Actualiza los datos del padrón PLD\n";
    echo "3. Haz clic en 'Revalidar Padrón'\n\n";
    
    // 3. Validar padrón actual
    $validationResult = validatePatronPLD($pdo);
    
    echo "Validación Actual del Padrón:\n";
    echo "  - Habilitado: " . ($validationResult['habilitado'] ? 'SÍ' : 'NO') . "\n";
    echo "  - Estatus: " . ($validationResult['estatus'] ?? 'N/A') . "\n";
    echo "  - Razón: " . ($validationResult['razon'] ?? 'N/A') . "\n\n";
    
    if (!$validationResult['habilitado']) {
        echo "⚠️  ADVERTENCIA: El sujeto obligado NO está habilitado para operar PLD.\n";
        echo "   Las operaciones PLD están bloqueadas.\n";
    }
    
    $logger->info('PLD Revalidation Script: Ejecutado', [
        'revalidation_status' => $revalidationStatus,
        'validation_result' => $validationResult
    ]);
    
    exit(0);
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    $logger->error('PLD Revalidation Script Error', ['error' => $e->getMessage()]);
    exit(1);
}

<?php
/**
 * Script para actualizar el ENUM de tipo_aviso en operaciones_pld
 * Agrega los valores 'sospechosa_24h' y 'listas_restringidas_24h'
 */

require_once __DIR__ . '/../config/db.php';

try {
    // Verificar si la columna existe
    $stmt = $pdo->query("SELECT COUNT(*) as count 
                         FROM INFORMATION_SCHEMA.COLUMNS 
                         WHERE TABLE_SCHEMA = DATABASE() 
                         AND TABLE_NAME = 'operaciones_pld'
                         AND COLUMN_NAME = 'tipo_aviso'");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result['count'] > 0) {
        // Actualizar el ENUM
        $sql = "ALTER TABLE `operaciones_pld` 
                MODIFY COLUMN `tipo_aviso` ENUM(
                    'umbral_individual', 
                    'acumulacion', 
                    'sospechosa', 
                    'sospechosa_24h',
                    'listas_restringidas',
                    'listas_restringidas_24h'
                ) DEFAULT NULL";
        
        $pdo->exec($sql);
        echo "✓ ENUM actualizado correctamente\n";
        
        // Verificar el cambio
        $stmt = $pdo->query("SELECT COLUMN_NAME, COLUMN_TYPE 
                             FROM INFORMATION_SCHEMA.COLUMNS 
                             WHERE TABLE_SCHEMA = DATABASE() 
                             AND TABLE_NAME = 'operaciones_pld'
                             AND COLUMN_NAME = 'tipo_aviso'");
        $column = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "Tipo actual: " . $column['COLUMN_TYPE'] . "\n";
    } else {
        echo "✗ La columna tipo_aviso no existe en operaciones_pld\n";
    }
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}

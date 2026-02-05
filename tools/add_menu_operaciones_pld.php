<?php
/**
 * Script para agregar "Operaciones PLD" al menú
 * Ejecutar desde línea de comandos o desde el navegador
 * 
 * Uso: php tools/add_menu_operaciones_pld.php
 */

require_once __DIR__ . '/../config/db.php';

try {
    // Verificar si ya existe
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM menu_access WHERE seccion = ? AND file_path = ?");
    $stmt->execute(['Operaciones PLD', 'operaciones_pld.php']);
    $exists = $stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0;
    
    if ($exists) {
        echo "✅ El elemento de menú 'Operaciones PLD' ya existe.\n";
        exit(0);
    }
    
    // Obtener todos los tipos de empresa que tienen menú
    $stmt = $pdo->query("SELECT DISTINCT id_tipo_empresa FROM menu_access");
    $tipos = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($tipos)) {
        echo "❌ No se encontraron tipos de empresa con menú.\n";
        exit(1);
    }
    
    // Insertar para cada tipo de empresa
    $stmt = $pdo->prepare("INSERT INTO menu_access (id_tipo_empresa, seccion, icon, file_path, id_parent) 
                           VALUES (?, 'Operaciones PLD', 'fa-file-invoice-dollar', 'operaciones_pld.php', 0)");
    
    $inserted = 0;
    foreach ($tipos as $tipo) {
        try {
            $stmt->execute([$tipo]);
            $inserted++;
            echo "✅ Agregado para tipo de empresa: $tipo\n";
        } catch (PDOException $e) {
            // Ignorar si ya existe (duplicate key)
            if ($e->getCode() != 23000) {
                echo "⚠️ Error al insertar para tipo $tipo: " . $e->getMessage() . "\n";
            }
        }
    }
    
    echo "\n✅ Proceso completado. Se agregaron $inserted elemento(s) de menú.\n";
    echo "🔄 Recarga el dashboard para ver el nuevo elemento 'Operaciones PLD' en el menú.\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}

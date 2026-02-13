<?php
/**
 * Verifica si el usuario actual puede modificar/eliminar operaciones y avisos PLD.
 */

session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/pld_permisos.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'puede_modificar' => false]);
    exit;
}

$id_cliente = isset($_GET['id_cliente']) ? (int)$_GET['id_cliente'] : null;
$puede = canModifyPLD($pdo, $_SESSION['user_id'], $id_cliente);

echo json_encode([
    'status' => 'success',
    'puede_modificar' => $puede,
    'id_usuario' => $_SESSION['user_id']
]);

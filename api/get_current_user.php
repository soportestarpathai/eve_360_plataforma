<?php
// api/get_current_user.php
// 1. Suppress HTML errors to ensure JSON integrity
ini_set('display_errors', 0);
error_reporting(E_ALL);

session_start();
header('Content-Type: application/json');

// 2. Check Session
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'No session']);
    exit;
}

try {
    // 3. Load DB inside Try/Catch
    if (!file_exists('../config/db.php')) {
        throw new Exception("DB config missing");
    }
    require_once '../config/db.php';

    $userId = $_SESSION['user_id'];

    // 4. Get User Permissions
    $sql = "
        SELECT 
            u.nombre,
            p.* FROM usuarios u
        LEFT JOIN usuarios_permisos p ON u.id_usuario = p.id_usuario
        WHERE u.id_usuario = ?
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$data) {
        throw new Exception("No user data found.");
    }

    $user = [
        'name' => $data['nombre'],
        'avatar' => 'https://ui-avatars.com/api/?name=' . urlencode($data['nombre']) . '&background=0D8ABC&color=fff'
    ];

    unset($data['id_permiso'], $data['id_usuario'], $data['nombre']);
    $permissions = $data;

    // 5. Get System Modules (Fail gracefully if table doesn't exist)
    $sysModules = [];
    try {
        $modStmt = $pdo->query("SELECT nombre_clave, activo FROM config_modulos");
        if ($modStmt) {
            $sysModules = $modStmt->fetchAll(PDO::FETCH_KEY_PAIR);
        }
    } catch (Exception $e) {
        $sysModules = []; 
    }

    // Apply logic: if module is disabled, force permission to 0
    if (isset($sysModules['reports']) && $sysModules['reports'] == 0) {
        $permissions['reportes'] = 0;
    }
    if (isset($sysModules['investments']) && $sysModules['investments'] == 0) {
        $permissions['rebalanceo'] = 0;
        $permissions['valuacion'] = 0;
    }
    
    echo json_encode([
        'status' => 'success',
        'user' => $user,
        'permissions' => $permissions,
        'sys_modules' => $sysModules
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
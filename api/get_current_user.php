<?php
// api/get_current_user.php
ini_set('display_errors', 0);
error_reporting(E_ALL);
ob_start();

session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    if (ob_get_level() > 0) ob_end_clean();
    echo json_encode(['status' => 'error', 'message' => 'No session']);
    exit;
}

if (!function_exists('buildAvatarDataUri')) {
    function buildAvatarDataUri(string $name): string {
        $name = trim($name);
        $initials = '';
        if ($name !== '') {
            $parts = preg_split('/\s+/', $name);
            foreach ($parts as $part) {
                if ($part === '') continue;
                $initials .= strtoupper(substr($part, 0, 1));
                if (strlen($initials) >= 2) break;
            }
        }
        if ($initials === '') $initials = 'U';

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="96" height="96" viewBox="0 0 96 96">'
             . '<defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1">'
             . '<stop offset="0%" stop-color="#0D8ABC"/><stop offset="100%" stop-color="#0B3C8A"/></linearGradient></defs>'
             . '<rect width="96" height="96" rx="48" fill="url(#g)"/>'
             . '<text x="50%" y="54%" dominant-baseline="middle" text-anchor="middle" '
             . 'font-family="Segoe UI, Arial, sans-serif" font-size="34" font-weight="700" fill="#ffffff">'
             . htmlspecialchars($initials, ENT_QUOTES, 'UTF-8')
             . '</text></svg>';

        return 'data:image/svg+xml;utf8,' . rawurlencode($svg);
    }
}

try {
    $userId = (int)$_SESSION['user_id'];
    $cacheKey = 'current_user_payload_v1';
    $cacheTtl = 120; // segundos

    if (!empty($_SESSION[$cacheKey]) && is_array($_SESSION[$cacheKey])) {
        $cached = $_SESSION[$cacheKey];
        $generatedAt = isset($cached['generated_at']) ? (int)$cached['generated_at'] : 0;
        $payload = $cached['payload'] ?? null;
        if ($generatedAt > 0 && is_array($payload) && (time() - $generatedAt) < $cacheTtl) {
            if (ob_get_level() > 0) ob_end_clean();
            echo json_encode($payload);
            exit;
        }
    }

    $dbPath = __DIR__ . '/../config/db.php';
    if (!file_exists($dbPath)) {
        throw new Exception('DB config missing');
    }
    require_once $dbPath;

    $sql = "
        SELECT u.nombre, p.*
        FROM usuarios u
        LEFT JOIN usuarios_permisos p ON u.id_usuario = p.id_usuario
        WHERE u.id_usuario = ?
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$data) {
        throw new Exception('No user data found');
    }

    $userName = trim((string)($data['nombre'] ?? 'Usuario'));
    if ($userName === '') $userName = 'Usuario';

    $user = [
        'name' => $userName,
        'avatar' => buildAvatarDataUri($userName)
    ];

    unset($data['id_permiso'], $data['id_usuario'], $data['nombre']);
    $permissions = $data;

    $sysModules = [];
    try {
        $modStmt = $pdo->prepare("
            SELECT m.nombre_clave, COALESCE(u.activo, m.activo) AS activo
            FROM config_modulos m
            LEFT JOIN config_modulos_usuario u
                ON u.id_modulo = m.id_modulo AND u.id_usuario = ?
        ");
        $modStmt->execute([$userId]);
        $rows = $modStmt ? $modStmt->fetchAll(PDO::FETCH_ASSOC) : [];
        foreach ($rows as $r) {
            $sysModules[$r['nombre_clave']] = (int)$r['activo'];
        }
        if (empty($sysModules)) {
            $fallback = $pdo->query("SELECT nombre_clave, activo FROM config_modulos");
            if ($fallback) $sysModules = $fallback->fetchAll(PDO::FETCH_KEY_PAIR);
        }
    } catch (Exception $e) {
        try {
            $fallback = $pdo->query("SELECT nombre_clave, activo FROM config_modulos");
            if ($fallback) $sysModules = $fallback->fetchAll(PDO::FETCH_KEY_PAIR);
        } catch (Exception $e2) {
            $sysModules = [];
        }
    }

    if (isset($sysModules['reports']) && (int)$sysModules['reports'] === 0) {
        $permissions['reportes'] = 0;
    }
    if (isset($sysModules['investments']) && (int)$sysModules['investments'] === 0) {
        $permissions['rebalanceo'] = 0;
        $permissions['valuacion'] = 0;
    }

    $payload = [
        'status' => 'success',
        'user' => $user,
        'permissions' => $permissions,
        'sys_modules' => $sysModules
    ];

    $_SESSION[$cacheKey] = [
        'generated_at' => time(),
        'payload' => $payload
    ];

    if (ob_get_level() > 0) ob_end_clean();
    echo json_encode($payload);
} catch (Exception $e) {
    http_response_code(500);
    if (ob_get_level() > 0) ob_end_clean();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>

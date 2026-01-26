<?php
session_start();
require_once 'config/db.php';

// Verificar que el usuario esté autenticado
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = $_SESSION['user_id'];
$error = '';
$success = '';

// Obtener información del usuario actual
try {
    $stmt = $pdo->prepare("
        SELECT u.*, 
               up.catalogo_instituciones, up.catalogo_emisoras, up.catalogo_clientes,
               up.captura, up.administracion, up.reportes, up.valuacion, 
               up.correcciones, up.rebalanceo
        FROM usuarios u
        LEFT JOIN usuarios_permisos up ON u.id_usuario = up.id_usuario
        WHERE u.id_usuario = ?
    ");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        header('Location: login.php');
        exit;
    }
} catch (Exception $e) {
    $error = 'Error al cargar información del usuario.';
    $user = null;
}

// Procesar formularios
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Cambiar contraseña
    if (isset($_POST['action']) && $_POST['action'] === 'change_password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $error = 'Todos los campos son requeridos.';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'Las contraseñas nuevas no coinciden.';
        } elseif (strlen($newPassword) < 8) {
            $error = 'La contraseña debe tener al menos 8 caracteres.';
        } else {
            // Verificar contraseña actual
            if (password_verify($currentPassword, $user['login_password'])) {
                try {
                    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE usuarios SET login_password = ? WHERE id_usuario = ?");
                    $stmt->execute([$newHash, $userId]);
                    
                    // Registrar en bitácora
                    try {
                        $logStmt = $pdo->prepare("
                            INSERT INTO bitacora (id_usuario, accion, tabla, id_registro, valor_anterior, valor_nuevo, fecha) 
                            VALUES (?, 'CAMBIAR_PASSWORD', 'usuarios', ?, '***', '***', NOW())
                        ");
                        $logStmt->execute([$userId, $userId]);
                    } catch (Exception $e) {
                        // Ignorar errores de bitácora
                    }
                    
                    $success = 'Contraseña actualizada correctamente.';
                } catch (Exception $e) {
                    $error = 'Error al actualizar la contraseña: ' . $e->getMessage();
                }
            } else {
                $error = 'La contraseña actual es incorrecta.';
            }
        }
    }
    
    // Actualizar información personal
    if (isset($_POST['action']) && $_POST['action'] === 'update_info') {
        $nombre = trim($_POST['nombre'] ?? '');
        
        if (empty($nombre)) {
            $error = 'El nombre es requerido.';
        } else {
            try {
                $oldData = ['nombre' => $user['nombre']];
                $stmt = $pdo->prepare("UPDATE usuarios SET nombre = ? WHERE id_usuario = ?");
                $stmt->execute([$nombre, $userId]);
                
                // Actualizar variable local
                $user['nombre'] = $nombre;
                
                // Registrar en bitácora
                try {
                    $logStmt = $pdo->prepare("
                        INSERT INTO bitacora (id_usuario, accion, tabla, id_registro, valor_anterior, valor_nuevo, fecha) 
                        VALUES (?, 'ACTUALIZAR', 'usuarios', ?, ?, ?, NOW())
                    ");
                    $logStmt->execute([$userId, $userId, json_encode($oldData), json_encode(['nombre' => $nombre])]);
                } catch (Exception $e) {
                    // Ignorar errores de bitácora
                }
                
                $success = 'Información actualizada correctamente.';
            } catch (Exception $e) {
                $error = 'Error al actualizar la información: ' . $e->getMessage();
            }
        }
    }
    
    // Recargar datos del usuario después de actualización
    if ($success) {
        $stmt = $pdo->prepare("
            SELECT u.*, 
                   up.catalogo_instituciones, up.catalogo_emisoras, up.catalogo_clientes,
                   up.captura, up.administracion, up.reportes, up.valuacion, 
                   up.correcciones, up.rebalanceo
            FROM usuarios u
            LEFT JOIN usuarios_permisos up ON u.id_usuario = up.id_usuario
            WHERE u.id_usuario = ?
        ");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

// Obtener actividad reciente del usuario
$recentActivity = [];
try {
    $stmt = $pdo->prepare("
        SELECT * FROM bitacora 
        WHERE id_usuario = ? 
        ORDER BY fecha DESC 
        LIMIT 10
    ");
    $stmt->execute([$userId]);
    $recentActivity = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $recentActivity = [];
}

include 'templates/header.php';
?>

<title>Mi Cuenta - <?= htmlspecialchars($appConfig['nombre_empresa']) ?></title>
<link rel="stylesheet" href="assets/css/mi_cuenta.css">

<body>
    <?php 
    $is_sub_page = true; // Activar botón de atrás
    include 'templates/top_bar.php'; 
    ?>
    
    <div class="container-fluid dashboard-wrapper">
        <div class="row">
            <div class="col-12">
                <div class="page-header mb-4">
                    <h2 class="page-title">
                        <i class="fa-solid fa-user-gear me-2"></i>Mi Cuenta
                    </h2>
                    <p class="page-subtitle">Administra tu información personal y configuración de cuenta</p>
                </div>
            </div>
        </div>
        
        <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fa-solid fa-circle-exclamation me-2"></i><?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fa-solid fa-check-circle me-2"></i><?= htmlspecialchars($success) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <div class="row g-4">
            <!-- Información Personal -->
            <div class="col-lg-6">
                <div class="account-card">
                    <div class="account-card-header">
                        <h5 class="account-card-title">
                            <i class="fa-solid fa-user me-2"></i>Información Personal
                        </h5>
                    </div>
                    <div class="account-card-body">
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="update_info">
                            
                            <div class="form-group mb-3">
                                <label for="nombre" class="form-label">
                                    <i class="fa-solid fa-signature me-2"></i>Nombre Completo
                                </label>
                                <input type="text" 
                                       class="form-control" 
                                       id="nombre" 
                                       name="nombre" 
                                       value="<?= htmlspecialchars($user['nombre'] ?? '') ?>" 
                                       required>
                            </div>
                            
                            <div class="form-group mb-3">
                                <label for="email" class="form-label">
                                    <i class="fa-solid fa-envelope me-2"></i>Correo Electrónico
                                </label>
                                <input type="email" 
                                       class="form-control" 
                                       id="email" 
                                       value="<?= htmlspecialchars($user['login_user'] ?? '') ?>" 
                                       disabled>
                                <small class="form-text text-muted">El correo electrónico no puede ser modificado desde aquí.</small>
                            </div>
                            
                            <div class="form-group mb-3">
                                <label class="form-label">
                                    <i class="fa-solid fa-circle-check me-2"></i>Estado de la Cuenta
                                </label>
                                <div>
                                    <?php if ($user['id_status_usuario'] == 1): ?>
                                        <span class="badge bg-success">
                                            <i class="fa-solid fa-check-circle me-1"></i>Activa
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark">
                                            <i class="fa-solid fa-clock me-1"></i>Inactiva / Pendiente
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fa-solid fa-save me-2"></i>Guardar Cambios
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Cambiar Contraseña -->
            <div class="col-lg-6">
                <div class="account-card">
                    <div class="account-card-header">
                        <h5 class="account-card-title">
                            <i class="fa-solid fa-lock me-2"></i>Cambiar Contraseña
                        </h5>
                    </div>
                    <div class="account-card-body">
                        <form method="POST" action="" id="passwordForm">
                            <input type="hidden" name="action" value="change_password">
                            
                            <div class="form-group mb-3">
                                <label for="current_password" class="form-label">
                                    <i class="fa-solid fa-key me-2"></i>Contraseña Actual
                                </label>
                                <input type="password" 
                                       class="form-control" 
                                       id="current_password" 
                                       name="current_password" 
                                       required 
                                       autocomplete="current-password">
                            </div>
                            
                            <div class="form-group mb-3">
                                <label for="new_password" class="form-label">
                                    <i class="fa-solid fa-lock me-2"></i>Nueva Contraseña
                                </label>
                                <input type="password" 
                                       class="form-control" 
                                       id="new_password" 
                                       name="new_password" 
                                       required 
                                       minlength="8"
                                       autocomplete="new-password">
                                <small class="form-text text-muted">Mínimo 8 caracteres</small>
                            </div>
                            
                            <div class="form-group mb-3">
                                <label for="confirm_password" class="form-label">
                                    <i class="fa-solid fa-lock me-2"></i>Confirmar Nueva Contraseña
                                </label>
                                <input type="password" 
                                       class="form-control" 
                                       id="confirm_password" 
                                       name="confirm_password" 
                                       required 
                                       minlength="8"
                                       autocomplete="new-password">
                            </div>
                            
                            <button type="submit" class="btn btn-warning w-100">
                                <i class="fa-solid fa-key me-2"></i>Cambiar Contraseña
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Permisos -->
            <div class="col-lg-6">
                <div class="account-card">
                    <div class="account-card-header">
                        <h5 class="account-card-title">
                            <i class="fa-solid fa-shield-halved me-2"></i>Permisos y Roles
                        </h5>
                    </div>
                    <div class="account-card-body">
                        <div class="permissions-list">
                            <?php
                            $permissions = [
                                'catalogo_instituciones' => ['label' => 'Catálogo de Instituciones', 'icon' => 'fa-building'],
                                'catalogo_emisoras' => ['label' => 'Catálogo de Emisoras', 'icon' => 'fa-chart-line'],
                                'catalogo_clientes' => ['label' => 'Catálogo de Clientes', 'icon' => 'fa-users'],
                                'captura' => ['label' => 'Captura de Datos', 'icon' => 'fa-keyboard'],
                                'administracion' => ['label' => 'Administración', 'icon' => 'fa-user-shield'],
                                'reportes' => ['label' => 'Reportes', 'icon' => 'fa-chart-pie'],
                                'valuacion' => ['label' => 'Valuación', 'icon' => 'fa-calculator'],
                                'correcciones' => ['label' => 'Correcciones', 'icon' => 'fa-edit'],
                                'rebalanceo' => ['label' => 'Rebalanceo', 'icon' => 'fa-balance-scale']
                            ];
                            
                            $hasPermissions = false;
                            foreach ($permissions as $key => $perm) {
                                if (!empty($user[$key]) && $user[$key] == 1) {
                                    $hasPermissions = true;
                                    echo '<div class="permission-item">';
                                    echo '<i class="fa-solid ' . $perm['icon'] . ' me-2"></i>';
                                    echo '<span>' . $perm['label'] . '</span>';
                                    echo '<i class="fa-solid fa-check-circle ms-auto text-success"></i>';
                                    echo '</div>';
                                }
                            }
                            
                            if (!$hasPermissions) {
                                echo '<div class="text-center text-muted py-3">';
                                echo '<i class="fa-solid fa-info-circle me-2"></i>No tienes permisos asignados.';
                                echo '</div>';
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Actividad Reciente -->
            <div class="col-lg-6">
                <div class="account-card">
                    <div class="account-card-header">
                        <h5 class="account-card-title">
                            <i class="fa-solid fa-clock-rotate-left me-2"></i>Actividad Reciente
                        </h5>
                    </div>
                    <div class="account-card-body">
                        <div class="activity-list">
                            <?php if (empty($recentActivity)): ?>
                                <div class="text-center text-muted py-3">
                                    <i class="fa-solid fa-inbox fa-2x mb-2"></i>
                                    <p class="mb-0">No hay actividad reciente</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($recentActivity as $activity): ?>
                                    <div class="activity-item">
                                        <div class="activity-icon">
                                            <i class="fa-solid fa-circle-exclamation"></i>
                                        </div>
                                        <div class="activity-content">
                                            <div class="activity-action">
                                                <strong><?= htmlspecialchars($activity['accion'] ?? 'Acción') ?></strong>
                                                <span class="badge bg-secondary ms-2">
                                                    <?= htmlspecialchars($activity['tabla'] ?? $activity['tabla_afectada'] ?? '-') ?>
                                                </span>
                                            </div>
                                            <div class="activity-date">
                                                <i class="fa-solid fa-calendar me-1"></i>
                                                <?= date('d/m/Y H:i', strtotime($activity['fecha'])) ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

<script>
    // Validar que las contraseñas coincidan
    document.getElementById('passwordForm')?.addEventListener('submit', function(e) {
        const newPassword = document.getElementById('new_password').value;
        const confirmPassword = document.getElementById('confirm_password').value;
        
        if (newPassword !== confirmPassword) {
            e.preventDefault();
            alert('Las contraseñas nuevas no coinciden.');
            return false;
        }
        
        if (newPassword.length < 8) {
            e.preventDefault();
            alert('La contraseña debe tener al menos 8 caracteres.');
            return false;
        }
    });
</script>

<?php include 'templates/footer.php'; ?>

<?php
session_start();
require_once 'config/db.php';
require_once 'config/pld_representacion_legal.php';

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
    
    // Guardar Representación Legal (VAL-PLD-004)
    if (isset($_POST['action']) && $_POST['action'] === 'save_representacion') {
        try {
            $tipo_representacion = $_POST['tipo_representacion'] ?? null;
            $fecha_vencimiento = !empty($_POST['fecha_vencimiento']) ? $_POST['fecha_vencimiento'] : null;
            
            if (!$tipo_representacion) {
                $error = 'Tipo de representación es requerido';
            } else {
                // Handle file upload
                $documento_facultades = null;
                if (isset($_FILES['documento_facultades']) && $_FILES['documento_facultades']['error'] === UPLOAD_ERR_OK) {
                    $uploadDir = 'uploads/representacion_legal/';
                    if (!file_exists($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }
                    
                    $extension = pathinfo($_FILES['documento_facultades']['name'], PATHINFO_EXTENSION);
                    $cleanName = 'rep_' . $userId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
                    $targetPath = $uploadDir . $cleanName;
                    
                    if (move_uploaded_file($_FILES['documento_facultades']['tmp_name'], $targetPath)) {
                        $documento_facultades = '../uploads/representacion_legal/' . $cleanName;
                    } else {
                        throw new Exception("Error al subir el archivo");
                    }
                } else {
                    // Si no hay archivo nuevo, mantener el existente
                    $id_representacion = $_POST['id_representacion'] ?? null;
                    if ($id_representacion) {
                        $stmt = $pdo->prepare("SELECT documento_facultades FROM usuarios_representacion_legal WHERE id_representacion = ? AND id_usuario = ?");
                        $stmt->execute([$id_representacion, $userId]);
                        $existente = $stmt->fetch(PDO::FETCH_ASSOC);
                        if ($existente && !empty($existente['documento_facultades'])) {
                            $documento_facultades = $existente['documento_facultades'];
                        }
                    }
                }
                
                if (!$documento_facultades) {
                    $error = 'Documento de facultades es requerido';
                } else {
                    $data = [
                        'id_usuario' => $userId,
                        'id_cliente' => null, // General para el usuario
                        'tipo_representacion' => $tipo_representacion,
                        'documento_facultades' => $documento_facultades,
                        'fecha_vencimiento' => $fecha_vencimiento
                    ];
                    
                    if (!empty($_POST['id_representacion'])) {
                        $data['id_representacion'] = $_POST['id_representacion'];
                    }
                    
                    $result = registrarRepresentacionLegal($pdo, $data);
                    
                    if ($result['success']) {
                        $success = 'Representación legal registrada correctamente';
                    } else {
                        $error = $result['message'];
                    }
                }
            }
        } catch (Exception $e) {
            $error = 'Error al guardar representación legal: ' . $e->getMessage();
        }
    }
}

// Obtener representaciones legales del usuario
$representaciones = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM usuarios_representacion_legal WHERE id_usuario = ? AND id_status = 1 ORDER BY fecha_alta DESC");
    $stmt->execute([$userId]);
    $representaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $representaciones = [];
}

// Validar representación legal
$validacionRepresentacion = validateRepresentacionLegal($pdo, $userId);

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
            
            <!-- Representación Legal (VAL-PLD-004) -->
            <div class="col-lg-6">
                <div class="account-card">
                    <div class="account-card-header">
                        <h5 class="account-card-title">
                            <i class="fa-solid fa-gavel me-2"></i>Representación Legal
                            <small class="text-muted ms-2">(VAL-PLD-004)</small>
                        </h5>
                    </div>
                    <div class="account-card-body">
                        <?php if ($validacionRepresentacion['valido'] && !$validacionRepresentacion['bloqueado']): ?>
                            <div class="alert alert-success mb-3">
                                <i class="fa-solid fa-check-circle me-2"></i>
                                <strong>Representación Legal Válida</strong>
                                <p class="mb-0 small">Tienes representación legal documentada y vigente</p>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-danger mb-3">
                                <i class="fa-solid fa-exclamation-triangle me-2"></i>
                                <strong>Representación Legal Requerida</strong>
                                <p class="mb-0 small"><?= htmlspecialchars($validacionRepresentacion['razon'] ?? 'Falta representación legal documentada') ?></p>
                                <p class="mb-0 small text-danger"><strong>Esto puede bloquear operaciones PLD</strong></p>
                            </div>
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <button type="button" class="btn btn-primary btn-sm w-100" data-bs-toggle="modal" data-bs-target="#representacionModal">
                                <i class="fa-solid fa-plus me-2"></i>Agregar Representación Legal
                            </button>
                        </div>
                        
                        <?php if (!empty($representaciones)): ?>
                            <div class="list-group">
                                <?php foreach ($representaciones as $rep): 
                                    $tieneDoc = !empty($rep['documento_facultades']) && file_exists($rep['documento_facultades']);
                                    $vencido = !empty($rep['fecha_vencimiento']) && strtotime($rep['fecha_vencimiento']) < time();
                                ?>
                                    <div class="list-group-item">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h6 class="mb-1">
                                                    <?= ucfirst(str_replace('_', ' ', $rep['tipo_representacion'])) ?>
                                                    <?php if ($vencido): ?>
                                                        <span class="badge bg-danger ms-2">Vencido</span>
                                                    <?php elseif ($tieneDoc): ?>
                                                        <span class="badge bg-success ms-2">Con documento</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning ms-2">Sin documento</span>
                                                    <?php endif; ?>
                                                </h6>
                                                <small class="text-muted">
                                                    Fecha alta: <?= $rep['fecha_alta'] ? date('d/m/Y', strtotime($rep['fecha_alta'])) : 'N/A' ?>
                                                    <?php if ($rep['fecha_vencimiento']): ?>
                                                        <br>Vencimiento: <?= date('d/m/Y', strtotime($rep['fecha_vencimiento'])) ?>
                                                    <?php endif; ?>
                                                </small>
                                            </div>
                                            <?php if ($tieneDoc): ?>
                                                <a href="<?= htmlspecialchars($rep['documento_facultades']) ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                                    <i class="fa-solid fa-download"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center text-muted py-3">
                                <i class="fa-solid fa-inbox fa-2x mb-2"></i>
                                <p class="mb-0">No hay representaciones registradas</p>
                            </div>
                        <?php endif; ?>
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

<!-- Modal para Representación Legal -->
<div class="modal fade" id="representacionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fa-solid fa-gavel me-2"></i>Representación Legal</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="action" value="save_representacion">
                    <input type="hidden" name="id_representacion" id="id_representacion" value="">
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Tipo de Representación <span class="text-danger">*</span></label>
                        <select class="form-select" name="tipo_representacion" id="tipo_representacion" required>
                            <option value="">Seleccione...</option>
                            <option value="representante_legal">Representante Legal</option>
                            <option value="apoderado">Apoderado</option>
                            <option value="usuario_autorizado">Usuario Autorizado</option>
                        </select>
                        <small class="text-muted">Seleccione el tipo de representación que tiene para actuar en nombre de la entidad</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Documento de Facultades <span class="text-danger">*</span></label>
                        <input type="file" class="form-control" name="documento_facultades" id="documento_facultades" accept=".pdf,.jpg,.jpeg,.png">
                        <small class="text-muted">Formatos permitidos: PDF, JPG, PNG. Debe ser el documento que acredita sus facultades</small>
                        <div id="documento_actual" class="mt-2"></div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Fecha de Vencimiento (Opcional)</label>
                        <input type="date" class="form-control" name="fecha_vencimiento" id="fecha_vencimiento">
                        <small class="text-muted">Si el documento tiene fecha de vencimiento</small>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fa-solid fa-info-circle me-2"></i>
                        <strong>Importante:</strong> La representación legal es requerida para realizar operaciones PLD. Sin ella, algunas operaciones pueden estar bloqueadas.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                </div>
            </form>
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

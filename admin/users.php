<?php 
// admin/users.php
include 'header.php'; 

// Helper for Email Sending
function sendVerificationEmail($email, $name, $token) {
    // Ideally, change this to your domain
    $domain = $_SERVER['HTTP_HOST']; 
    $link = "http://$domain/verify.php?token=$token";
    
    $subject = "Verifique su cuenta - $domain";
    $message = "Hola $name,\n\nSe ha creado o modificado su cuenta. Por favor haga clic en el siguiente enlace para activarla:\n\n$link\n\nSi usted no solicitó esto, ignore este mensaje.";
    $headers = "From: no-reply@$domain" . "\r\n" .
               "Reply-To: no-reply@$domain" . "\r\n" .
               "X-Mailer: PHP/" . phpversion();

    return mail($email, $subject, $message, $headers);
}

// Helper for Logging
function logUserAction($pdo, $action, $id, $oldVal, $newVal) {
    // Use session ID if available, else 0 (Admin/System)
    $userId = $_SESSION['user_id'] ?? 0; // Ensure your login sets this, or default to 0
    
    $stmt = $pdo->prepare("INSERT INTO bitacora (id_usuario, accion, tabla, id_registro, valor_anterior, valor_nuevo, fecha) VALUES (?, ?, 'usuarios', ?, ?, ?, NOW())");
    $stmt->execute([
        $userId, 
        $action, 
        $id, 
        json_encode($oldVal), 
        json_encode($newVal)
    ]);
}
?>
<title>Administración de Usuarios</title>

<?php
// --- ACTION HANDLER ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. SAVE USER
    if (isset($_POST['action']) && $_POST['action'] === 'save_user') {
        try {
            $pdo->beginTransaction();

            $id_usuario = $_POST['id_usuario'] ?? '';
            $nombre = $_POST['nombre'];
            $email = trim($_POST['login_user']);
            $id_grupo = $_POST['id_grupo'] ?? 1;
            
            // Validate Email
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception("El campo 'Usuario (Login)' debe ser un correo electrónico válido.");
            }

            // Flags
            $sendEmail = false;
            $newToken = null;
            $statusToSave = $_POST['id_status_usuario']; // Default from form

            // A. UPDATE EXISTING USER
            if ($id_usuario) {
                // Fetch Old Data for Log & Comparison
                $stmtOld = $pdo->prepare("SELECT * FROM usuarios WHERE id_usuario = ?");
                $stmtOld->execute([$id_usuario]);
                $oldData = $stmtOld->fetch(PDO::FETCH_ASSOC);

                // Check if email changed
                if ($oldData['login_user'] !== $email) {
                    $statusToSave = 0; // Force Inactive
                    $newToken = bin2hex(random_bytes(32));
                    $sendEmail = true;
                }

                $sql = "UPDATE usuarios SET nombre = ?, login_user = ?, id_status_usuario = ?, id_grupo = ?, verification_token = ? WHERE id_usuario = ?";
                $params = [$nombre, $email, $statusToSave, $id_grupo, ($newToken ? $newToken : $oldData['verification_token']), $id_usuario];
                
                // Password Update
                if (!empty($_POST['login_password'])) {
                    $hash = password_hash($_POST['login_password'], PASSWORD_DEFAULT);
                    $sql = "UPDATE usuarios SET nombre = ?, login_user = ?, id_status_usuario = ?, id_grupo = ?, verification_token = ?, login_password = ? WHERE id_usuario = ?";
                    $params = [$nombre, $email, $statusToSave, $id_grupo, ($newToken ? $newToken : $oldData['verification_token']), $hash, $id_usuario];
                }
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);

                // Prepare New Data for Log
                $newData = ['nombre' => $nombre, 'login_user' => $email, 'id_status_usuario' => $statusToSave, 'id_grupo' => $id_grupo];
                logUserAction($pdo, 'ACTUALIZAR', $id_usuario, $oldData, $newData);

            } 
            // B. CREATE NEW USER
            else {
                // Check if email exists
                $stmtCheck = $pdo->prepare("SELECT count(*) FROM usuarios WHERE login_user = ?");
                $stmtCheck->execute([$email]);
                if ($stmtCheck->fetchColumn() > 0) {
                    throw new Exception("El correo electrónico ya está registrado.");
                }

                $statusToSave = 0; // Force Inactive on Create
                $newToken = bin2hex(random_bytes(32));
                $sendEmail = true;

                $hash = password_hash($_POST['login_password'], PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO usuarios (nombre, login_user, login_password, id_status_usuario, id_grupo, verification_token) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$nombre, $email, $hash, $statusToSave, $id_grupo, $newToken]);
                $id_usuario = $pdo->lastInsertId();

                $newData = ['nombre' => $nombre, 'login_user' => $email, 'id_status_usuario' => $statusToSave, 'verification_token' => 'GENERATED'];
                logUserAction($pdo, 'CREAR', $id_usuario, null, $newData);
            }

            // C. Handle Permissions (Standard logic)
            // ... [Keep existing permission logic same as before, omitted for brevity but part of the file] ...
            $permCols = ['catalogo_instituciones', 'catalogo_emisoras', 'catalogo_clientes', 'captura', 'administracion', 'reportes', 'valuacion', 'correcciones', 'rebalanceo'];
            $permValues = [];
            foreach ($permCols as $col) { $permValues[$col] = isset($_POST['perm_' . $col]) ? 1 : 0; }
            
            // (Insert/Update Permissions DB Logic here - assumed standard)
            // For full code context, ensure the permission block from previous version is preserved here.
            $stmtCheckPerm = $pdo->prepare("SELECT id_permiso FROM usuarios_permisos WHERE id_usuario = ?");
            $stmtCheckPerm->execute([$id_usuario]);
            if ($stmtCheckPerm->fetchColumn()) {
                $setParts = []; $execParams = [];
                foreach ($permValues as $col => $val) { $setParts[] = "$col = ?"; $execParams[] = $val; }
                $execParams[] = $id_usuario;
                $pdo->prepare("UPDATE usuarios_permisos SET " . implode(', ', $setParts) . " WHERE id_usuario = ?")->execute($execParams);
            } else {
                $cols = "id_usuario, " . implode(', ', array_keys($permValues));
                $placeholders = "?, " . str_repeat('?, ', count($permValues) - 1) . "?";
                $execParams = array_merge([$id_usuario], array_values($permValues));
                $pdo->prepare("INSERT INTO usuarios_permisos ($cols) VALUES ($placeholders)")->execute($execParams);
            }

            // D. SEND EMAIL IF REQUIRED
            if ($sendEmail && $newToken) {
                if(sendVerificationEmail($email, $nombre, $newToken)) {
                    logUserAction($pdo, 'EMAIL_ENVIADO', $id_usuario, null, ['status' => 'sent', 'to' => $email]);
                    echo '<div class="alert alert-success mt-3"><i class="fa-solid fa-envelope me-2"></i>Usuario guardado. Se ha enviado un correo de verificación.</div>';
                } else {
                    logUserAction($pdo, 'EMAIL_ERROR', $id_usuario, null, ['status' => 'failed', 'to' => $email]);
                    echo '<div class="alert alert-warning mt-3">Usuario guardado, pero falló el envío del correo. Verifique la configuración del servidor.</div>';
                }
            } else {
                echo '<div class="alert alert-success mt-3"><i class="fa-solid fa-check me-2"></i>Usuario actualizado correctamente.</div>';
            }

            $pdo->commit();

        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            echo '<div class="alert alert-danger mt-3">Error: ' . $e->getMessage() . '</div>';
        }
    }

    // 2. DELETE USER
    if (isset($_POST['action']) && $_POST['action'] === 'delete_user') {
        try {
            $idDel = $_POST['id_user_delete'];
            // Fetch for log
            $stmtOld = $pdo->prepare("SELECT * FROM usuarios WHERE id_usuario = ?");
            $stmtOld->execute([$idDel]);
            $oldData = $stmtOld->fetch(PDO::FETCH_ASSOC);

            $pdo->beginTransaction();
            $pdo->prepare("DELETE FROM usuarios_permisos WHERE id_usuario = ?")->execute([$idDel]);
            $pdo->prepare("DELETE FROM usuarios WHERE id_usuario = ?")->execute([$idDel]);
            
            logUserAction($pdo, 'ELIMINAR', $idDel, $oldData, null);
            
            $pdo->commit();
            echo '<div class="alert alert-warning mt-3">Usuario eliminado.</div>';
        } catch (Exception $e) {
            $pdo->rollBack();
            echo '<div class="alert alert-danger">Error: ' . $e->getMessage() . '</div>';
        }
    }
}

// --- FETCH DATA ---
$stmt = $pdo->query("
    SELECT u.*, 
           up.catalogo_instituciones, up.catalogo_emisoras, up.catalogo_clientes,
           up.captura, up.administracion, up.reportes, up.valuacion, up.correcciones, up.rebalanceo
    FROM usuarios u
    LEFT JOIN usuarios_permisos up ON u.id_usuario = up.id_usuario
    ORDER BY u.nombre ASC
");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container mt-4 mb-5">
    <div class="card shadow-sm">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fa-solid fa-users-gear me-2"></i>Gestión de Usuarios</h5>
            <button class="btn btn-light btn-sm text-primary fw-bold" onclick="openUserModal()">
                <i class="fa-solid fa-plus me-1"></i>Nuevo Usuario
            </button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Nombre</th>
                            <th>Email (Login)</th>
                            <th>Status</th>
                            <th class="text-center">Permisos</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                            <tr>
                                <td class="fw-bold"><?= htmlspecialchars($u['nombre']) ?></td>
                                <td><?= htmlspecialchars($u['login_user']) ?></td>
                                <td>
                                    <?php if ($u['id_status_usuario'] == 1): ?>
                                        <span class="badge bg-success">Activo</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark">Inactivo / Pendiente</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php
                                        $countP = 0;
                                        $permsToCheck = ['catalogo_instituciones', 'catalogo_emisoras', 'catalogo_clientes', 'captura', 'administracion', 'reportes', 'valuacion', 'correcciones', 'rebalanceo'];
                                        foreach($permsToCheck as $p) { if(!empty($u[$p]) && $u[$p] == 1) $countP++; }
                                        echo $countP > 0 ? '<span class="badge bg-info text-dark">' . $countP . ' Roles</span>' : '<span class="text-muted small">-</span>';
                                    ?>
                                </td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-primary border-0" 
                                            onclick='editUser(<?= htmlspecialchars(json_encode($u), ENT_QUOTES) ?>)' title="Editar">
                                        <i class="fa-solid fa-pen"></i>
                                    </button>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar este usuario permanentemente?');">
                                        <input type="hidden" name="action" value="delete_user">
                                        <input type="hidden" name="id_user_delete" value="<?= $u['id_usuario'] ?>">
                                        <button class="btn btn-sm btn-outline-danger border-0" title="Eliminar">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<div class="modal fade" id="userModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h5 class="modal-title" id="modalTitle">Nuevo Usuario</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="save_user">
                    <input type="hidden" name="id_usuario" id="userId">

                    <div class="alert alert-info small">
                        <i class="fa-solid fa-circle-info me-2"></i>
                        Al crear un usuario o cambiar su email, la cuenta quedará <strong>Inactiva</strong> hasta que se verifique por correo.
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Nombre Completo</label>
                            <input type="text" name="nombre" id="userName" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Email (Login)</label>
                            <input type="email" name="login_user" id="userLogin" class="form-control" required>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Contraseña</label>
                            <input type="password" name="login_password" id="userPass" class="form-control" placeholder="Min. 6 caracteres">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Estatus</label>
                            <select name="id_status_usuario" id="userStatus" class="form-select">
                                <option value="1">Activo</option>
                                <option value="0">Inactivo</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Grupo</label>
                            <input type="number" name="id_grupo" id="userGroup" class="form-control" value="1">
                        </div>
                    </div>

                    <hr>
                    <h6 class="text-primary mb-3">Permisos</h6>
                    
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="card h-100 bg-light border-0"><div class="card-body py-2">
                                <small class="text-uppercase fw-bold text-muted">Catálogos</small>
                                <div class="form-check"><input class="form-check-input" type="checkbox" name="perm_catalogo_instituciones" id="perm_inst"><label class="form-check-label" for="perm_inst">Instituciones</label></div>
                                <div class="form-check"><input class="form-check-input" type="checkbox" name="perm_catalogo_emisoras" id="perm_emi"><label class="form-check-label" for="perm_emi">Emisoras</label></div>
                                <div class="form-check"><input class="form-check-input" type="checkbox" name="perm_catalogo_clientes" id="perm_cli"><label class="form-check-label" for="perm_cli">Clientes</label></div>
                            </div></div>
                        </div>
                        <div class="col-md-4">
                            <div class="card h-100 bg-light border-0"><div class="card-body py-2">
                                <small class="text-uppercase fw-bold text-muted">Transacción</small>
                                <div class="form-check"><input class="form-check-input" type="checkbox" name="perm_captura" id="perm_cap"><label class="form-check-label" for="perm_cap">Captura</label></div>
                                <div class="form-check"><input class="form-check-input" type="checkbox" name="perm_valuacion" id="perm_val"><label class="form-check-label" for="perm_val">Valuación</label></div>
                                <div class="form-check"><input class="form-check-input" type="checkbox" name="perm_rebalanceo" id="perm_reb"><label class="form-check-label" for="perm_reb">Rebalanceo</label></div>
                            </div></div>
                        </div>
                        <div class="col-md-4">
                            <div class="card h-100 bg-light border-0"><div class="card-body py-2">
                                <small class="text-uppercase fw-bold text-muted">Control</small>
                                <div class="form-check"><input class="form-check-input" type="checkbox" name="perm_administracion" id="perm_admin"><label class="form-check-label text-danger" for="perm_admin">Admin</label></div>
                                <div class="form-check"><input class="form-check-input" type="checkbox" name="perm_correcciones" id="perm_corr"><label class="form-check-label" for="perm_corr">Correcciones</label></div>
                                <div class="form-check"><input class="form-check-input" type="checkbox" name="perm_reportes" id="perm_rep"><label class="form-check-label" for="perm_rep">Reportes</label></div>
                            </div></div>
                        </div>
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
    const userModal = new bootstrap.Modal(document.getElementById('userModal'));

    function openUserModal() {
        document.getElementById('modalTitle').textContent = 'Nuevo Usuario';
        document.getElementById('userId').value = '';
        document.getElementById('userName').value = '';
        document.getElementById('userLogin').value = '';
        document.getElementById('userPass').value = ''; 
        document.getElementById('userPass').required = true; 
        document.getElementById('userStatus').value = '1';
        document.getElementById('userStatus').disabled = true; // Force status on create (visual)
        
        document.querySelectorAll('.form-check-input').forEach(el => el.checked = false);
        userModal.show();
    }

    function editUser(u) {
        document.getElementById('modalTitle').textContent = 'Editar Usuario';
        document.getElementById('userId').value = u.id_usuario;
        document.getElementById('userName').value = u.nombre;
        document.getElementById('userLogin').value = u.login_user;
        document.getElementById('userPass').value = ''; 
        document.getElementById('userPass').required = false; 
        document.getElementById('userStatus').value = u.id_status_usuario;
        document.getElementById('userStatus').disabled = false; // Allow manual activation on edit
        document.getElementById('userGroup').value = u.id_grupo;

        const setPerm = (id, val) => { 
            const el = document.getElementById(id);
            if(el) el.checked = (val == 1); 
        };
        
        setPerm('perm_inst', u.catalogo_instituciones);
        setPerm('perm_emi', u.catalogo_emisoras);
        setPerm('perm_cli', u.catalogo_clientes);
        setPerm('perm_cap', u.captura);
        setPerm('perm_val', u.valuacion);
        setPerm('perm_reb', u.rebalanceo);
        setPerm('perm_admin', u.administracion);
        setPerm('perm_corr', u.correcciones);
        setPerm('perm_rep', u.reportes);

        userModal.show();
    }
</script>

<?php include '../templates/footer.php'; ?>

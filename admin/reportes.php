<?php include 'header.php'; ?>
<title>Control de Reportes por Usuario</title>

<?php
$id_usuario_seleccionado = (int)($_GET['id_usuario'] ?? 0);

// Asegurar que los 4 reportes controlables existan en el catálogo
$reportesDef = [
    ['Conservación y Verificación PLD', 'conservacion_pld.php', 'Reporte de conservación PLD (VAL-PLD-013, VAL-PLD-014)'],
    ['Reporte de riesgos', 'reporte_riesgos.php', 'Reporte de niveles de riesgo por cliente'],
    ['Reporte de transacciones', 'reporte_transacciones.php', 'Reporte de transacciones PLD'],
    ['Bitácora de actividad de usuarios (SAT)', 'bitacora_actividad.php', 'Registro de actividad de usuarios'],
];
foreach ($reportesDef as $r) {
    $chk = $pdo->prepare("SELECT 1 FROM cat_tipos_reporte WHERE codigo = ?");
    $chk->execute([$r[1]]);
    if (!$chk->fetch()) {
        $pdo->prepare("INSERT INTO cat_tipos_reporte (nombre, codigo, descripcion) VALUES (?, ?, ?)")->execute($r);
    }
}

// Lista de usuarios para selector
$stmtUsuarios = $pdo->prepare("
    SELECT id_usuario, nombre, login_user
    FROM usuarios
    WHERE id_status_usuario = 1
    ORDER BY nombre ASC
");
$stmtUsuarios->execute();
$listaUsuarios = $stmtUsuarios->fetchAll(PDO::FETCH_ASSOC);

// Handle toggle visibilidad por usuario
if (isset($_POST['toggle_reporte_usuario']) && $id_usuario_seleccionado > 0) {
    $id_tipo = (int)$_POST['id_tipo_reporte'];
    $activo = (int)$_POST['new_state'];
    try {
        $pdo->prepare("
            INSERT INTO reportes_usuario (id_usuario, id_tipo_reporte, activo)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE activo = VALUES(activo)
        ")->execute([$id_usuario_seleccionado, $id_tipo, $activo]);
    } catch (Exception $e) { /* tabla no existe */ }
    header("Location: reportes.php?id_usuario=" . $id_usuario_seleccionado);
    exit;
}

// Handle Actions
$message = "";
$msgType = "success";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action']) && $_POST['action'] === 'add') {
            $stmt = $pdo->prepare("INSERT INTO cat_tipos_reporte (nombre, codigo, descripcion) VALUES (?, ?, ?)");
            $stmt->execute([$_POST['nombre'], $_POST['codigo'], $_POST['descripcion']]);
            $message = "Reporte agregado correctamente.";
        } elseif (isset($_POST['action']) && $_POST['action'] === 'edit') {
            $stmt = $pdo->prepare("UPDATE cat_tipos_reporte SET nombre = ?, codigo = ?, descripcion = ? WHERE id_tipo_reporte = ?");
            $stmt->execute([$_POST['nombre'], $_POST['codigo'], $_POST['descripcion'], $_POST['id']]);
            $message = "Reporte actualizado correctamente.";
        } elseif (isset($_POST['action']) && $_POST['action'] === 'delete') {
            $stmt = $pdo->prepare("DELETE FROM cat_tipos_reporte WHERE id_tipo_reporte = ?");
            $stmt->execute([$_POST['id']]);
            $message = "Reporte eliminado correctamente.";
        }
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        $msgType = "danger";
    }
}

// Fetch Reports
$reports = $pdo->query("SELECT * FROM cat_tipos_reporte ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);

// Per-user visibility when user selected
$reporteVisible = [];
if ($id_usuario_seleccionado > 0) {
    try {
        $stmtR = $pdo->prepare("SELECT id_tipo_reporte, activo FROM reportes_usuario WHERE id_usuario = ?");
        $stmtR->execute([$id_usuario_seleccionado]);
        while ($row = $stmtR->fetch(PDO::FETCH_ASSOC)) {
            $reporteVisible[(int)$row['id_tipo_reporte']] = (int)$row['activo'];
        }
    } catch (Exception $e) { /* tabla no existe */ }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Catálogo de Tipos de Reporte</h2>
    <button class="btn btn-primary" onclick="openModal('add')">
        <i class="fa-solid fa-plus me-2"></i>Nuevo Reporte
    </button>
</div>

<div class="card shadow-sm mb-4 border-primary">
    <div class="card-body py-3">
        <h6 class="card-title text-primary mb-2"><i class="fa-solid fa-user-check me-2"></i>Control de reportes por usuario</h6>
        <p class="text-muted small mb-3">Seleccione un usuario y active o desactive los reportes que podrá ver en el menú.</p>
        <form method="GET" action="reportes.php" class="row g-2 align-items-center">
            <div class="col-auto">
                <label class="col-form-label fw-bold">Usuario:</label>
            </div>
            <div class="col-md-5">
                <select name="id_usuario" class="form-select" onchange="this.form.submit()" required>
                    <option value="">— Seleccione un usuario —</option>
                    <?php foreach ($listaUsuarios as $u): ?>
                        <option value="<?= (int)$u['id_usuario'] ?>" <?= $id_usuario_seleccionado === (int)$u['id_usuario'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($u['nombre'] ?? 'Usuario') ?> (<?= htmlspecialchars($u['login_user'] ?? '') ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($id_usuario_seleccionado > 0): ?>
            <div class="col-auto">
                <a href="reportes.php" class="btn btn-outline-secondary btn-sm">Cambiar usuario</a>
            </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<?php if($message): ?>
    <div class="alert alert-<?= $msgType ?> alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($message) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($id_usuario_seleccionado <= 0): ?>
<div class="alert alert-info">
    <i class="fa-solid fa-info-circle me-2"></i>
    Seleccione un usuario arriba para activar o desactivar los reportes que ese usuario podrá ver.
</div>
<?php endif; ?>

<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th class="ps-4">Nombre</th>
                    <th>Código</th>
                    <th>Descripción</th>
                    <?php if ($id_usuario_seleccionado > 0): ?>
                    <th class="text-center">Visible para usuario</th>
                    <?php endif; ?>
                    <th class="text-end pe-4">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($reports)): ?>
                    <tr><td colspan="<?= $id_usuario_seleccionado > 0 ? 5 : 4 ?>" class="text-center py-4 text-muted">No hay reportes configurados.</td></tr>
                <?php else: ?>
                    <?php foreach($reports as $r): ?>
                    <tr>
                        <td class="ps-4 fw-bold"><?= htmlspecialchars($r['nombre'] ?? '') ?></td>
                        <td><span class="badge bg-secondary font-monospace"><?= htmlspecialchars($r['codigo'] ?? '') ?></span></td>
                        <td class="text-muted small"><?= htmlspecialchars($r['descripcion'] ?? '') ?></td>
                        <?php if ($id_usuario_seleccionado > 0):
                            $activo = isset($reporteVisible[(int)$r['id_tipo_reporte']]) ? $reporteVisible[(int)$r['id_tipo_reporte']] : 1;
                        ?>
                        <td class="text-center">
                            <form method="POST" action="reportes.php?id_usuario=<?= $id_usuario_seleccionado ?>" class="d-inline">
                                <input type="hidden" name="toggle_reporte_usuario" value="1">
                                <input type="hidden" name="id_tipo_reporte" value="<?= (int)$r['id_tipo_reporte'] ?>">
                                <input type="hidden" name="new_state" value="<?= $activo ? 0 : 1 ?>">
                                <?php if ($activo): ?>
                                <button type="submit" class="btn btn-success btn-sm px-3"><i class="fa-solid fa-eye me-1"></i>Visible</button>
                                <?php else: ?>
                                <button type="submit" class="btn btn-secondary btn-sm px-3"><i class="fa-solid fa-eye-slash me-1"></i>Oculto</button>
                                <?php endif; ?>
                            </form>
                        </td>
                        <?php endif; ?>
                        <td class="text-end pe-4">
                            <button class="btn btn-sm btn-outline-primary me-1" onclick='openModal("edit", <?= json_encode($r) ?>)'>
                                <i class="fa-solid fa-pen"></i>
                            </button>
                            <form method="POST" action="reportes.php<?= $id_usuario_seleccionado > 0 ? '?id_usuario='.$id_usuario_seleccionado : '' ?>" style="display:inline;" onsubmit="return confirm('¿Eliminar este tipo de reporte?');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $r['id_tipo_reporte'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Nuevo/Editar Reporte -->
<div class="modal fade" id="reportModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title d-flex align-items-center" id="modalTitle">
                    <i class="fa-solid fa-plus-circle me-2"></i>Nuevo Reporte
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form method="POST" action="reportes.php<?= $id_usuario_seleccionado > 0 ? '?id_usuario='.$id_usuario_seleccionado : '' ?>" id="reportForm">
                <div class="modal-body py-4">
                    <input type="hidden" name="action" id="formAction" value="add">
                    <input type="hidden" name="id" id="reportId">
                    
                    <div class="mb-4">
                        <label class="form-label fw-semibold" for="reportName">
                            <i class="fa-solid fa-heading text-primary me-2" style="width:18px;"></i>Nombre del reporte
                        </label>
                        <input type="text" name="nombre" id="reportName" class="form-control form-control-lg" 
                               placeholder="Ej: Conservación y Verificación PLD" required maxlength="100">
                        <small class="text-muted">Texto visible en el menú de Reportes.</small>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label fw-semibold" for="reportCode">
                            <i class="fa-solid fa-code text-primary me-2" style="width:18px;"></i>Código (archivo PHP)
                        </label>
                        <input type="text" name="codigo" id="reportCode" class="form-control form-control-lg font-monospace" 
                               placeholder="conservacion_pld.php" required maxlength="80">
                        <small class="text-muted">Nombre completo del archivo. Debe coincidir con la ruta real para el control por usuario. Ej: reporte_riesgos.php, bitacora_actividad.php</small>
                    </div>
                    
                    <div class="mb-0">
                        <label class="form-label fw-semibold" for="reportDesc">
                            <i class="fa-solid fa-align-left text-primary me-2" style="width:18px;"></i>Descripción (opcional)
                        </label>
                        <textarea name="descripcion" id="reportDesc" class="form-control" rows="3" 
                                  placeholder="Breve descripción del reporte para referencia interna..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light border-top">
                    <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">
                        <i class="fa-solid fa-xmark me-2"></i>Cancelar
                    </button>
                    <button type="submit" class="btn btn-primary px-4">
                        <i class="fa-solid fa-check me-2"></i>Guardar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const modal = new bootstrap.Modal(document.getElementById('reportModal'));

    function openModal(mode, data = null) {
        const titleEl = document.getElementById('modalTitle');
        if (mode === 'add') {
            titleEl.innerHTML = '<i class="fa-solid fa-plus-circle me-2"></i>Nuevo Reporte';
            document.getElementById('formAction').value = 'add';
            document.getElementById('reportId').value = '';
            document.getElementById('reportName').value = '';
            document.getElementById('reportCode').value = '';
            document.getElementById('reportDesc').value = '';
        } else {
            titleEl.innerHTML = '<i class="fa-solid fa-pen-to-square me-2"></i>Editar Reporte';
            document.getElementById('formAction').value = 'edit';
            document.getElementById('reportId').value = data.id_tipo_reporte;
            document.getElementById('reportName').value = data.nombre || '';
            document.getElementById('reportCode').value = data.codigo || '';
            document.getElementById('reportDesc').value = data.descripcion || '';
        }
        modal.show();
        setTimeout(() => document.getElementById('reportName').focus(), 350);
    }
</script>

<?php include '../templates/footer.php'; ?>

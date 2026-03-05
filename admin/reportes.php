<?php include 'header.php'; ?>
<title>Catálogo de Reportes</title>

<?php
$id_usuario_seleccionado = (int)($_GET['id_usuario'] ?? 0);

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

<div class="card shadow-sm mb-4">
    <div class="card-body py-3">
        <form method="GET" action="reportes.php" class="row g-2 align-items-center">
            <div class="col-auto">
                <label class="col-form-label fw-bold"><i class="fa-solid fa-users me-2"></i>Configurar para el usuario:</label>
            </div>
            <div class="col-md-5">
                <select name="id_usuario" class="form-select" onchange="this.form.submit()">
                    <option value="">— Todos / Configuración general —</option>
                    <?php foreach ($listaUsuarios as $u): ?>
                        <option value="<?= (int)$u['id_usuario'] ?>" <?= $id_usuario_seleccionado === (int)$u['id_usuario'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($u['nombre'] ?? 'Usuario') ?> (<?= htmlspecialchars($u['login_user'] ?? '') ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($id_usuario_seleccionado > 0): ?>
            <div class="col-auto">
                <a href="reportes.php" class="btn btn-outline-secondary btn-sm">Ver todos</a>
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
                    <tr><td colspan="4" class="text-center py-4 text-muted">No hay reportes configurados.</td></tr>
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

<!-- Modal -->
<div class="modal fade" id="reportModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Nuevo Reporte</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="reportes.php<?= $id_usuario_seleccionado > 0 ? '?id_usuario='.$id_usuario_seleccionado : '' ?>">
                <div class="modal-body">
                    <input type="hidden" name="action" id="formAction" value="add">
                    <input type="hidden" name="id" id="reportId">
                    
                    <div class="mb-3">
                        <label class="form-label">Nombre del Reporte</label>
                        <input type="text" name="nombre" id="reportName" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Código Interno</label>
                        <input type="text" name="codigo" id="reportCode" class="form-control" placeholder="Ej: REP_MENSUAL" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Descripción</label>
                        <textarea name="descripcion" id="reportDesc" class="form-control" rows="3"></textarea>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const modal = new bootstrap.Modal(document.getElementById('reportModal'));

    function openModal(mode, data = null) {
        if (mode === 'add') {
            document.getElementById('modalTitle').innerText = 'Nuevo Reporte';
            document.getElementById('formAction').value = 'add';
            document.getElementById('reportId').value = '';
            document.getElementById('reportName').value = '';
            document.getElementById('reportCode').value = '';
            document.getElementById('reportDesc').value = '';
        } else {
            document.getElementById('modalTitle').innerText = 'Editar Reporte';
            document.getElementById('formAction').value = 'edit';
            document.getElementById('reportId').value = data.id_tipo_reporte;
            document.getElementById('reportName').value = data.nombre || '';
            document.getElementById('reportCode').value = data.codigo || '';
            document.getElementById('reportDesc').value = data.descripcion || '';
        }
        modal.show();
    }
</script>

<?php include '../templates/footer.php'; ?>

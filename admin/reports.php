<?php include 'header.php'; ?>
<title>Catálogo de Reportes</title>

<?php
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
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Catálogo de Tipos de Reporte</h2>
    <button class="btn btn-primary" onclick="openModal('add')">
        <i class="fa-solid fa-plus me-2"></i>Nuevo Reporte
    </button>
</div>

<?php if($message): ?>
    <div class="alert alert-<?= $msgType ?> alert-dismissible fade show" role="alert">
        <?= $message ?>
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
                    <th class="text-end pe-4">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($reports)): ?>
                    <tr><td colspan="4" class="text-center py-4 text-muted">No hay reportes configurados.</td></tr>
                <?php else: ?>
                    <?php foreach($reports as $r): ?>
                    <tr>
                        <td class="ps-4 fw-bold"><?= htmlspecialchars($r['nombre']) ?></td>
                        <td><span class="badge bg-secondary font-monospace"><?= htmlspecialchars($r['codigo']) ?></span></td>
                        <td class="text-muted small"><?= htmlspecialchars($r['descripcion']) ?></td>
                        <td class="text-end pe-4">
                            <button class="btn btn-sm btn-outline-primary me-1" onclick='openModal("edit", <?= json_encode($r) ?>)'>
                                <i class="fa-solid fa-pen"></i>
                            </button>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('¿Eliminar este tipo de reporte?');">
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
            <form method="POST">
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
            document.getElementById('reportName').value = data.nombre;
            document.getElementById('reportCode').value = data.codigo;
            document.getElementById('reportDesc').value = data.descripcion;
        }
        modal.show();
    }
</script>

</body>
</html>
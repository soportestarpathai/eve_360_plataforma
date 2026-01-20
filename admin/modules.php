<?php include 'header.php'; ?>
<title>Gestión de Módulos</title>

<?php
// Handle Toggle
if (isset($_POST['toggle_module'])) {
    $id = $_POST['id_modulo'];
    $state = $_POST['new_state'];
    $pdo->prepare("UPDATE config_modulos SET activo = ? WHERE id_modulo = ?")->execute([$state, $id]);
}

$modules = $pdo->query("SELECT * FROM config_modulos ORDER BY id_modulo")->fetchAll(PDO::FETCH_ASSOC);
?>

<h2 class="mb-4">Módulos Habilitados</h2>
<p class="text-muted">Active o desactive los módulos disponibles para esta instancia de la aplicación.</p>

<div class="row">
    <?php foreach($modules as $mod): ?>
    <div class="col-md-6">
        <div class="card p-3 d-flex flex-row justify-content-between align-items-center">
            <div>
                <h5 class="mb-1"><?= htmlspecialchars($mod['nombre_mostrar']) ?></h5>
                <small class="text-muted font-monospace"><?= $mod['nombre_clave'] ?></small>
            </div>
            <form method="POST">
                <input type="hidden" name="toggle_module" value="1">
                <input type="hidden" name="id_modulo" value="<?= $mod['id_modulo'] ?>">
                <input type="hidden" name="new_state" value="<?= $mod['activo'] ? 0 : 1 ?>">
                
                <?php if($mod['activo']): ?>
                    <button type="submit" class="btn btn-success btn-sm px-3">
                        <i class="fa-solid fa-toggle-on me-2"></i>Activo
                    </button>
                <?php else: ?>
                    <button type="submit" class="btn btn-secondary btn-sm px-3">
                        <i class="fa-solid fa-toggle-off me-2"></i>Inactivo
                    </button>
                <?php endif; ?>
            </form>
        </div>
    </div>
    <?php endforeach; ?>
</div>

</body>
</html>
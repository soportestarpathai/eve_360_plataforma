<?php include 'header.php'; ?>
<title>Módulos para Usuarios</title>

<?php
$id_usuario_seleccionado = (int)($_GET['id_usuario'] ?? 0);

// Handle Toggle
if (isset($_POST['toggle_module'])) {
    $id = (int)$_POST['id_modulo'];
    $state = (int)$_POST['new_state'];
    try {
        if ($id_usuario_seleccionado > 0) {
            $pdo->prepare("
                INSERT INTO config_modulos_usuario (id_usuario, id_modulo, activo)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE activo = VALUES(activo)
            ")->execute([$id_usuario_seleccionado, $id, $state]);
        } else {
            $pdo->prepare("UPDATE config_modulos SET activo = ? WHERE id_modulo = ?")->execute([$state, $id]);
        }
    } catch (Exception $e) { /* tabla no existe */ }
}

// Load modules: base + per-user override
$modules = $pdo->query("SELECT id_modulo, nombre_clave, nombre_mostrar, activo FROM config_modulos ORDER BY id_modulo")->fetchAll(PDO::FETCH_ASSOC);
if ($id_usuario_seleccionado > 0) {
    try {
        $stmtU = $pdo->prepare("SELECT id_modulo, activo FROM config_modulos_usuario WHERE id_usuario = ?");
        $stmtU->execute([$id_usuario_seleccionado]);
        $override = [];
        while ($r = $stmtU->fetch(PDO::FETCH_ASSOC)) $override[(int)$r['id_modulo']] = (int)$r['activo'];
        foreach ($modules as &$m) {
            if (isset($override[(int)$m['id_modulo']])) $m['activo'] = $override[(int)$m['id_modulo']];
        }
        unset($m);
    } catch (Exception $e) { /* tabla no existe */ }
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
?>

<h2 class="mb-4">Módulos Habilitados</h2>
<div class="alert alert-info border-0 mb-4 shadow-sm">
    <i class="fa-solid fa-users me-2"></i>
    <strong>Módulos para usuarios.</strong> Active o desactive los módulos visibles en la plataforma cuando los usuarios inician sesión.
</div>

<div class="card shadow-sm mb-4">
    <div class="card-body py-3">
        <form method="GET" action="modulos.php" class="row g-2 align-items-center">
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
                <a href="modulos.php" class="btn btn-outline-secondary btn-sm">Ver todos</a>
            </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<p class="text-muted">Active o desactive los módulos disponibles para esta instancia de la aplicación.</p>

<div class="row">
    <?php foreach($modules as $mod): ?>
    <div class="col-md-6">
        <div class="card p-3 d-flex flex-row justify-content-between align-items-center">
            <div>
                <h5 class="mb-1"><?= htmlspecialchars($mod['nombre_mostrar'] ?? '') ?></h5>
                <small class="text-muted font-monospace"><?= htmlspecialchars($mod['nombre_clave'] ?? '') ?></small>
            </div>
            <form method="POST" action="modulos.php<?= $id_usuario_seleccionado > 0 ? '?id_usuario='.$id_usuario_seleccionado : '' ?>">
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

<?php include '../templates/footer.php'; ?>

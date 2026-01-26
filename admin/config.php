<?php include 'header.php'; ?>
<title>Configuración General y Menús</title>

<?php
// --- ACTIONS HANDLER ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. SAVE GENERAL CONFIGURATION
    if (isset($_POST['action']) && $_POST['action'] === 'save_config') {
        $logoPath = $_POST['existing_logo'];

        // Handle Logo Upload
        if (isset($_FILES['logo_file']) && $_FILES['logo_file']['error'] === UPLOAD_ERR_OK) {
            $fileTmpPath = $_FILES['logo_file']['tmp_name'];
            $fileName = $_FILES['logo_file']['name'];
            $fileNameCmps = explode(".", $fileName);
            $fileExtension = strtolower(end($fileNameCmps));
            $allowedfileExtensions = array('jpg', 'jpeg', 'png');

            if (in_array($fileExtension, $allowedfileExtensions)) {
                $uploadFileDir = '../assets/img/';
                if (!is_dir($uploadFileDir)) mkdir($uploadFileDir, 0755, true);
                $newFileName = 'logo_company_' . time() . '.' . $fileExtension;
                $dest_path = $uploadFileDir . $newFileName;
                if (move_uploaded_file($fileTmpPath, $dest_path)) {
                    $logoPath = 'assets/img/' . $newFileName;
                }
            }
        }

        try {
            // LOGIC: Check if "Actividad Vulnerable" (ID 1) is selected
            $id_tipo_empresa = $_POST['id_tipo_empresa'];
            $id_vulnerable = 0; // Default to 0

            if ($id_tipo_empresa == 1) {
                // If it is vulnerable, use the selected ID
                $id_vulnerable = $_POST['id_vulnerable'] ?? 0;
            }

            // UPDATED QUERY: Included 'id_vulnerable'
            $stmt = $pdo->prepare("
                UPDATE config_empresa SET 
                    nombre_empresa = ?, logo_url = ?, color_primario = ?, 
                    max_usuarios = ?, max_busquedas_api = ?, 
                    id_tipo_empresa = ?, id_vulnerable = ?
                WHERE id_config = 1
            ");
            $stmt->execute([
                $_POST['nombre_empresa'], $logoPath, $_POST['color_primario'],
                $_POST['max_usuarios'], $_POST['max_busquedas_api'], 
                $id_tipo_empresa, $id_vulnerable
            ]);
            echo '<div class="alert alert-success mt-3">Configuración actualizada.</div>';
        } catch (Exception $e) {
            echo '<div class="alert alert-danger mt-3">Error: ' . $e->getMessage() . '</div>';
        }
    }

    // 2. SAVE MENU ITEM
    if (isset($_POST['action']) && $_POST['action'] === 'save_menu') {
        try {
            $id_menu = $_POST['id_menu_access'] ?? ''; 
            $id_tipo = $_POST['id_tipo_empresa_menu'];
            $seccion = $_POST['seccion'];
            $icon    = $_POST['icon'];
            $file    = $_POST['file_path'];
            $parent  = $_POST['id_parent'];

            if ($id_menu) {
                $stmt = $pdo->prepare("UPDATE menu_access SET id_tipo_empresa=?, seccion=?, icon=?, file_path=?, id_parent=? WHERE id_menu_access=?");
                $stmt->execute([$id_tipo, $seccion, $icon, $file, $parent, $id_menu]);
                echo '<div class="alert alert-success mt-3">Elemento de menú actualizado.</div>';
            } else {
                $stmt = $pdo->prepare("INSERT INTO menu_access (id_tipo_empresa, seccion, icon, file_path, id_parent) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$id_tipo, $seccion, $icon, $file, $parent]);
                echo '<div class="alert alert-success mt-3">Nuevo elemento agregado al menú.</div>';
            }
        } catch (Exception $e) {
            echo '<div class="alert alert-danger mt-3">Error al guardar menú: ' . $e->getMessage() . '</div>';
        }
    }

    // 3. DELETE MENU ITEM (Updated Logic: Promote children)
    if (isset($_POST['action']) && $_POST['action'] === 'delete_menu') {
        try {
            $idToDelete = $_POST['id_menu_delete'];
            
            // Start Transaction to ensure data integrity
            $pdo->beginTransaction();

            // Step A: Promote children to Parents (id_parent = 0)
            // This prevents them from being deleted or hidden if they depended on this parent
            $stmtPromote = $pdo->prepare("UPDATE menu_access SET id_parent = 0 WHERE id_parent = ?");
            $stmtPromote->execute([$idToDelete]);

            // Step B: Delete the item
            $stmtDelete = $pdo->prepare("DELETE FROM menu_access WHERE id_menu_access = ?");
            $stmtDelete->execute([$idToDelete]);

            $pdo->commit();
            
            echo '<div class="alert alert-warning mt-3">
                    <i class="fa-solid fa-check-circle me-2"></i>
                    Elemento eliminado. Los submenús asociados (si existían) ahora son elementos principales.
                  </div>';

        } catch (Exception $e) {
            $pdo->rollBack();
            echo '<div class="alert alert-danger">Error: ' . $e->getMessage() . '</div>';
        }
    }
}

// --- DATA FETCHING ---
// 1. Config
$stmt = $pdo->query("SELECT * FROM config_empresa WHERE id_config = 1");
$config = $stmt->fetch(PDO::FETCH_ASSOC);

// Ensure config has default values to prevent null errors
if (!$config) {
    $config = [
        'nombre_empresa' => '',
        'logo_url' => '',
        'color_primario' => '#0d6efd',
        'max_usuarios' => 10,
        'max_busquedas_api' => 500,
        'id_tipo_empresa' => 1,
        'id_vulnerable' => 0
    ];
} else {
    // Set defaults for null values
    $config['nombre_empresa'] = $config['nombre_empresa'] ?? '';
    $config['logo_url'] = $config['logo_url'] ?? '';
    $config['color_primario'] = $config['color_primario'] ?? '#0d6efd';
    $config['max_usuarios'] = $config['max_usuarios'] ?? 10;
    $config['max_busquedas_api'] = $config['max_busquedas_api'] ?? 500;
    $config['id_tipo_empresa'] = $config['id_tipo_empresa'] ?? 1;
    $config['id_vulnerable'] = $config['id_vulnerable'] ?? 0;
}

// 2. Company Types
$stmtTypes = $pdo->query("SELECT * FROM cat_tipo_empresa ORDER BY id_tipo_empresa ASC");
$companyTypes = $stmtTypes->fetchAll(PDO::FETCH_ASSOC);

// 3. Vulnerable Activities Catalog (NEW)
$stmtVuln = $pdo->query("SELECT * FROM cat_vulnerables ORDER BY nombre ASC");
$vulnerables = $stmtVuln->fetchAll(PDO::FETCH_ASSOC);

// 4. Menu Items
$stmtMenu = $pdo->query("
    SELECT m.*, t.nombre as nombre_empresa_tipo 
    FROM menu_access m 
    LEFT JOIN cat_tipo_empresa t ON m.id_tipo_empresa = t.id_tipo_empresa 
    ORDER BY m.id_tipo_empresa ASC, m.id_parent ASC, m.id_menu_access ASC
");
$menuItems = $stmtMenu->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container mt-4 mb-5">
    
    <ul class="nav nav-tabs mb-4" id="configTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="general-tab" data-bs-toggle="tab" data-bs-target="#general" type="button" role="tab"><i class="fa-solid fa-gears me-2"></i>General</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="pld-tab" data-bs-toggle="tab" data-bs-target="#pld" type="button" role="tab"><i class="fa-solid fa-shield-halved me-2"></i>Padrón PLD</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="responsable-pld-tab" data-bs-toggle="tab" data-bs-target="#responsable-pld" type="button" role="tab"><i class="fa-solid fa-user-tie me-2"></i>Responsables PLD</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="menu-tab" data-bs-toggle="tab" data-bs-target="#menu" type="button" role="tab"><i class="fa-solid fa-list-tree me-2"></i>Gestor de Menús</button>
        </li>
    </ul>

    <div class="tab-content" id="configTabsContent">
        
        <div class="tab-pane fade show active" id="general" role="tabpanel">
            <div class="card shadow-sm">
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="save_config">
                        <input type="hidden" name="existing_logo" value="<?= htmlspecialchars($config['logo_url']) ?>">

                        <h5 class="mb-3 text-primary">Identidad Visual</h5>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Nombre de la Empresa</label>
                                <input type="text" name="nombre_empresa" class="form-control" value="<?= htmlspecialchars($config['nombre_empresa']) ?>" required>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Giro / Tipo de Empresa</label>
                                <select name="id_tipo_empresa" id="selectTipoEmpresa" class="form-select" required onchange="toggleVulnerable()">
                                    <?php foreach ($companyTypes as $type): ?>
                                        <option value="<?= $type['id_tipo_empresa'] ?>" 
                                            <?= ($config['id_tipo_empresa'] == $type['id_tipo_empresa']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($type['nombre']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="row mb-3" id="vulnerableContainer" style="display: none;">
                            <div class="col-md-12">
                                <div class="p-3 bg-warning-subtle border border-warning rounded">
                                    <label class="form-label fw-bold text-dark"><i class="fa-solid fa-triangle-exclamation me-2"></i>Seleccione la Actividad Vulnerable</label>
                                    <select name="id_vulnerable" class="form-select border-warning">
                                        <option value="0">-- Seleccione --</option>
                                        <?php foreach ($vulnerables as $vuln): ?>
                                            <option value="<?= $vuln['id_vulnerable'] ?>" 
                                                <?= ($config['id_vulnerable'] == $vuln['id_vulnerable']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($vuln['nombre']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-text text-dark">Especifique el tipo de actividad para configurar los umbrales PLD correctamente.</div>
                                </div>
                            </div>
                        </div>

                        <?php if (!empty($config['logo_url'])): ?>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Logo Actual</label><br>
                            <img src="../<?= htmlspecialchars($config['logo_url']) ?>" alt="Logo" style="height: 50px; object-fit: contain;" class="border p-1 rounded" onerror="this.style.display='none'">
                        </div>
                        <?php endif; ?>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Cambiar Logo</label>
                            <input type="file" name="logo_file" class="form-control" accept="image/png, image/jpeg">
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Color Primario</label>
                            <input type="color" name="color_primario" class="form-control form-control-color" value="<?= htmlspecialchars($config['color_primario']) ?>">
                        </div>

                        <hr>
                        <h5 class="mb-3 text-primary">Límites</h5>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Máximo de Usuarios</label>
                                <input type="number" name="max_usuarios" class="form-control" value="<?= $config['max_usuarios'] ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Límite Búsquedas API</label>
                                <input type="number" name="max_busquedas_api" class="form-control" value="<?= $config['max_busquedas_api'] ?>" required>
                            </div>
                        </div>

                        <div class="text-end">
                            <button type="submit" class="btn btn-primary">Guardar Configuración</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- TAB: Padrón PLD (VAL-PLD-001 y VAL-PLD-002) -->
        <div class="tab-pane fade" id="pld" role="tabpanel" id="pld-revalidation">
            <?php
            require_once __DIR__ . '/../config/pld_validation.php';
            require_once __DIR__ . '/../config/pld_revalidation.php';
            
            $pldValidation = validatePatronPLD($pdo);
            $revalidationStatus = checkRevalidationDue($pdo);
            ?>
            
            <div class="row">
                <!-- Estado Actual -->
                <div class="col-md-6 mb-4">
                    <div class="card shadow-sm">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="fa-solid fa-shield-halved me-2"></i>Estado del Padrón PLD</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Estatus de Habilitación</label>
                                <div>
                                    <?php if ($pldValidation['habilitado']): ?>
                                        <span class="badge bg-success fs-6">
                                            <i class="fa-solid fa-check-circle me-1"></i>HABILITADO
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-danger fs-6">
                                            <i class="fa-solid fa-xmark-circle me-1"></i>NO HABILITADO
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <small class="text-muted"><?= htmlspecialchars($pldValidation['razon'] ?? '') ?></small>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label fw-bold">Revalidación Periódica</label>
                                <div>
                                    <?php if ($revalidationStatus['vencida']): ?>
                                        <span class="badge bg-danger">
                                            <i class="fa-solid fa-exclamation-triangle me-1"></i>VENCIDA
                                        </span>
                                    <?php elseif ($revalidationStatus['proxima_vencer']): ?>
                                        <span class="badge bg-warning text-dark">
                                            <i class="fa-solid fa-clock me-1"></i>PRÓXIMA A VENCER
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-success">
                                            <i class="fa-solid fa-check-circle me-1"></i>VIGENTE
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <small class="text-muted"><?= htmlspecialchars($revalidationStatus['mensaje'] ?? '') ?></small>
                            </div>
                            
                            <?php if (isset($pldValidation['detalles']) && !empty($pldValidation['detalles'])): ?>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Detalles</label>
                                <ul class="small mb-0">
                                    <?php foreach ($pldValidation['detalles'] as $key => $value): ?>
                                        <li><strong><?= htmlspecialchars($key) ?>:</strong> 
                                            <?= is_array($value) ? json_encode($value) : htmlspecialchars($value) ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Configuración del Padrón -->
                <div class="col-md-6 mb-4">
                    <div class="card shadow-sm">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0"><i class="fa-solid fa-edit me-2"></i>Configurar Padrón PLD</h5>
                        </div>
                        <div class="card-body">
                            <form id="pldPatronForm">
                                <div class="mb-3">
                                    <label class="form-label fw-bold">
                                        <i class="fa-solid fa-file-contract me-2"></i>Folio del Padrón PLD
                                    </label>
                                    <input type="text" 
                                           id="folioPatron" 
                                           class="form-control" 
                                           value="<?= htmlspecialchars($config['folio_patron_pld'] ?? '') ?>" 
                                           placeholder="Ej: FOLIO-123456789">
                                    <small class="form-text text-muted">Folio asignado por el SAT en el Portal PLD</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label fw-bold">
                                        <i class="fa-solid fa-circle-check me-2"></i>Estatus en el Padrón
                                    </label>
                                    <select id="estatusPatron" class="form-select">
                                        <option value="">-- Seleccione --</option>
                                        <option value="vigente" <?= (strtolower($config['estatus_patron_pld'] ?? '') === 'vigente') ? 'selected' : '' ?>>Vigente</option>
                                        <option value="baja" <?= (strtolower($config['estatus_patron_pld'] ?? '') === 'baja') ? 'selected' : '' ?>>Baja</option>
                                        <option value="suspendido" <?= (strtolower($config['estatus_patron_pld'] ?? '') === 'suspendido') ? 'selected' : '' ?>>Suspendido</option>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label fw-bold">
                                        <i class="fa-solid fa-list me-2"></i>Fracciones Activas
                                    </label>
                                    <textarea id="fraccionesActivas" 
                                              class="form-control" 
                                              rows="3" 
                                              placeholder='Ej: ["V", "V Bis", "VI"] o V, V Bis, VI'><?= htmlspecialchars($config['fracciones_activas'] ?? '') ?></textarea>
                                    <small class="form-text text-muted">Formato JSON array o separado por comas</small>
                                </div>
                                
                                <div class="d-grid gap-2">
                                    <button type="button" class="btn btn-primary" onclick="savePatronPLD()">
                                        <i class="fa-solid fa-save me-2"></i>Guardar Configuración
                                    </button>
                                    <button type="button" class="btn btn-warning" onclick="revalidatePatronPLD()">
                                        <i class="fa-solid fa-rotate me-2"></i>Revalidar Padrón
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="responsable-pld" role="tabpanel">
            <?php
            require_once __DIR__ . '/../config/pld_responsable_validation.php';
            
            // Obtener lista de clientes morales y fideicomisos
            $stmt = $pdo->prepare("
                SELECT 
                    c.id_cliente,
                    c.no_contrato,
                    c.alias,
                    tp.nombre as tipo_persona,
                    COALESCE(cm.razon_social, cf.nombre) as nombre_cliente,
                    c.restriccion_usuario
                FROM clientes c
                LEFT JOIN cat_tipo_persona tp ON c.id_tipo_persona = tp.id_tipo_persona
                LEFT JOIN clientes_morales cm ON c.id_cliente = cm.id_cliente
                LEFT JOIN clientes_fisicas cf ON c.id_cliente = cf.id_cliente
                WHERE (tp.es_moral = 1 OR tp.es_fideicomiso = 1)
                  AND c.id_status = 1
                ORDER BY c.fecha_apertura DESC
            ");
            $stmt->execute();
            $clientesRequeridos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Obtener lista de usuarios activos
            $stmt = $pdo->prepare("
                SELECT id_usuario, nombre, login_user
                FROM usuarios
                WHERE id_status_usuario = 1
                ORDER BY nombre
            ");
            $stmt->execute();
            $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
            ?>
            
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fa-solid fa-user-tie me-2"></i>Gestión de Responsables PLD</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-4">
                        <i class="fa-solid fa-info-circle me-2"></i>
                        Las personas morales y fideicomisos deben tener un responsable PLD designado. 
                        Seleccione un cliente para ver o designar su responsable.
                    </p>
                    
                    <div class="table-responsive" style="max-height: 600px; overflow-y: auto;">
                        <table class="table table-hover">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th>Contrato</th>
                                    <th>Cliente</th>
                                    <th>Tipo</th>
                                    <th>Responsable</th>
                                    <th>Estado</th>
                                    <th class="text-end">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($clientesRequeridos)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center text-muted p-4">
                                            No hay clientes morales o fideicomisos registrados.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($clientesRequeridos as $cliente): ?>
                                        <?php
                                        $validation = validateResponsablePLD($pdo, $cliente['id_cliente']);
                                        ?>
                                        <tr class="<?= $validation['restriccion'] ? 'table-warning' : '' ?>">
                                            <td><?= htmlspecialchars($cliente['no_contrato']) ?></td>
                                            <td>
                                                <strong><?= htmlspecialchars($cliente['nombre_cliente'] ?? 'Sin nombre') ?></strong><br>
                                                <small class="text-muted"><?= htmlspecialchars($cliente['alias'] ?? '') ?></small>
                                            </td>
                                            <td>
                                                <span class="badge bg-info"><?= htmlspecialchars($cliente['tipo_persona']) ?></span>
                                            </td>
                                            <td>
                                                <?php if ($validation['tiene_responsable']): ?>
                                                    <i class="fa-solid fa-user-check text-success me-1"></i>
                                                    <strong><?= htmlspecialchars($validation['detalles']['responsable_nombre'] ?? 'N/A') ?></strong><br>
                                                    <small class="text-muted"><?= htmlspecialchars($validation['detalles']['responsable_email'] ?? '') ?></small>
                                                <?php else: ?>
                                                    <span class="text-danger">
                                                        <i class="fa-solid fa-user-xmark me-1"></i>Sin responsable
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($validation['restriccion']): ?>
                                                    <span class="badge bg-danger">RESTRICCION_USUARIO</span>
                                                <?php else: ?>
                                                    <span class="badge bg-success">Sin restricción</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end">
                                                <button class="btn btn-sm btn-primary" 
                                                        onclick="openDesignarModal(<?= $cliente['id_cliente'] ?>, '<?= htmlspecialchars(addslashes($cliente['nombre_cliente'] ?? 'Cliente')) ?>', <?= $validation['tiene_responsable'] ? 'true' : 'false' ?>, <?= $validation['detalles']['id_responsable'] ?? 'null' ?>)">
                                                    <i class="fa-solid fa-edit me-1"></i>
                                                    <?= $validation['tiene_responsable'] ? 'Cambiar' : 'Designar' ?>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Modal para designar responsable -->
            <div class="modal fade" id="designarResponsableModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header bg-primary text-white">
                            <h5 class="modal-title">
                                <i class="fa-solid fa-user-tie me-2"></i>Designar Responsable PLD
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p><strong>Cliente:</strong> <span id="modalClienteNombre"></span></p>
                            
                            <div class="mb-3">
                                <label class="form-label fw-bold">Seleccionar Usuario Responsable</label>
                                <select id="selectUsuarioResponsable" class="form-select">
                                    <option value="">-- Seleccione un usuario --</option>
                                    <?php foreach ($usuarios as $usuario): ?>
                                        <option value="<?= $usuario['id_usuario'] ?>">
                                            <?= htmlspecialchars($usuario['nombre']) ?> (<?= htmlspecialchars($usuario['login_user']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Observaciones (opcional)</label>
                                <textarea id="observacionesResponsable" class="form-control" rows="3" placeholder="Observaciones sobre la designación..."></textarea>
                            </div>
                            
                            <input type="hidden" id="modalClienteId">
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="button" class="btn btn-primary" onclick="saveDesignarResponsable()">
                                <i class="fa-solid fa-save me-2"></i>Guardar Designación
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="menu" role="tabpanel">
            <div class="row">
                <div class="col-md-4">
                    <div class="card shadow-sm mb-3">
                        <div class="card-header bg-light fw-bold">Agregar / Editar Ítem</div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="save_menu">
                                <input type="hidden" name="id_menu_access" id="menuId"> 

                                <div class="mb-2">
                                    <label class="form-label small fw-bold">Tipo de Empresa</label>
                                    <select name="id_tipo_empresa_menu" id="menuTipo" class="form-select form-select-sm" required>
                                        <?php foreach ($companyTypes as $type): ?>
                                            <option value="<?= $type['id_tipo_empresa'] ?>"><?= htmlspecialchars($type['nombre']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="mb-2">
                                    <label class="form-label small fw-bold">Texto (Sección)</label>
                                    <input type="text" name="seccion" id="menuSeccion" class="form-control form-control-sm" placeholder="Ej: Clientes" required>
                                </div>

                                <div class="mb-2">
                                    <label class="form-label small fw-bold">Icono (FontAwesome)</label>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text"><i class="fa-solid fa-icons"></i></span>
                                        <input type="text" name="icon" id="menuIcon" class="form-control" placeholder="Ej: fa-users">
                                    </div>
                                    <div class="form-text" style="font-size: 0.7rem;">Solo el nombre de la clase (sin comillas).</div>
                                </div>

                                <div class="mb-2">
                                    <label class="form-label small fw-bold">Archivo Destino</label>
                                    <input type="text" name="file_path" id="menuFile" class="form-control form-control-sm" placeholder="Ej: clientes.php">
                                </div>

                                <div class="mb-3">
                                    <label class="form-label small fw-bold">Padre (Submenú de...)</label>
                                    <select name="id_parent" id="menuParent" class="form-select form-select-sm">
                                        <option value="0">-- Ninguno (Raíz) --</option>
                                        <?php foreach ($menuItems as $item): ?>
                                            <option value="<?= $item['id_menu_access'] ?>">
                                                <?= htmlspecialchars($item['seccion']) ?> (<?= htmlspecialchars(substr($item['nombre_empresa_tipo'] ?? 'N/A', 0, 10)) ?>...)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-sm btn-success">Guardar Ítem</button>
                                    <button type="button" class="btn btn-sm btn-secondary" onclick="resetMenuForm()">Limpiar</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-md-8">
                    <div class="card shadow-sm">
                        <div class="card-header bg-light fw-bold">Estructura Actual</div>
                        <div class="card-body p-0">
                            <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
                                <table class="table table-hover table-sm mb-0">
                                    <thead class="table-light sticky-top">
                                        <tr>
                                            <th>Icono</th>
                                            <th>Sección</th>
                                            <th>Archivo</th>
                                            <th>Padre ID</th>
                                            <th>Empresa</th>
                                            <th class="text-end">Acción</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if(empty($menuItems)): ?>
                                            <tr><td colspan="6" class="text-center text-muted p-3">No hay elementos configurados.</td></tr>
                                        <?php else: ?>
                                            <?php foreach ($menuItems as $m): ?>
                                                <tr>
                                                    <td class="text-center text-primary"><i class="fa-solid <?= $m['icon'] ?>"></i></td>
                                                    <td class="fw-bold"><?= htmlspecialchars($m['seccion']) ?></td>
                                                    <td class="small text-muted"><?= htmlspecialchars($m['file_path']) ?></td>
                                                    <td>
                                                        <?php if($m['id_parent'] == 0): ?>
                                                            <span class="badge bg-secondary">Raíz</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-info text-dark">Sub: <?= $m['id_parent'] ?></span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="small"><?= htmlspecialchars($m['nombre_empresa_tipo'] ?? 'N/A') ?></td>
                                                    <td class="text-end">
                                                        <button class="btn btn-xs btn-outline-primary border-0" 
                                                            onclick='editMenu(<?= json_encode($m) ?>)'>
                                                            <i class="fa-solid fa-pen"></i>
                                                        </button>
                                                        <form method="POST" class="d-inline delete-menu-form">
                                                            <input type="hidden" name="action" value="delete_menu">
                                                            <input type="hidden" name="id_menu_delete" value="<?= $m['id_menu_access'] ?>">
                                                            <button type="button" class="btn btn-xs btn-outline-danger border-0" onclick="confirmDeleteMenu(this)"><i class="fa-solid fa-trash"></i></button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<script>
    // --- 1. TOGGLE VULNERABLE COMBO ---
    function toggleVulnerable() {
        const typeSelect = document.getElementById('selectTipoEmpresa');
        const container = document.getElementById('vulnerableContainer');
        
        // Assuming ID 1 is "Actividad Vulnerable"
        if (typeSelect.value == 1) {
            container.style.display = 'block';
        } else {
            container.style.display = 'none';
        }
    }

    // Run on load to set initial state
    document.addEventListener('DOMContentLoaded', toggleVulnerable);

    // --- 2. MENU EDIT FUNCTIONS ---
    function editMenu(data) {
        document.getElementById('menuId').value = data.id_menu_access;
        document.getElementById('menuTipo').value = data.id_tipo_empresa;
        document.getElementById('menuSeccion').value = data.seccion;
        document.getElementById('menuIcon').value = data.icon;
        document.getElementById('menuFile').value = data.file_path;
        document.getElementById('menuParent').value = data.id_parent;
        document.getElementById('menuSeccion').focus();
    }

    function resetMenuForm() {
        document.getElementById('menuId').value = '';
        document.getElementById('menuSeccion').value = '';
        document.getElementById('menuIcon').value = '';
        document.getElementById('menuFile').value = '';
        document.getElementById('menuParent').value = '0';
    }

    // Función para confirmar eliminación de menú con SweetAlert2
    function confirmDeleteMenu(button) {
        const form = button.closest('form');
        Swal.fire({
            title: '¿Eliminar este ítem?',
            text: 'Esta acción no se puede deshacer',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                form.submit();
            }
        });
    }

    // --- 3. PLD PATRÓN FUNCTIONS ---
    function savePatronPLD() {
        const folio = document.getElementById('folioPatron').value.trim();
        const estatus = document.getElementById('estatusPatron').value;
        let fracciones = document.getElementById('fraccionesActivas').value.trim();
        
        // Convertir fracciones a JSON si es necesario
        if (fracciones && !fracciones.startsWith('[')) {
            // Si viene separado por comas, convertir a JSON array
            const fraccionesArray = fracciones.split(',').map(f => f.trim()).filter(f => f);
            fracciones = JSON.stringify(fraccionesArray);
        }
        
        fetch('../api/revalidate_patron_pld.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                folio: folio || null,
                estatus: estatus || null,
                fracciones: fracciones || null,
                confirmar: true
            })
        })
        .then(res => {
            if (!res.ok) {
                return res.text().then(text => {
                    throw new Error(`HTTP ${res.status}: ${text.substring(0, 100)}`);
                });
            }
            return res.json();
        })
        .then(data => {
            if (data.status === 'success') {
                Swal.fire({
                    icon: 'success',
                    title: '¡Éxito!',
                    text: 'Configuración del padrón PLD guardada correctamente.',
                    confirmButtonColor: '#3085d6'
                }).then(() => {
                    location.reload();
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: data.message || 'Error desconocido',
                    confirmButtonColor: '#d33'
                });
            }
        })
        .catch(err => {
            console.error('Error:', err);
            Swal.fire({
                icon: 'error',
                title: 'Error al guardar',
                text: err.message,
                confirmButtonColor: '#d33'
            });
        });
    }

    // --- 4. RESPONSABLE PLD FUNCTIONS ---
    function openDesignarModal(idCliente, nombreCliente, tieneResponsable, idResponsable) {
        document.getElementById('modalClienteId').value = idCliente;
        document.getElementById('modalClienteNombre').textContent = nombreCliente;
        document.getElementById('selectUsuarioResponsable').value = '';
        document.getElementById('observacionesResponsable').value = '';
        
        const modal = new bootstrap.Modal(document.getElementById('designarResponsableModal'));
        modal.show();
    }
    
    function saveDesignarResponsable() {
        const idCliente = document.getElementById('modalClienteId').value;
        const idUsuarioResponsable = document.getElementById('selectUsuarioResponsable').value;
        const observaciones = document.getElementById('observacionesResponsable').value.trim();
        
        if (!idUsuarioResponsable) {
            Swal.fire({
                icon: 'warning',
                title: 'Campo requerido',
                text: 'Debe seleccionar un usuario responsable',
                confirmButtonColor: '#3085d6'
            });
            return;
        }
        
        fetch('../api/designar_responsable_pld.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                id_cliente: idCliente,
                id_usuario_responsable: idUsuarioResponsable,
                observaciones: observaciones || null
            })
        })
        .then(res => {
            if (!res.ok) {
                return res.text().then(text => {
                    throw new Error(`HTTP ${res.status}: ${text.substring(0, 100)}`);
                });
            }
            return res.json();
        })
        .then(data => {
            if (data.status === 'success') {
                Swal.fire({
                    icon: 'success',
                    title: '¡Éxito!',
                    text: data.message || 'Responsable PLD designado correctamente',
                    confirmButtonColor: '#3085d6'
                }).then(() => {
                    location.reload();
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: data.message || 'Error desconocido',
                    confirmButtonColor: '#d33'
                });
            }
        })
        .catch(err => {
            console.error('Error:', err);
            Swal.fire({
                icon: 'error',
                title: 'Error al designar responsable',
                text: err.message,
                confirmButtonColor: '#d33'
            });
        });
    }

    function revalidatePatronPLD() {
        Swal.fire({
            title: '¿Revalidar padrón PLD?',
            text: 'Esto comparará los datos actuales con los almacenados.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sí, revalidar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (!result.isConfirmed) {
                return;
            }
            
            const folio = document.getElementById('folioPatron').value.trim();
            const estatus = document.getElementById('estatusPatron').value;
            let fracciones = document.getElementById('fraccionesActivas').value.trim();
            
            // Convertir fracciones a JSON si es necesario
            if (fracciones && !fracciones.startsWith('[')) {
                const fraccionesArray = fracciones.split(',').map(f => f.trim()).filter(f => f);
                fracciones = JSON.stringify(fraccionesArray);
            }
            
            fetch('../api/revalidate_patron_pld.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    folio: folio || null,
                    estatus: estatus || null,
                    fracciones: fracciones || null,
                    confirmar: false // Primero mostrar cambios
                })
            })
            .then(res => {
                if (!res.ok) {
                    return res.text().then(text => {
                        throw new Error(`HTTP ${res.status}: ${text.substring(0, 100)}`);
                    });
                }
                return res.json();
            })
            .then(data => {
                if (data.status === 'pending_confirmation') {
                    // Mostrar cambios detectados
                    let mensajeHTML = '<div style="text-align: left;"><strong>Se detectaron cambios:</strong><ul style="margin-top: 10px;">';
                    data.cambios.forEach(cambio => {
                        const nuevoValor = Array.isArray(cambio.nuevo) ? cambio.nuevo.join(', ') : cambio.nuevo;
                        mensajeHTML += `<li><strong>${cambio.campo}:</strong> ${cambio.anterior} → ${nuevoValor}</li>`;
                    });
                    mensajeHTML += '</ul></div>';
                    
                    Swal.fire({
                        title: 'Cambios detectados',
                        html: mensajeHTML,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#3085d6',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: 'Sí, aplicar cambios',
                        cancelButtonText: 'Cancelar'
                    }).then((confirmResult) => {
                        if (confirmResult.isConfirmed) {
                            // Confirmar y aplicar
                            fetch('../api/revalidate_patron_pld.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json'
                                },
                                body: JSON.stringify({
                                    folio: folio || null,
                                    estatus: estatus || null,
                                    fracciones: fracciones || null,
                                    confirmar: true
                                })
                            })
                            .then(res => {
                                if (!res.ok) {
                                    return res.text().then(text => {
                                        throw new Error(`HTTP ${res.status}: ${text.substring(0, 100)}`);
                                    });
                                }
                                return res.json();
                            })
                            .then(data => {
                                if (data.status === 'success') {
                                    if (data.bloqueado) {
                                        Swal.fire({
                                            icon: 'warning',
                                            title: '⚠️ ADVERTENCIA',
                                            html: `${data.mensaje}<br><br><strong>Se detectó una BAJA. Las operaciones PLD han sido bloqueadas.</strong>`,
                                            confirmButtonColor: '#d33'
                                        }).then(() => {
                                            location.reload();
                                        });
                                    } else {
                                        Swal.fire({
                                            icon: 'success',
                                            title: '¡Éxito!',
                                            text: data.mensaje,
                                            confirmButtonColor: '#3085d6'
                                        }).then(() => {
                                            location.reload();
                                        });
                                    }
                                } else {
                                    Swal.fire({
                                        icon: 'error',
                                        title: 'Error',
                                        text: data.message || 'Error desconocido',
                                        confirmButtonColor: '#d33'
                                    });
                                }
                            })
                            .catch(err => {
                                console.error('Error:', err);
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Error al confirmar cambios',
                                    text: err.message,
                                    confirmButtonColor: '#d33'
                                });
                            });
                        }
                    });
                } else if (data.status === 'success') {
                    Swal.fire({
                        icon: 'success',
                        title: '¡Éxito!',
                        text: data.mensaje,
                        confirmButtonColor: '#3085d6'
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.message || 'Error desconocido',
                        confirmButtonColor: '#d33'
                    });
                }
            })
            .catch(err => {
                console.error('Error:', err);
                Swal.fire({
                    icon: 'error',
                    title: 'Error al revalidar',
                    text: err.message,
                    confirmButtonColor: '#d33'
                });
            });
        });
    }
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<?php include '../templates/footer.php'; ?>
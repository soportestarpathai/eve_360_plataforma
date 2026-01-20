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

                        <div class="mb-3">
                            <label class="form-label fw-bold">Logo Actual</label><br>
                            <img src="../<?= htmlspecialchars($config['logo_url']) ?>" alt="Logo" style="height: 50px; object-fit: contain;" class="border p-1 rounded">
                        </div>

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
                                                <?= htmlspecialchars($item['seccion']) ?> (<?= substr($item['nombre_empresa_tipo'], 0, 10) ?>...)
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
                                                    <td class="small"><?= htmlspecialchars($m['nombre_empresa_tipo']) ?></td>
                                                    <td class="text-end">
                                                        <button class="btn btn-xs btn-outline-primary border-0" 
                                                            onclick='editMenu(<?= json_encode($m) ?>)'>
                                                            <i class="fa-solid fa-pen"></i>
                                                        </button>
                                                        <form method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar este ítem?');">
                                                            <input type="hidden" name="action" value="delete_menu">
                                                            <input type="hidden" name="id_menu_delete" value="<?= $m['id_menu_access'] ?>">
                                                            <button class="btn btn-xs btn-outline-danger border-0"><i class="fa-solid fa-trash"></i></button>
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
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<?php include 'footer.php'; ?>
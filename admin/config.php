<?php include 'header.php'; ?>
<title>Configuración para Usuarios</title>

<?php
function ensureContractFolioColumns(PDO $pdo): void {
    $requiredColumns = [
        'contrato_prefijo' => "VARCHAR(20) NOT NULL DEFAULT ''",
        'contrato_siguiente' => "INT NOT NULL DEFAULT 1",
        'contrato_longitud' => "INT NOT NULL DEFAULT 6",
        'contrato_rellenar_ceros' => "TINYINT(1) NOT NULL DEFAULT 1"
    ];

    foreach ($requiredColumns as $column => $definition) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'config_empresa'
              AND COLUMN_NAME = ?
        ");
        $stmt->execute([$column]);
        $exists = (int)$stmt->fetchColumn() > 0;
        if (!$exists) {
            $pdo->exec("ALTER TABLE config_empresa ADD COLUMN {$column} {$definition}");
        }
    }
}

ensureContractFolioColumns($pdo);

// Asegurar columna subfracciones_xi y subfracciones_ii
try {
    $chk = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'config_empresa' AND COLUMN_NAME = 'subfracciones_xi'");
    if ($chk && $chk->fetchColumn() == 0) {
        $pdo->exec("ALTER TABLE config_empresa ADD COLUMN subfracciones_xi JSON DEFAULT NULL COMMENT 'Subfracciones XI (SPR) activas'");
    }
    $chk2 = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'config_empresa' AND COLUMN_NAME = 'subfracciones_ii'");
    if ($chk2 && $chk2->fetchColumn() == 0) {
        $pdo->exec("ALTER TABLE config_empresa ADD COLUMN subfracciones_ii JSON DEFAULT NULL COMMENT 'Subfracciones II activas'");
    }
    $chk3 = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'config_empresa_usuario' AND COLUMN_NAME = 'subfracciones_xi'");
    if ($chk3 && $chk3->fetchColumn() == 0) {
        $pdo->exec("ALTER TABLE config_empresa_usuario ADD COLUMN subfracciones_xi JSON DEFAULT NULL");
    }
    $chk4 = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'config_empresa_usuario' AND COLUMN_NAME = 'subfracciones_ii'");
    if ($chk4 && $chk4->fetchColumn() == 0) {
        $pdo->exec("ALTER TABLE config_empresa_usuario ADD COLUMN subfracciones_ii JSON DEFAULT NULL");
    }
} catch (Exception $e) { /* ignorar */ }

$id_usuario_seleccionado = (int)($_GET['id_usuario'] ?? 0);

// --- ACTIONS HANDLER ---
$config_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. SAVE GENERAL CONFIGURATION
    if (isset($_POST['action']) && $_POST['action'] === 'save_config') {
        $id_usuario_config = (int)($_POST['id_usuario_config'] ?? 0);
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
                $newFileName = 'logo_company_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $fileExtension;
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
            $contratoPrefijo = trim((string)($_POST['contrato_prefijo'] ?? ''));
            $contratoSiguiente = max(1, (int)($_POST['contrato_siguiente'] ?? 1));
            $contratoLongitud = (int)($_POST['contrato_longitud'] ?? 6);
            $contratoRellenarCeros = isset($_POST['contrato_rellenar_ceros']) ? 1 : 0;
            if ($contratoLongitud < 1) {
                $contratoLongitud = 1;
            } elseif ($contratoLongitud > 12) {
                $contratoLongitud = 12;
            }

            if ($id_tipo_empresa == 1) {
                // If it is vulnerable, use the selected ID
                $id_vulnerable = $_POST['id_vulnerable'] ?? 0;
            }

            if ($id_usuario_config > 0) {
                // Guardar en config_empresa_usuario (por usuario)
                $stmt = $pdo->prepare("
                    INSERT INTO config_empresa_usuario 
                    (id_usuario, nombre_empresa, logo_url, color_primario, max_usuarios, max_busquedas_api, id_tipo_empresa, id_vulnerable, contrato_prefijo, contrato_siguiente, contrato_longitud, contrato_rellenar_ceros)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                    nombre_empresa = VALUES(nombre_empresa), logo_url = VALUES(logo_url), color_primario = VALUES(color_primario),
                    max_usuarios = VALUES(max_usuarios), max_busquedas_api = VALUES(max_busquedas_api),
                    id_tipo_empresa = VALUES(id_tipo_empresa), id_vulnerable = VALUES(id_vulnerable),
                    contrato_prefijo = VALUES(contrato_prefijo), contrato_siguiente = VALUES(contrato_siguiente),
                    contrato_longitud = VALUES(contrato_longitud), contrato_rellenar_ceros = VALUES(contrato_rellenar_ceros)
                ");
                $stmt->execute([
                    $id_usuario_config, $_POST['nombre_empresa'], $logoPath, $_POST['color_primario'],
                    $_POST['max_usuarios'], $_POST['max_busquedas_api'],
                    $id_tipo_empresa, $id_vulnerable,
                    $contratoPrefijo, $contratoSiguiente, $contratoLongitud, $contratoRellenarCeros
                ]);
            } else {
                // Guardar en config_empresa (global)
                $stmt = $pdo->prepare("
                    UPDATE config_empresa SET 
                        nombre_empresa = ?, logo_url = ?, color_primario = ?, 
                        max_usuarios = ?, max_busquedas_api = ?, 
                        id_tipo_empresa = ?, id_vulnerable = ?,
                        contrato_prefijo = ?, contrato_siguiente = ?, contrato_longitud = ?, contrato_rellenar_ceros = ?
                    WHERE id_config = 1
                ");
                $stmt->execute([
                    $_POST['nombre_empresa'], $logoPath, $_POST['color_primario'],
                    $_POST['max_usuarios'], $_POST['max_busquedas_api'],
                    $id_tipo_empresa, $id_vulnerable,
                    $contratoPrefijo, $contratoSiguiente, $contratoLongitud, $contratoRellenarCeros
                ]);
            }
            $config_message = '<div class="config-alert config-alert-success"><i class="fa-solid fa-check-circle me-2"></i>Configuración actualizada.</div>';
        } catch (Exception $e) {
            $config_message = '<div class="config-alert config-alert-danger"><i class="fa-solid fa-xmark-circle me-2"></i>Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
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
                $config_message = '<div class="config-alert config-alert-success"><i class="fa-solid fa-check-circle me-2"></i>Elemento de menú actualizado.</div>';
            } else {
                $stmt = $pdo->prepare("INSERT INTO menu_access (id_tipo_empresa, seccion, icon, file_path, id_parent) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$id_tipo, $seccion, $icon, $file, $parent]);
                $config_message = '<div class="config-alert config-alert-success"><i class="fa-solid fa-check-circle me-2"></i>Nuevo elemento agregado al menú.</div>';
            }
        } catch (Exception $e) {
            $config_message = '<div class="config-alert config-alert-danger"><i class="fa-solid fa-xmark-circle me-2"></i>Error al guardar menú: ' . htmlspecialchars($e->getMessage()) . '</div>';
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
            
            $config_message = '<div class="config-alert config-alert-warning"><i class="fa-solid fa-check-circle me-2"></i>Elemento eliminado. Los submenús asociados (si existían) ahora son elementos principales.</div>';

        } catch (Exception $e) {
            $pdo->rollBack();
            $config_message = '<div class="config-alert config-alert-danger"><i class="fa-solid fa-xmark-circle me-2"></i>Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }

    // 4. TOGGLE MENU VISIBILITY POR USUARIO
    if (isset($_POST['action']) && $_POST['action'] === 'toggle_menu_usuario' && $id_usuario_seleccionado > 0) {
        try {
            $id_menu = (int)$_POST['id_menu_access'];
            $activo = (int)$_POST['activo'];
            $pdo->prepare("INSERT INTO menu_access_usuario (id_usuario, id_menu_access, activo) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE activo = VALUES(activo)")
                ->execute([$id_usuario_seleccionado, $id_menu, $activo]);
            $config_message = '<div class="config-alert config-alert-success"><i class="fa-solid fa-check-circle me-2"></i>Visibilidad actualizada.</div>';
        } catch (Exception $e) {
            $config_message = '<div class="config-alert config-alert-danger"><i class="fa-solid fa-xmark-circle me-2"></i>Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }
}

// --- DATA FETCHING ---
// 1. Config (global base)
$stmt = $pdo->query("SELECT * FROM config_empresa WHERE id_config = 1");
$configGlobal = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

// 2. Config por usuario (si hay usuario seleccionado)
$config = $configGlobal;
if ($id_usuario_seleccionado > 0) {
    try {
        $stmtU = $pdo->prepare("SELECT * FROM config_empresa_usuario WHERE id_usuario = ?");
        $stmtU->execute([$id_usuario_seleccionado]);
        $configU = $stmtU->fetch(PDO::FETCH_ASSOC);
        if ($configU) {
            foreach (['nombre_empresa','logo_url','color_primario','max_usuarios','max_busquedas_api','id_tipo_empresa','id_vulnerable','contrato_prefijo','contrato_siguiente','contrato_longitud','contrato_rellenar_ceros','folio_patron_pld','estatus_patron_pld','fecha_revalidacion_patron','fracciones_activas','subfracciones_xi','subfracciones_ii','no_habilitado_pld'] as $k) {
                if (isset($configU[$k]) && $configU[$k] !== null) {
                    $config[$k] = $configU[$k];
                }
            }
        }
    } catch (Exception $e) { /* tabla no existe aún */ }
}

// Ensure config has default values to prevent null errors
$config['nombre_empresa'] = $config['nombre_empresa'] ?? '';
$config['logo_url'] = $config['logo_url'] ?? '';
$config['color_primario'] = $config['color_primario'] ?? '#0d6efd';
$config['max_usuarios'] = $config['max_usuarios'] ?? 10;
$config['max_busquedas_api'] = $config['max_busquedas_api'] ?? 500;
$config['id_tipo_empresa'] = $config['id_tipo_empresa'] ?? 1;
$config['id_vulnerable'] = $config['id_vulnerable'] ?? 0;
$config['contrato_prefijo'] = $config['contrato_prefijo'] ?? '';
$config['contrato_siguiente'] = $config['contrato_siguiente'] ?? 1;
$config['contrato_longitud'] = $config['contrato_longitud'] ?? 6;
$config['contrato_rellenar_ceros'] = $config['contrato_rellenar_ceros'] ?? 1;

// 2. Company Types
$stmtTypes = $pdo->query("SELECT * FROM cat_tipo_empresa ORDER BY id_tipo_empresa ASC");
$companyTypes = $stmtTypes->fetchAll(PDO::FETCH_ASSOC);

// 3. Vulnerable Activities Catalog (NEW)
$stmtVuln = $pdo->query("SELECT * FROM cat_vulnerables ORDER BY nombre ASC");
$vulnerables = $stmtVuln->fetchAll(PDO::FETCH_ASSOC);

// 4. Menu Items
$menuVisibility = [];
if ($id_usuario_seleccionado > 0) {
    try {
        $stmtM = $pdo->prepare("SELECT id_menu_access, activo FROM menu_access_usuario WHERE id_usuario = ?");
        $stmtM->execute([$id_usuario_seleccionado]);
        while ($r = $stmtM->fetch(PDO::FETCH_ASSOC)) $menuVisibility[(int)$r['id_menu_access']] = (int)$r['activo'];
    } catch (Exception $e) { /* tabla no existe */ }
}
$stmtMenu = $pdo->query("
    SELECT m.*, t.nombre as nombre_empresa_tipo 
    FROM menu_access m 
    LEFT JOIN cat_tipo_empresa t ON m.id_tipo_empresa = t.id_tipo_empresa 
    ORDER BY m.id_tipo_empresa ASC, m.id_parent ASC, m.id_menu_access ASC
");
$menuItems = $stmtMenu->fetchAll(PDO::FETCH_ASSOC);

// 5. Lista de usuarios para selector
$stmtUsuarios = $pdo->prepare("
    SELECT id_usuario, nombre, login_user
    FROM usuarios
    WHERE id_status_usuario = 1
    ORDER BY nombre ASC
");
$stmtUsuarios->execute();
$listaUsuarios = $stmtUsuarios->fetchAll(PDO::FETCH_ASSOC);
?>
<style>
    body { background: linear-gradient(135deg, #f0f4f8 0%, #e8f0f5 50%, #f5f9fc 100%); min-height: 100vh; }
    
    /* Alerts */
    .config-page .config-alerts { margin-bottom: 1.5rem; }
    .config-page .config-alert {
        padding: 1rem 1.25rem; border-radius: 14px; font-weight: 500; display: flex; align-items: center;
        box-shadow: 0 4px 16px rgba(0,0,0,0.08); border: none;
    }
    .config-page .config-alert-success { background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%); color: #065f46; }
    .config-page .config-alert-danger { background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%); color: #991b1b; }
    .config-page .config-alert-warning { background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%); color: #92400e; }
    
    /* Welcome */
    .config-page .config-welcome {
        background: linear-gradient(135deg, #0B3C8A 0%, #0B486B 100%);
        border-radius: 20px; padding: 1.75rem 2rem; margin-bottom: 1.5rem; color: #fff;
        box-shadow: 0 12px 40px rgba(11, 60, 138, 0.25);
    }
    .config-page .config-welcome h1 { font-size: 1.5rem; font-weight: 800; margin: 0; letter-spacing: -0.02em; }
    .config-page .config-welcome p { margin: 0.35rem 0 0; opacity: 0.92; font-size: 0.9rem; }
    
    /* Selector */
    .config-page .config-selector { border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.06); border: 1px solid rgba(0,0,0,0.04); overflow: hidden; }
    .config-page .config-selector .card-header { background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); color: #fff; border: none; padding: 1rem 1.25rem; font-weight: 600; font-size: 0.95rem; }
    .config-page .config-selector .form-select { border-radius: 10px; border: 2px solid #e2e8f0; padding: 0.5rem 1rem; }
    
    /* Tabs */
    .config-page .nav-tabs { border-bottom: 2px solid #e2e8f0; gap: 0.5rem; padding-bottom: 0; }
    .config-page .nav-tabs .nav-link {
        border: none; border-radius: 12px 12px 0 0; padding: 0.85rem 1.35rem;
        color: #64748b; font-weight: 600; background: transparent; transition: all 0.2s;
    }
    .config-page .nav-tabs .nav-link:hover { color: #0B3C8A; background: rgba(11, 60, 138, 0.08); }
    .config-page .nav-tabs .nav-link.active { background: linear-gradient(135deg, #0B3C8A 0%, #0B486B 100%); color: #fff; }
    .config-page .tab-content { padding-top: 1.5rem; }
    
    /* Cards */
    .config-page .config-card {
        border-radius: 16px; overflow: hidden; border: none;
        box-shadow: 0 4px 20px rgba(0,0,0,0.06); border: 1px solid rgba(0,0,0,0.04);
    }
    .config-page .config-card .card-header {
        background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
        color: #fff; border: none; padding: 1rem 1.25rem; font-weight: 600; font-size: 0.95rem;
    }
    .config-page .config-card .card-body { padding: 1.5rem; }
    .config-page .config-card .form-control, .config-page .config-card .form-select,
    .config-page .config-card .form-control-sm, .config-page .config-card .form-select-sm {
        border-radius: 10px; border: 2px solid #e2e8f0; transition: border-color 0.2s, box-shadow 0.2s;
    }
    .config-page .config-card .form-control, .config-page .config-card .form-select { padding: 0.5rem 1rem; }
    .config-page .config-card input[type="file"] { padding: 0.4rem 0.8rem; }
    .config-page .config-card .form-control:focus, .config-page .config-card .form-select:focus,
    .config-page .config-card .form-control-sm:focus, .config-page .config-card .form-select-sm:focus {
        border-color: #0B3C8A; box-shadow: 0 0 0 3px rgba(11,60,138,0.15);
    }
    .config-page .config-card .form-control-color { height: 44px; border-radius: 10px; cursor: pointer; padding: 4px; }
    .config-page .config-card .form-label { font-weight: 600; color: #334155; }
    .config-page .config-card .form-check-input { width: 1.15em; height: 1.15em; margin-top: 0.15em; }
    .config-page .config-card .form-check-input:checked { background-color: #0B3C8A; border-color: #0B3C8A; }
    .config-page .section-title { color: #0B3C8A; font-weight: 700; margin-bottom: 1rem; font-size: 1rem; display: flex; align-items: center; }
    .config-page .card-header.bg-primary { background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%) !important; }
    .config-page .card-header.bg-info { background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%) !important; }
    
    /* Section separators */
    .config-page .config-card hr { border: none; border-top: 2px solid #e2e8f0; margin: 1.75rem 0; opacity: 0.8; }
    
    /* Vulnerable box */
    .config-page .config-vulnerable-box {
        background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%); border: 2px solid #f59e0b;
        border-radius: 12px; padding: 1rem 1.25rem; margin-bottom: 1rem;
    }
    .config-page .config-vulnerable-box .form-select { border-color: #f59e0b; }
    
    /* Logo preview */
    .config-page .config-logo-preview { display: inline-block; padding: 12px; background: #f8fafc; border: 2px dashed #e2e8f0; border-radius: 12px; }
    .config-page .config-logo-preview img { border-radius: 8px; }
    .config-page .config-folio-preview code { font-size: 0.95rem; }
    .config-page .config-status-item { padding: 0.75rem 0; border-bottom: 1px solid #f1f5f9; }
    .config-page .config-status-item:last-of-type { border-bottom: none; }
    
    /* Badges */
    .config-page .badge { padding: 0.4em 0.75em; font-weight: 600; border-radius: 8px; }
    .config-page .badge.bg-success { background: linear-gradient(135deg, #059669 0%, #10b981 100%) !important; }
    .config-page .badge.bg-danger { background: linear-gradient(135deg, #dc2626 0%, #ef4444 100%) !important; }
    .config-page .badge.bg-warning { background: linear-gradient(135deg, #f59e0b 0%, #fbbf24 100%) !important; color: #1f2937 !important; }
    .config-page .badge.bg-info { background: linear-gradient(135deg, #0284c7 0%, #0ea5e9 100%) !important; }
    
    /* Tables */
    .config-page .table { border-collapse: separate; border-spacing: 0; }
    .config-page .table thead th {
        background: #f1f5f9 !important; color: #475569; font-weight: 600; font-size: 0.7rem; text-transform: uppercase;
        padding: 0.9rem 1rem; border-bottom: 2px solid #e2e8f0; letter-spacing: 0.5px;
    }
    .config-page .table tbody td { padding: 0.9rem 1rem; vertical-align: middle; }
    .config-page .table tbody tr { transition: background 0.15s; }
    .config-page .table tbody tr:hover { background: #f8fafc !important; }
    .config-page .table-responsive { border-radius: 0 0 12px 12px; overflow: hidden; }
    .config-page .table tbody tr.table-warning { background: #fffbeb !important; }
    
    /* Empty state */
    .config-page .table td.text-center.text-muted { padding: 3rem 2rem !important; font-size: 0.95rem; }
    
    /* Info box Responsables */
    .config-page .config-info-box {
        background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%); border-left: 4px solid #0B3C8A;
        border-radius: 0 12px 12px 0; padding: 1rem 1.25rem; margin-bottom: 1.5rem; color: #1e40af;
    }
    
    /* Buttons */
    .config-page .btn { border-radius: 10px; font-weight: 600; padding: 0.5rem 1.25rem; transition: all 0.2s; }
    .config-page .btn-primary { background: linear-gradient(135deg, #0B3C8A 0%, #0B486B 100%); border: none; }
    .config-page .btn-primary:hover { opacity: 0.92; transform: translateY(-1px); }
    .config-page .btn-success { background: linear-gradient(135deg, #059669 0%, #10b981 100%); border: none; color: #fff; }
    .config-page .btn-success:hover { opacity: 0.92; }
    .config-page .btn-warning { background: linear-gradient(135deg, #f59e0b 0%, #fbbf24 100%); border: none; color: #1f2937; }
    .config-page .btn-warning:hover { opacity: 0.92; color: #1f2937; }
    .config-page .btn-secondary { background: #64748b; border: none; color: #fff; }
    .config-page .btn-outline-secondary { border: 2px solid #e2e8f0; color: #64748b; }
    .config-page .btn-outline-secondary:hover { background: #f8fafc; border-color: #cbd5e1; }
    .config-page .btn-sm { padding: 0.35rem 0.9rem; font-size: 0.85rem; }
    
    /* Input groups */
    .config-page .input-group-text { border-radius: 10px 0 0 10px; border: 2px solid #e2e8f0; background: #f8fafc; }
    .config-page .input-group .form-control { border-radius: 0 10px 10px 0; }
    
    /* Modal */
    #designarResponsableModal .modal-content { border-radius: 16px; border: none; box-shadow: 0 24px 48px rgba(0,0,0,0.15); overflow: hidden; }
    #designarResponsableModal .modal-header { background: linear-gradient(135deg, #0B3C8A 0%, #0B486B 100%); color: #fff; border: none; padding: 1.25rem; }
    #designarResponsableModal .modal-header .btn-close { filter: brightness(0) invert(1); }
    #designarResponsableModal .modal-body { padding: 1.5rem; }
    #designarResponsableModal .modal-footer { padding: 1rem 1.25rem; border-top: 1px solid #e2e8f0; }
#designarResponsableModal .form-select, #designarResponsableModal .form-control { border-radius: 10px; border: 2px solid #e2e8f0; }
#designarResponsableModal .form-select:focus, #designarResponsableModal textarea:focus { border-color: #0B3C8A; box-shadow: 0 0 0 3px rgba(11,60,138,0.15); }
    
    /* Menu table actions */
    .config-page .btn-outline-primary { border-radius: 8px; }
    .config-page .btn-outline-danger { border-radius: 8px; }
</style>

<div class="config-page container mt-4 mb-5">
    <?php if ($config_message): ?>
    <div class="config-alerts"><?= $config_message ?></div>
    <?php endif; ?>
    <div class="config-welcome">
        <h1><i class="fa-solid fa-gears me-2"></i>Configuración para Usuarios</h1>
        <p>Identidad, límites, PLD y menús que ven los usuarios al iniciar sesión en la plataforma</p>
    </div>

    <div class="card config-selector mb-4">
        <div class="card-header py-3">
            <i class="fa-solid fa-user-gear me-2"></i> Configurar para los usuarios de:
        </div>
        <div class="card-body py-3">
            <form method="GET" action="config.php" class="row g-2 align-items-center" id="formSelectCliente">
                <div class="col-auto">
                    <label class="col-form-label fw-bold"><i class="fa-solid fa-users me-2"></i>Ámbito de usuarios:</label>
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
                    <a href="config.php" class="btn btn-outline-secondary btn-sm">Ver todos</a>
                </div>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <?php $tabActivo = $id_usuario_seleccionado > 0 ? 'responsable-pld' : 'general'; ?>
    <ul class="nav nav-tabs mb-4" id="configTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link <?= $tabActivo === 'general' ? 'active' : '' ?>" id="general-tab" data-bs-toggle="tab" data-bs-target="#general" type="button" role="tab"><i class="fa-solid fa-gears me-2"></i>General</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link <?= $tabActivo === 'pld' ? 'active' : '' ?>" id="pld-tab" data-bs-toggle="tab" data-bs-target="#pld" type="button" role="tab"><i class="fa-solid fa-shield-halved me-2"></i>Padrón PLD</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link <?= $tabActivo === 'responsable-pld' ? 'active' : '' ?>" id="responsable-pld-tab" data-bs-toggle="tab" data-bs-target="#responsable-pld" type="button" role="tab"><i class="fa-solid fa-user-tie me-2"></i>Responsables PLD</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="menu-tab" data-bs-toggle="tab" data-bs-target="#menu" type="button" role="tab"><i class="fa-solid fa-list-tree me-2"></i>Gestor de Menús</button>
        </li>
    </ul>

    <div class="tab-content" id="configTabsContent">
        
        <div class="tab-pane fade <?= $tabActivo === 'general' ? 'show active' : '' ?>" id="general" role="tabpanel">
            <div class="card config-card mb-4">
                <div class="card-header"><i class="fa-solid fa-gears me-2"></i>Configuración General</div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data" action="config.php<?= $id_usuario_seleccionado > 0 ? '?id_usuario='.$id_usuario_seleccionado : '' ?>">
                        <input type="hidden" name="action" value="save_config">
                        <input type="hidden" name="id_usuario_config" value="<?= (int)$id_usuario_seleccionado ?>">
                        <input type="hidden" name="existing_logo" value="<?= htmlspecialchars($config['logo_url']) ?>">

                        <h5 class="section-title"><i class="fa-solid fa-palette me-2"></i>Identidad Visual</h5>
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
                                <div class="config-vulnerable-box">
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
                            <div class="config-logo-preview">
                                <img src="../<?= htmlspecialchars($config['logo_url']) ?>" alt="Logo" style="height: 50px; object-fit: contain;" onerror="this.style.display='none'">
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Cambiar Logo</label>
                            <input type="file" name="logo_file" class="form-control" accept="image/png, image/jpeg">
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Color Primario</label>
                            <div class="d-flex align-items-center gap-3">
                                <input type="color" name="color_primario" class="form-control form-control-color" value="<?= htmlspecialchars($config['color_primario']) ?>" style="width:60px;height:44px;">
                                <span class="text-muted small"><?= htmlspecialchars($config['color_primario']) ?></span>
                            </div>
                        </div>

                        <hr>
                        <h5 class="section-title"><i class="fa-solid fa-sliders me-2"></i>Límites</h5>
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

                        <hr>
                        <h5 class="section-title"><i class="fa-solid fa-file-contract me-2"></i>Folio de Contrato</h5>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label fw-bold">Prefijo</label>
                                <input type="text" name="contrato_prefijo" id="contrato_prefijo" class="form-control" maxlength="20" value="<?= htmlspecialchars($config['contrato_prefijo']) ?>" placeholder="Ej: EVE-">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label fw-bold">Siguiente consecutivo</label>
                                <input type="number" name="contrato_siguiente" id="contrato_siguiente" class="form-control" min="1" step="1" value="<?= (int)$config['contrato_siguiente'] ?>" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label fw-bold">Longitud numérica</label>
                                <input type="number" name="contrato_longitud" id="contrato_longitud" class="form-control" min="1" max="12" step="1" value="<?= (int)$config['contrato_longitud'] ?>" required>
                            </div>
                            <div class="col-md-12 mb-2">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="contrato_rellenar_ceros" name="contrato_rellenar_ceros" value="1" <?= ((int)$config['contrato_rellenar_ceros'] === 1) ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-bold" for="contrato_rellenar_ceros">
                                        Rellenar con ceros a la izquierda (ej. 000123)
                                    </label>
                                </div>
                            </div>
                            <div class="col-12">
                                <?php
                                $previewBase = max(1, (int)$config['contrato_siguiente']);
                                $previewLen = max(1, min(12, (int)$config['contrato_longitud']));
                                $previewSeq = ((int)$config['contrato_rellenar_ceros'] === 1)
                                    ? str_pad((string)$previewBase, $previewLen, '0', STR_PAD_LEFT)
                                    : (string)$previewBase;
                                $previewFolio = (string)($config['contrato_prefijo'] ?? '') . $previewSeq;
                                ?>
                                <div class="config-folio-preview mt-1">
                                    <span class="text-muted small">Vista previa:</span>
                                    <code id="contractPreview" class="ms-2 px-2 py-1 rounded" style="background:#f1f5f9;color:#0B3C8A;font-weight:600;"><?= htmlspecialchars($previewFolio) ?></code>
                                </div>
                            </div>
                        </div>

                        <div class="text-end pt-2">
                            <button type="submit" class="btn btn-primary px-4 py-2">
                                <i class="fa-solid fa-save me-2"></i>Guardar Configuración
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- TAB: Padrón PLD (VAL-PLD-001 y VAL-PLD-002) -->
        <div class="tab-pane fade <?= $tabActivo === 'pld' ? 'show active' : '' ?>" id="pld" role="tabpanel">
            <?php
            require_once __DIR__ . '/../config/pld_validation.php';
            require_once __DIR__ . '/../config/pld_revalidation.php';
            
            $pldValidation = validatePatronPLD($pdo, null, $id_usuario_seleccionado);
            $revalidationStatus = checkRevalidationDue($pdo, $id_usuario_seleccionado);
            ?>
            
            <div class="row">
                <!-- Estado Actual -->
                <div class="col-md-6 mb-4">
                    <div class="card config-card">
                        <div class="card-header bg-primary">
                            <i class="fa-solid fa-shield-halved me-2"></i>Estado del Padrón PLD
                        </div>
                        <div class="card-body">
                            <div class="config-status-item mb-4">
                                <label class="form-label fw-bold small text-uppercase text-muted">Estatus de Habilitación</label>
                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                    <?php if ($pldValidation['habilitado']): ?>
                                        <span class="badge bg-success fs-6 px-3 py-2">
                                            <i class="fa-solid fa-check-circle me-1"></i>HABILITADO
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-danger fs-6 px-3 py-2">
                                            <i class="fa-solid fa-xmark-circle me-1"></i>NO HABILITADO
                                        </span>
                                    <?php endif; ?>
                                    <small class="text-muted"><?= htmlspecialchars($pldValidation['razon'] ?? '') ?></small>
                                </div>
                            </div>
                            
                            <div class="config-status-item mb-3">
                                <label class="form-label fw-bold small text-uppercase text-muted">Revalidación Periódica</label>
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
                                <small class="text-muted d-block mt-1"><?= htmlspecialchars($revalidationStatus['mensaje'] ?? '') ?></small>
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
                    <div class="card config-card">
                        <div class="card-header bg-info">
                            <i class="fa-solid fa-edit me-2"></i>Configurar Padrón PLD
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
                                    <?php
                                    $fraccionesActivasConfig = [];
                                    $frRaw = trim((string)($config['fracciones_activas'] ?? ''));
                                    if ($frRaw !== '') {
                                        $dec = json_decode($frRaw, true);
                                        if (is_array($dec)) {
                                            $fraccionesActivasConfig = $dec;
                                        } else {
                                            $fraccionesActivasConfig = array_map('trim', explode(',', $frRaw));
                                        }
                                    }
                                    $fraccionesActivasConfig = array_values(array_unique(array_filter(array_map(function ($f) {
                                        $s = trim((string)$f);
                                        if ($s === 'XII') return 'XI';
                                        return $s;
                                    }, $fraccionesActivasConfig))));

                                    // Opciones dinámicas desde catálogo de vulnerables + configuración actual.
                                    $fraccionesOpciones = [];
                                    foreach (($vulnerables ?? []) as $vuln) {
                                        $f = trim((string)($vuln['fraccion'] ?? ''));
                                        if ($f === 'XII') $f = 'XI';
                                        if ($f !== '') $fraccionesOpciones[$f] = $f;
                                    }
                                    foreach ($fraccionesActivasConfig as $fCfg) {
                                        $f = trim((string)$fCfg);
                                        if ($f === 'XII') $f = 'XI';
                                        if ($f !== '') $fraccionesOpciones[$f] = $f;
                                    }

                                    // Orden recomendado de fracciones implementadas.
                                    // Siempre incluirlas para evitar que desaparezcan del panel
                                    // cuando el catálogo venga incompleto temporalmente.
                                    $ordenPreferido = ['II', 'V', 'V Bis', 'VI', 'VIII', 'XI', 'XIII', 'XVI'];
                                    foreach ($ordenPreferido as $fo) {
                                        $fraccionesOpciones[$fo] = $fo;
                                    }
                                    $fraccionesOpcionesLista = array_values(array_unique(array_keys($fraccionesOpciones)));
                                    usort($fraccionesOpcionesLista, function ($a, $b) use ($ordenPreferido) {
                                        $ia = array_search($a, $ordenPreferido, true);
                                        $ib = array_search($b, $ordenPreferido, true);
                                        $ia = ($ia === false) ? 999 : $ia;
                                        $ib = ($ib === false) ? 999 : $ib;
                                        if ($ia === $ib) return strcmp($a, $b);
                                        return $ia <=> $ib;
                                    });
                                    ?>
                                    <input type="hidden" id="fraccionesActivas" value="<?= htmlspecialchars(json_encode($fraccionesActivasConfig, JSON_UNESCAPED_UNICODE)) ?>">
                                    <div id="fraccionesActivasChecks" class="row g-2">
                                        <?php foreach ($fraccionesOpcionesLista as $claveFrac): ?>
                                        <div class="col-md-4">
                                            <div class="form-check">
                                                <input
                                                    class="form-check-input fraccion-activa-check"
                                                    type="checkbox"
                                                    value="<?= htmlspecialchars($claveFrac) ?>"
                                                    id="frac_activa_<?= htmlspecialchars(str_replace(' ', '_', $claveFrac)) ?>"
                                                    <?= in_array($claveFrac, $fraccionesActivasConfig, true) ? 'checked' : '' ?>
                                                >
                                                <label class="form-check-label" for="frac_activa_<?= htmlspecialchars(str_replace(' ', '_', $claveFrac)) ?>">
                                                    <?= htmlspecialchars($claveFrac) ?>
                                                </label>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <small class="form-text text-muted">Seleccione las fracciones habilitadas. Se guardan automáticamente como arreglo JSON.</small>
                                </div>

                                <div id="subfraccionesXiSection" class="mb-3 mt-3 nested-card" style="display:none;">
                                    <label class="form-label fw-bold"><i class="fa-solid fa-layer-group me-2"></i>Subfracciones XI (SPR)</label>
                                    <p class="small text-muted mb-2">Seleccione las actividades de Servicios Profesionales que aplican a su empresa:</p>
                                    <div class="row g-2">
                                        <?php
                                        require_once __DIR__ . '/../config/spr_catalogos.php';
                                        $tipoAct = $SPR_CATALOGOS['tipo_actividad'] ?? [];
                                        $subfraccionesConfig = [];
                                        if (!empty($config['subfracciones_xi'])) {
                                            $dec = json_decode($config['subfracciones_xi'], true);
                                            if (is_array($dec)) $subfraccionesConfig = $dec;
                                        }
                                        foreach ($tipoAct as $clave => $etiqueta):
                                            $chk = in_array($clave, $subfraccionesConfig) ? ' checked' : '';
                                        ?>
                                        <div class="col-md-6"><div class="form-check">
                                            <input class="form-check-input subfraccion-xi-check" type="checkbox" value="<?= htmlspecialchars($clave) ?>" id="subf_<?= htmlspecialchars($clave) ?>"<?= $chk ?>>
                                            <label class="form-check-label" for="subf_<?= htmlspecialchars($clave) ?>"><?= htmlspecialchars($etiqueta) ?></label>
                                        </div></div>
                                        <?php endforeach; ?>
                                    </div>
                                    <small class="form-text text-muted d-block mt-2">Si no selecciona ninguna, se mostrarán todas en el formulario SPR.</small>
                                </div>

                                <div id="subfraccionesIiSection" class="mb-3 mt-3 nested-card" style="display:none;">
                                    <label class="form-label fw-bold"><i class="fa-solid fa-credit-card me-2"></i>Subfracciones II</label>
                                    <p class="small text-muted mb-2">Seleccione las subfracciones de Fracción II habilitadas para su empresa:</p>
                                    <div class="row g-2">
                                        <?php
                                        require_once __DIR__ . '/../config/pld_fraccion_ii.php';
                                        $subfraccionesIIConfig = [];
                                        if (!empty($config['subfracciones_ii'])) {
                                            $dec = json_decode($config['subfracciones_ii'], true);
                                            if (is_array($dec)) $subfraccionesIIConfig = $dec;
                                        }
                                        $subfraccionesIIDef = function_exists('getSubfraccionesIIDefinition') ? getSubfraccionesIIDefinition() : [];
                                        foreach ($subfraccionesIIDef as $clave => $meta):
                                            $etiqueta = is_array($meta) ? ($meta['nombre'] ?? $clave) : $clave;
                                            $chk = in_array($clave, $subfraccionesIIConfig, true) ? ' checked' : '';
                                        ?>
                                        <div class="col-md-6"><div class="form-check">
                                            <input class="form-check-input subfraccion-ii-check" type="checkbox" value="<?= htmlspecialchars($clave) ?>" id="subf_ii_<?= htmlspecialchars($clave) ?>"<?= $chk ?>>
                                            <label class="form-check-label" for="subf_ii_<?= htmlspecialchars($clave) ?>"><?= htmlspecialchars($etiqueta) ?></label>
                                        </div></div>
                                        <?php endforeach; ?>
                                    </div>
                                    <small class="form-text text-muted d-block mt-2">Si no selecciona ninguna, se permitirán todas en el formulario de Fracción II.</small>
                                </div>
                                
                                <div class="d-flex flex-wrap gap-2 mt-3">
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

        <div class="tab-pane fade <?= $tabActivo === 'responsable-pld' ? 'show active' : '' ?>" id="responsable-pld" role="tabpanel">
            <?php
            require_once __DIR__ . '/../config/pld_responsable_validation.php';
            
            // Obtener lista de clientes morales y fideicomisos (filtrado por usuario seleccionado: los que ese usuario registró)
            $sqlClientes = "
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
            ";
            if ($id_usuario_seleccionado > 0) {
                $sqlClientes .= " AND c.id_usuario = ?";
            }
            $sqlClientes .= " ORDER BY c.fecha_apertura DESC";
            $stmt = $pdo->prepare($sqlClientes);
            if ($id_usuario_seleccionado > 0) {
                $stmt->execute([$id_usuario_seleccionado]);
            } else {
                $stmt->execute();
            }
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
            
            <div class="card config-card">
                <div class="card-header">
                    <i class="fa-solid fa-user-tie me-2"></i>Gestión de Responsables PLD
                </div>
                <div class="card-body">
                    <div class="config-info-box">
                        <i class="fa-solid fa-info-circle me-2"></i>
                        Las personas morales y fideicomisos deben tener un responsable PLD designado. 
                        Seleccione el ámbito para ver o designar el responsable que aplica a los usuarios.
                    </div>
                    
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
                        <div class="modal-header">
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

        <div class="tab-pane fade <?= $tabActivo === 'menu' ? 'show active' : '' ?>" id="menu" role="tabpanel">
            <?php if ($id_usuario_seleccionado > 0): ?>
            <div class="alert alert-info mb-3">
                <i class="fa-solid fa-user me-2"></i>Configurando visibilidad de menú para el usuario seleccionado. Cada ítem puede mostrarse u ocultarse.
            </div>
            <?php endif; ?>
            <div class="row">
                <div class="col-md-4" style="<?= $id_usuario_seleccionado > 0 ? 'display:none;' : '' ?>">
                    <div class="card shadow-sm mb-3">
                        <div class="card-header bg-light fw-bold">Agregar / Editar Ítem</div>
                        <div class="card-body">
                            <form method="POST" action="config.php<?= $id_usuario_seleccionado > 0 ? '?id_usuario='.$id_usuario_seleccionado : '' ?>">
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
                                            <?php if ($id_usuario_seleccionado > 0): ?>
                                            <th class="text-center">Visible</th>
                                            <?php endif; ?>
                                            <th class="text-end">Acción</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if(empty($menuItems)): ?>
                                            <tr><td colspan="<?= $id_usuario_seleccionado > 0 ? 7 : 6 ?>" class="text-center text-muted p-3">No hay elementos configurados.</td></tr>
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
                                                    <?php if ($id_usuario_seleccionado > 0):
                                                        $activo = isset($menuVisibility[(int)$m['id_menu_access']]) ? $menuVisibility[(int)$m['id_menu_access']] : 1;
                                                    ?>
                                                    <td class="text-center">
                                                        <form method="POST" action="config.php?id_usuario=<?= $id_usuario_seleccionado ?>" class="d-inline">
                                                            <input type="hidden" name="action" value="toggle_menu_usuario">
                                                            <input type="hidden" name="id_menu_access" value="<?= (int)$m['id_menu_access'] ?>">
                                                            <input type="hidden" name="activo" value="<?= $activo ? 0 : 1 ?>">
                                                            <?php if ($activo): ?>
                                                            <button type="submit" class="btn btn-success btn-sm px-2"><i class="fa-solid fa-eye me-1"></i>Sí</button>
                                                            <?php else: ?>
                                                            <button type="submit" class="btn btn-secondary btn-sm px-2"><i class="fa-solid fa-eye-slash me-1"></i>No</button>
                                                            <?php endif; ?>
                                                        </form>
                                                    </td>
                                                    <?php endif; ?>
                                                    <td class="text-end">
                                                        <?php if ($id_usuario_seleccionado <= 0): ?>
                                                        <button class="btn btn-xs btn-outline-primary border-0" 
                                                            onclick='editMenu(<?= json_encode($m) ?>)'>
                                                            <i class="fa-solid fa-pen"></i>
                                                        </button>
                                                        <form method="POST" class="d-inline delete-menu-form" action="config.php">
                                                            <input type="hidden" name="action" value="delete_menu">
                                                            <input type="hidden" name="id_menu_delete" value="<?= $m['id_menu_access'] ?>">
                                                            <button type="button" class="btn btn-xs btn-outline-danger border-0" onclick="confirmDeleteMenu(this)"><i class="fa-solid fa-trash"></i></button>
                                                        </form>
                                                        <?php endif; ?>
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
    window.idUsuarioConfig = <?= (int)$id_usuario_seleccionado ?>;
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

    function updateContractPreview() {
        const prefix = (document.getElementById('contrato_prefijo')?.value || '').trim();
        const nextRaw = parseInt(document.getElementById('contrato_siguiente')?.value || '1', 10);
        const lengthRaw = parseInt(document.getElementById('contrato_longitud')?.value || '6', 10);
        const fillZeros = document.getElementById('contrato_rellenar_ceros')?.checked === true;
        const next = Number.isFinite(nextRaw) && nextRaw > 0 ? nextRaw : 1;
        const length = Number.isFinite(lengthRaw) ? Math.max(1, Math.min(12, lengthRaw)) : 6;
        const sequence = fillZeros ? String(next).padStart(length, '0') : String(next);
        const folio = prefix + sequence;
        const preview = document.getElementById('contractPreview');
        if (preview) preview.textContent = folio;
    }

    // Run on load to set initial state
    document.addEventListener('DOMContentLoaded', function() {
        toggleVulnerable();
        updateContractPreview();
        ['contrato_prefijo', 'contrato_siguiente', 'contrato_longitud'].forEach((id) => {
            const el = document.getElementById(id);
            if (!el) return;
            el.addEventListener('input', updateContractPreview);
        });
        const fill = document.getElementById('contrato_rellenar_ceros');
        if (fill) {
            fill.addEventListener('change', updateContractPreview);
        }
    });

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
    function normalizeFraccionValue(fraccion) {
        const s = String(fraccion || '').trim();
        if (!s) return '';
        return s === 'XII' ? 'XI' : s;
    }

    function getSelectedFraccionesActivas() {
        const selected = [];
        document.querySelectorAll('.fraccion-activa-check:checked').forEach((cb) => {
            const frac = normalizeFraccionValue(cb.value);
            if (frac) selected.push(frac);
        });
        return Array.from(new Set(selected));
    }

    function syncFraccionesActivasHidden() {
        const hidden = document.getElementById('fraccionesActivas');
        if (!hidden) return;
        hidden.value = JSON.stringify(getSelectedFraccionesActivas());
    }

    function toggleSubfraccionesXI() {
        const sec = document.getElementById('subfraccionesXiSection');
        if (!sec) return;
        const fracciones = getSelectedFraccionesActivas();
        const tieneXI = fracciones.includes('XI');
        sec.style.display = tieneXI ? 'block' : 'none';
    }
    function toggleSubfraccionesII() {
        const sec = document.getElementById('subfraccionesIiSection');
        if (!sec) return;
        const fracciones = getSelectedFraccionesActivas();
        const tieneII = fracciones.includes('II');
        sec.style.display = tieneII ? 'block' : 'none';
    }
    (function initSubfraccionesXI() {
        const checks = document.querySelectorAll('.fraccion-activa-check');
        checks.forEach((cb) => cb.addEventListener('change', () => {
            syncFraccionesActivasHidden();
            toggleSubfraccionesXI();
            toggleSubfraccionesII();
        }));
        syncFraccionesActivasHidden();
        toggleSubfraccionesXI();
        toggleSubfraccionesII();
    })();

    function savePatronPLD() {
        const folio = document.getElementById('folioPatron').value.trim();
        const estatus = document.getElementById('estatusPatron').value;
        syncFraccionesActivasHidden();
        const fraccionesSeleccionadas = getSelectedFraccionesActivas();
        const fracciones = fraccionesSeleccionadas.length > 0 ? JSON.stringify(fraccionesSeleccionadas) : null;

        const subfraccionesChecked = [];
        document.querySelectorAll('.subfraccion-xi-check:checked').forEach(cb => subfraccionesChecked.push(cb.value));
        const subfracciones = document.getElementById('subfraccionesXiSection').style.display !== 'none' ? subfraccionesChecked : null;
        const subfraccionesIIChecked = [];
        document.querySelectorAll('.subfraccion-ii-check:checked').forEach(cb => subfraccionesIIChecked.push(cb.value));
        const subfraccionesII = document.getElementById('subfraccionesIiSection').style.display !== 'none' ? subfraccionesIIChecked : null;
        
        fetch('../api/revalidate_patron_pld.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                folio: folio || null,
                estatus: estatus || null,
                fracciones: fracciones || null,
                subfracciones_xi: subfracciones,
                subfracciones_ii: subfraccionesII,
                confirmar: true,
                id_usuario: window.idUsuarioConfig || 0
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
            syncFraccionesActivasHidden();
            const fraccionesSeleccionadas = getSelectedFraccionesActivas();
            const fracciones = fraccionesSeleccionadas.length > 0 ? JSON.stringify(fraccionesSeleccionadas) : null;
            const subf = [];
            document.querySelectorAll('.subfraccion-xi-check:checked').forEach(cb => subf.push(cb.value));
            const subfII = [];
            document.querySelectorAll('.subfraccion-ii-check:checked').forEach(cb => subfII.push(cb.value));
            
            fetch('../api/revalidate_patron_pld.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    folio: folio || null,
                    estatus: estatus || null,
                    fracciones: fracciones || null,
                    subfracciones_xi: subf,
                    subfracciones_ii: subfII,
                    confirmar: false,
                    id_usuario: window.idUsuarioConfig || 0
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
                                    subfracciones_xi: subf,
                                    subfracciones_ii: subfII,
                                    confirmar: true,
                                    id_usuario: window.idUsuarioConfig || 0
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
                                            html: `${data.mensaje}<br><br><strong>Se detectó una BAJA. Las transacciones PLD han sido bloqueadas.</strong>`,
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

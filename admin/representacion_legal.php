<?php 
// admin/representacion_legal.php
include 'header.php';
require_once '../config/pld_representacion_legal.php';

// --- ACTION HANDLER ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. SAVE REPRESENTACION
    if (isset($_POST['action']) && $_POST['action'] === 'save_representacion') {
        try {
            $id_usuario = $_POST['id_usuario'] ?? null;
            $id_cliente = !empty($_POST['id_cliente']) ? $_POST['id_cliente'] : null;
            $tipo_representacion = $_POST['tipo_representacion'] ?? null;
            $fecha_vencimiento = !empty($_POST['fecha_vencimiento']) ? $_POST['fecha_vencimiento'] : null;
            
            if (!$id_usuario || !$tipo_representacion) {
                throw new Exception("Usuario y tipo de representación son requeridos");
            }
            
            // Handle file upload
            $documento_facultades = null;
            if (isset($_FILES['documento_facultades']) && $_FILES['documento_facultades']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = '../uploads/representacion_legal/';
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                $extension = pathinfo($_FILES['documento_facultades']['name'], PATHINFO_EXTENSION);
                $cleanName = 'rep_' . $id_usuario . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
                $targetPath = $uploadDir . $cleanName;
                
                if (move_uploaded_file($_FILES['documento_facultades']['tmp_name'], $targetPath)) {
                    $documento_facultades = $targetPath;
                } else {
                    throw new Exception("Error al subir el archivo");
                }
            } else {
                // Si no hay archivo nuevo, mantener el existente
                $id_representacion = $_POST['id_representacion'] ?? null;
                if ($id_representacion) {
                    $stmt = $pdo->prepare("SELECT documento_facultades FROM usuarios_representacion_legal WHERE id_representacion = ?");
                    $stmt->execute([$id_representacion]);
                    $existente = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($existente && !empty($existente['documento_facultades'])) {
                        $documento_facultades = $existente['documento_facultades'];
                    }
                }
            }
            
            $data = [
                'id_usuario' => $id_usuario,
                'id_cliente' => $id_cliente,
                'tipo_representacion' => $tipo_representacion,
                'documento_facultades' => $documento_facultades,
                'fecha_vencimiento' => $fecha_vencimiento
            ];
            
            if (!empty($_POST['id_representacion'])) {
                $data['id_representacion'] = $_POST['id_representacion'];
            }
            
            $result = registrarRepresentacionLegal($pdo, $data);
            
            if ($result['success']) {
                echo '<div class="alert alert-success mt-3">Representación legal registrada correctamente.</div>';
            } else {
                throw new Exception($result['message']);
            }
            
        } catch (Exception $e) {
            echo '<div class="alert alert-danger mt-3">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }
    
    // 2. DELETE REPRESENTACION
    if (isset($_POST['action']) && $_POST['action'] === 'delete_representacion') {
        try {
            $id_representacion = $_POST['id_representacion'] ?? null;
            
            if (!$id_representacion) {
                throw new Exception("ID de representación requerido");
            }
            
            $stmt = $pdo->prepare("UPDATE usuarios_representacion_legal SET id_status = 0 WHERE id_representacion = ?");
            $stmt->execute([$id_representacion]);
            
            echo '<div class="alert alert-warning mt-3">Representación legal eliminada.</div>';
            
        } catch (Exception $e) {
            echo '<div class="alert alert-danger mt-3">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }
    
    // 3. VALIDATE USER
    if (isset($_POST['action']) && $_POST['action'] === 'validate_user') {
        try {
            $id_usuario = $_POST['id_usuario'] ?? null;
            $id_cliente = !empty($_POST['id_cliente']) ? $_POST['id_cliente'] : null;
            
            if (!$id_usuario) {
                throw new Exception("ID de usuario requerido");
            }
            
            $result = validateRepresentacionLegal($pdo, $id_usuario, $id_cliente);
            
            header('Content-Type: application/json');
            echo json_encode($result);
            exit;
            
        } catch (Exception $e) {
            header('Content-Type: application/json');
            echo json_encode(['valido' => false, 'error' => $e->getMessage()]);
            exit;
        }
    }
    
    // 4. GET REPRESENTACIONES
    if (isset($_POST['action']) && $_POST['action'] === 'get_representaciones') {
        $id_usuario = $_POST['id_usuario'] ?? null;
        if ($id_usuario) {
            $stmt = $pdo->prepare("
                SELECT ur.*, c.alias as cliente_alias, c.no_contrato
                FROM usuarios_representacion_legal ur
                LEFT JOIN clientes c ON ur.id_cliente = c.id_cliente
                WHERE ur.id_usuario = ? AND ur.id_status = 1
                ORDER BY ur.fecha_alta DESC
            ");
            $stmt->execute([$id_usuario]);
            $representaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
            ?>
            <div class="list-group">
                <?php if (empty($representaciones)): ?>
                    <div class="alert alert-info">No hay representaciones registradas</div>
                <?php else: ?>
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
                                    <p class="mb-1">
                                        <strong>Cliente:</strong> <?= $rep['id_cliente'] ? htmlspecialchars($rep['cliente_alias'] . ' - ' . $rep['no_contrato']) : 'General' ?><br>
                                        <strong>Fecha alta:</strong> <?= $rep['fecha_alta'] ? date('d/m/Y', strtotime($rep['fecha_alta'])) : 'N/A' ?><br>
                                        <?php if ($rep['fecha_vencimiento']): ?>
                                            <strong>Vencimiento:</strong> <?= date('d/m/Y', strtotime($rep['fecha_vencimiento'])) ?>
                                        <?php endif; ?>
                                    </p>
                                </div>
                                <div>
                                    <?php if ($tieneDoc): ?>
                                        <a href="../<?= htmlspecialchars($rep['documento_facultades']) ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                            <i class="fa-solid fa-download"></i> Ver
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <?php
            exit;
        }
    }
}

// --- FETCH DATA ---
// Obtener todos los usuarios con sus representaciones legales
$stmt = $pdo->query("
    SELECT u.id_usuario, u.nombre, u.login_user, u.id_status_usuario,
           COUNT(ur.id_representacion) as total_representaciones,
           SUM(CASE WHEN ur.documento_facultades IS NOT NULL AND ur.documento_facultades != '' THEN 1 ELSE 0 END) as con_documento,
           SUM(CASE WHEN ur.fecha_vencimiento IS NOT NULL AND ur.fecha_vencimiento < CURDATE() THEN 1 ELSE 0 END) as vencidas
    FROM usuarios u
    LEFT JOIN usuarios_representacion_legal ur ON u.id_usuario = ur.id_usuario AND ur.id_status = 1
    GROUP BY u.id_usuario, u.nombre, u.login_user, u.id_status_usuario
    ORDER BY u.nombre ASC
");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener clientes para el select
$stmtClientes = $pdo->query("
    SELECT c.id_cliente, c.alias, c.no_contrato,
           CASE 
               WHEN tp.es_fisica > 0 THEN CONCAT(cf.nombre, ' ', cf.apellido_paterno, ' ', COALESCE(cf.apellido_materno, ''))
               WHEN tp.es_moral > 0 THEN cm.razon_social
               WHEN tp.es_fideicomiso > 0 THEN CONCAT('Fideicomiso ', cfid.numero_fideicomiso)
               ELSE c.alias
           END as nombre_cliente
    FROM clientes c
    LEFT JOIN cat_tipo_persona tp ON c.id_tipo_persona = tp.id_tipo_persona
    LEFT JOIN clientes_fisicas cf ON c.id_cliente = cf.id_cliente AND tp.es_fisica > 0
    LEFT JOIN clientes_morales cm ON c.id_cliente = cm.id_cliente AND tp.es_moral > 0
    LEFT JOIN clientes_fideicomisos cfid ON c.id_cliente = cfid.id_cliente AND tp.es_fideicomiso > 0
    WHERE c.id_status = 1
    ORDER BY nombre_cliente ASC
");
$clientes = $stmtClientes->fetchAll(PDO::FETCH_ASSOC);
?>
<title>Representación Legal de Usuarios - VAL-PLD-004</title>

<div class="container mt-4 mb-5">
    <div class="card shadow-sm">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <div>
                <h5 class="mb-0"><i class="fa-solid fa-gavel me-2"></i>Representación Legal de Usuarios</h5>
                <small class="opacity-75">VAL-PLD-004: Validación de facultades documentadas</small>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Usuario</th>
                            <th>Email</th>
                            <th class="text-center">Representaciones</th>
                            <th class="text-center">Con Documento</th>
                            <th class="text-center">Estado</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): 
                            $validation = validateRepresentacionLegal($pdo, $u['id_usuario']);
                            $isValid = $validation['valido'] && !$validation['bloqueado'];
                        ?>
                            <tr>
                                <td class="fw-bold"><?= htmlspecialchars($u['nombre']) ?></td>
                                <td><?= htmlspecialchars($u['login_user']) ?></td>
                                <td class="text-center">
                                    <?php if ($u['total_representaciones'] > 0): ?>
                                        <span class="badge bg-info"><?= $u['total_representaciones'] ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">0</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($u['con_documento'] > 0): ?>
                                        <span class="badge bg-success"><?= $u['con_documento'] ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark">Sin documento</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($isValid): ?>
                                        <span class="badge bg-success"><i class="fa-solid fa-check me-1"></i>Válido</span>
                                    <?php elseif ($u['vencidas'] > 0): ?>
                                        <span class="badge bg-danger"><i class="fa-solid fa-exclamation-triangle me-1"></i>Vencido</span>
                                    <?php elseif ($u['total_representaciones'] == 0): ?>
                                        <span class="badge bg-danger"><i class="fa-solid fa-times me-1"></i>Sin registro</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark"><i class="fa-solid fa-exclamation me-1"></i>Incompleto</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-primary" onclick="openRepresentacionModal(<?= $u['id_usuario'] ?>, '<?= htmlspecialchars($u['nombre'], ENT_QUOTES) ?>')">
                                        <i class="fa-solid fa-plus me-1"></i>Agregar
                                    </button>
                                    <button class="btn btn-sm btn-info" onclick="viewRepresentaciones(<?= $u['id_usuario'] ?>)">
                                        <i class="fa-solid fa-eye me-1"></i>Ver
                                    </button>
                                    <button class="btn btn-sm btn-secondary" onclick="validateUser(<?= $u['id_usuario'] ?>)">
                                        <i class="fa-solid fa-check-circle me-1"></i>Validar
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal para Agregar/Editar Representación -->
<div class="modal fade" id="representacionModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fa-solid fa-gavel me-2"></i>Representación Legal</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data" id="representacionForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="save_representacion">
                    <input type="hidden" name="id_representacion" id="id_representacion">
                    <input type="hidden" name="id_usuario" id="modal_id_usuario">
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Usuario</label>
                        <input type="text" class="form-control" id="modal_usuario_nombre" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Tipo de Representación <span class="text-danger">*</span></label>
                        <select class="form-select" name="tipo_representacion" id="tipo_representacion" required>
                            <option value="">Seleccione...</option>
                            <option value="representante_legal">Representante Legal</option>
                            <option value="apoderado">Apoderado</option>
                            <option value="usuario_autorizado">Usuario Autorizado</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Cliente (Opcional)</label>
                        <select class="form-select" name="id_cliente" id="id_cliente">
                            <option value="">General (aplica a todos los clientes)</option>
                            <?php foreach ($clientes as $c): ?>
                                <option value="<?= $c['id_cliente'] ?>"><?= htmlspecialchars($c['nombre_cliente']) ?> - <?= htmlspecialchars($c['no_contrato']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Dejar vacío para representación general</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Documento de Facultades <span class="text-danger">*</span></label>
                        <input type="file" class="form-control" name="documento_facultades" id="documento_facultades" accept=".pdf,.jpg,.jpeg,.png">
                        <small class="text-muted">Formatos permitidos: PDF, JPG, PNG</small>
                        <div id="documento_actual" class="mt-2"></div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Fecha de Vencimiento (Opcional)</label>
                        <input type="date" class="form-control" name="fecha_vencimiento" id="fecha_vencimiento">
                        <small class="text-muted">Si el documento tiene fecha de vencimiento</small>
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

<!-- Modal para Ver Representaciones -->
<div class="modal fade" id="viewModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="fa-solid fa-list me-2"></i>Representaciones Legales</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="viewModalBody">
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Cargando...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function openRepresentacionModal(idUsuario, nombreUsuario) {
    document.getElementById('modal_id_usuario').value = idUsuario;
    document.getElementById('modal_usuario_nombre').value = nombreUsuario;
    document.getElementById('id_representacion').value = '';
    document.getElementById('representacionForm').reset();
    document.getElementById('documento_actual').innerHTML = '';
    document.getElementById('modal_id_usuario').value = idUsuario;
    document.getElementById('modal_usuario_nombre').value = nombreUsuario;
    
    const modal = new bootstrap.Modal(document.getElementById('representacionModal'));
    modal.show();
}

function viewRepresentaciones(idUsuario) {
    fetch('representacion_legal.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=get_representaciones&id_usuario=' + idUsuario
    })
    .then(response => response.text())
    .then(html => {
        document.getElementById('viewModalBody').innerHTML = html;
        const modal = new bootstrap.Modal(document.getElementById('viewModal'));
        modal.show();
    })
    .catch(error => {
        Swal.fire('Error', 'No se pudieron cargar las representaciones', 'error');
    });
}

function validateUser(idUsuario) {
    fetch('representacion_legal.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=validate_user&id_usuario=' + idUsuario
    })
    .then(response => response.json())
    .then(data => {
        if (data.valido && !data.bloqueado) {
            Swal.fire({
                icon: 'success',
                title: 'Válido',
                text: data.razon,
                html: '<p><strong>Representaciones válidas:</strong> ' + data.detalles.representaciones_validas + '</p>' +
                      '<p><strong>Tipos:</strong> ' + (data.detalles.tipos ? data.detalles.tipos.join(', ') : 'N/A') + '</p>'
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'No Válido',
                text: data.razon,
                html: '<p><strong>Razón:</strong> ' + data.razon + '</p>' +
                      '<p><strong>Tipo requerido:</strong> ' + (data.tipo_requerido || 'N/A') + '</p>'
            });
        }
    })
    .catch(error => {
        Swal.fire('Error', 'Error al validar usuario', 'error');
    });
}

</script>

<?php include '../templates/footer.php'; ?>

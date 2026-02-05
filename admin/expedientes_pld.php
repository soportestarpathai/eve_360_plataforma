<?php 
// admin/expedientes_pld.php
include 'header.php';
require_once '../config/pld_expediente.php';

// --- ACTION HANDLER ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. VALIDATE EXPEDIENTE
    if (isset($_POST['action']) && $_POST['action'] === 'validate_expediente') {
        try {
            $id_cliente = $_POST['id_cliente'] ?? null;
            
            if (!$id_cliente) {
                throw new Exception("ID de cliente requerido");
            }
            
            // Validar completitud (VAL-PLD-005)
            $resultCompleto = validateExpedienteCompleto($pdo, $id_cliente);
            
            // Validar actualización (VAL-PLD-006)
            $resultActualizacion = validateActualizacionExpediente($pdo, $id_cliente);
            
            header('Content-Type: application/json');
            echo json_encode([
                'completitud' => $resultCompleto,
                'actualizacion' => $resultActualizacion,
                'valido' => $resultCompleto['completo'] && $resultActualizacion['actualizado']
            ]);
            exit;
            
        } catch (Exception $e) {
            header('Content-Type: application/json');
            echo json_encode(['valido' => false, 'error' => $e->getMessage()]);
            exit;
        }
    }
    
    // 2. UPDATE FECHA EXPEDIENTE
    if (isset($_POST['action']) && $_POST['action'] === 'update_fecha_expediente') {
        try {
            $id_cliente = $_POST['id_cliente'] ?? null;
            
            if (!$id_cliente) {
                throw new Exception("ID de cliente requerido");
            }
            
            $result = actualizarFechaExpediente($pdo, $id_cliente);
            
            if ($result) {
                echo '<div class="alert alert-success mt-3">Fecha de expediente actualizada correctamente.</div>';
            } else {
                throw new Exception("Error al actualizar fecha");
            }
            
        } catch (Exception $e) {
            echo '<div class="alert alert-danger mt-3">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }
    
    // 3. GET DETALLES EXPEDIENTE
    if (isset($_POST['action']) && $_POST['action'] === 'get_detalles_expediente') {
        $id_cliente = $_POST['id_cliente'] ?? null;
        if ($id_cliente) {
            // Obtener datos del cliente
            $stmt = $pdo->prepare("SELECT c.*, tp.nombre as tipo_persona_nombre, tp.es_fisica, tp.es_moral, tp.es_fideicomiso
                                   FROM clientes c
                                   LEFT JOIN cat_tipo_persona tp ON c.id_tipo_persona = tp.id_tipo_persona
                                   WHERE c.id_cliente = ?");
            $stmt->execute([$id_cliente]);
            $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Obtener identificaciones
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM clientes_identificaciones WHERE id_cliente = ? AND id_status = 1");
            $stmt->execute([$id_cliente]);
            $identificaciones = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Obtener direcciones
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM clientes_direcciones WHERE id_cliente = ? AND id_status = 1");
            $stmt->execute([$id_cliente]);
            $direcciones = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Obtener contactos
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM clientes_contactos WHERE id_cliente = ? AND id_status = 1");
            $stmt->execute([$id_cliente]);
            $contactos = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Obtener documentos
            $stmt = $pdo->prepare("SELECT COUNT(*) as count, 
                                          SUM(CASE WHEN ruta IS NOT NULL AND ruta != '' THEN 1 ELSE 0 END) as con_archivo
                                   FROM clientes_documentos 
                                   WHERE id_cliente = ? AND id_status = 1");
            $stmt->execute([$id_cliente]);
            $documentos = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Validar expediente
            $resultCompleto = validateExpedienteCompleto($pdo, $id_cliente);
            $resultActualizacion = validateActualizacionExpediente($pdo, $id_cliente);
            
            ?>
            <div class="row">
                <div class="col-md-6">
                    <h6 class="text-primary mb-3"><i class="fa-solid fa-info-circle me-2"></i>Información del Cliente</h6>
                    <table class="table table-sm">
                        <tr>
                            <th>ID:</th>
                            <td><?= $cliente['id_cliente'] ?></td>
                        </tr>
                        <tr>
                            <th>Tipo:</th>
                            <td><?= htmlspecialchars($cliente['tipo_persona_nombre'] ?? 'N/A') ?></td>
                        </tr>
                        <tr>
                            <th>Alias:</th>
                            <td><?= htmlspecialchars($cliente['alias'] ?? 'N/A') ?></td>
                        </tr>
                        <tr>
                            <th>No. Contrato:</th>
                            <td><?= htmlspecialchars($cliente['no_contrato'] ?? 'N/A') ?></td>
                        </tr>
                        <tr>
                            <th>Fecha Apertura:</th>
                            <td><?= $cliente['fecha_apertura'] ? date('d/m/Y', strtotime($cliente['fecha_apertura'])) : 'N/A' ?></td>
                        </tr>
                        <tr>
                            <th>Última Actualización:</th>
                            <td>
                                <?php if ($cliente['fecha_ultima_actualizacion_expediente']): ?>
                                    <?= date('d/m/Y', strtotime($cliente['fecha_ultima_actualizacion_expediente'])) ?>
                                <?php else: ?>
                                    <span class="text-danger">Nunca actualizado</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <h6 class="text-primary mb-3"><i class="fa-solid fa-check-circle me-2"></i>Estado del Expediente</h6>
                    <div class="mb-3">
                        <strong>Completitud (VAL-PLD-005):</strong><br>
                        <?php if ($resultCompleto['completo']): ?>
                            <span class="badge bg-success"><i class="fa-solid fa-check me-1"></i>Completo</span>
                        <?php else: ?>
                            <span class="badge bg-danger"><i class="fa-solid fa-times me-1"></i>Incompleto</span>
                        <?php endif; ?>
                    </div>
                    <div class="mb-3">
                        <strong>Actualización (VAL-PLD-006):</strong><br>
                        <?php if ($resultActualizacion['actualizado']): ?>
                            <span class="badge bg-success"><i class="fa-solid fa-check me-1"></i>Actualizado</span>
                        <?php else: ?>
                            <span class="badge bg-danger"><i class="fa-solid fa-times me-1"></i>Vencido</span>
                            <?php if ($resultActualizacion['dias_vencido']): ?>
                                <small class="text-danger">(<?= $resultActualizacion['dias_vencido'] ?> días vencido)</small>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    <div class="mb-3">
                        <strong>Flag BD:</strong><br>
                        <?php if ($cliente['identificacion_incompleta']): ?>
                            <span class="badge bg-danger">IDENTIFICACION_INCOMPLETA</span>
                        <?php else: ?>
                            <span class="badge bg-success">Completo</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <hr>
            
            <h6 class="text-primary mb-3"><i class="fa-solid fa-list-check me-2"></i>Componentes del Expediente</h6>
            <div class="row">
                <div class="col-md-6">
                    <div class="card mb-3">
                        <div class="card-body">
                            <h6 class="card-title">Datos Básicos</h6>
                            <?php if ($cliente['es_fisica'] > 0): ?>
                                <?php
                                $stmt = $pdo->prepare("SELECT * FROM clientes_fisicas WHERE id_cliente = ?");
                                $stmt->execute([$id_cliente]);
                                $fisica = $stmt->fetch(PDO::FETCH_ASSOC);
                                ?>
                                <?php if ($fisica && !empty($fisica['nombre']) && !empty($fisica['apellido_paterno'])): ?>
                                    <span class="badge bg-success"><i class="fa-solid fa-check me-1"></i>Completo</span>
                                <?php else: ?>
                                    <span class="badge bg-danger"><i class="fa-solid fa-times me-1"></i>Faltante</span>
                                <?php endif; ?>
                            <?php elseif ($cliente['es_moral'] > 0): ?>
                                <?php
                                $stmt = $pdo->prepare("SELECT * FROM clientes_morales WHERE id_cliente = ?");
                                $stmt->execute([$id_cliente]);
                                $moral = $stmt->fetch(PDO::FETCH_ASSOC);
                                ?>
                                <?php if ($moral && !empty($moral['razon_social'])): ?>
                                    <span class="badge bg-success"><i class="fa-solid fa-check me-1"></i>Completo</span>
                                <?php else: ?>
                                    <span class="badge bg-danger"><i class="fa-solid fa-times me-1"></i>Faltante</span>
                                <?php endif; ?>
                            <?php elseif ($cliente['es_fideicomiso'] > 0): ?>
                                <?php
                                $stmt = $pdo->prepare("SELECT * FROM clientes_fideicomisos WHERE id_cliente = ?");
                                $stmt->execute([$id_cliente]);
                                $fideicomiso = $stmt->fetch(PDO::FETCH_ASSOC);
                                ?>
                                <?php if ($fideicomiso && !empty($fideicomiso['numero_fideicomiso'])): ?>
                                    <span class="badge bg-success"><i class="fa-solid fa-check me-1"></i>Completo</span>
                                <?php else: ?>
                                    <span class="badge bg-danger"><i class="fa-solid fa-times me-1"></i>Faltante</span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card mb-3">
                        <div class="card-body">
                            <h6 class="card-title">Identificaciones</h6>
                            <?php if ($identificaciones['count'] > 0): ?>
                                <span class="badge bg-success"><i class="fa-solid fa-check me-1"></i><?= $identificaciones['count'] ?> registrada(s)</span>
                            <?php else: ?>
                                <span class="badge bg-danger"><i class="fa-solid fa-times me-1"></i>Sin identificaciones</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card mb-3">
                        <div class="card-body">
                            <h6 class="card-title">Direcciones</h6>
                            <?php if ($direcciones['count'] > 0): ?>
                                <span class="badge bg-success"><i class="fa-solid fa-check me-1"></i><?= $direcciones['count'] ?> registrada(s)</span>
                            <?php else: ?>
                                <span class="badge bg-danger"><i class="fa-solid fa-times me-1"></i>Sin direcciones</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card mb-3">
                        <div class="card-body">
                            <h6 class="card-title">Contactos</h6>
                            <?php if ($contactos['count'] > 0): ?>
                                <span class="badge bg-success"><i class="fa-solid fa-check me-1"></i><?= $contactos['count'] ?> registrado(s)</span>
                            <?php else: ?>
                                <span class="badge bg-danger"><i class="fa-solid fa-times me-1"></i>Sin contactos</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card mb-3">
                        <div class="card-body">
                            <h6 class="card-title">Documentos</h6>
                            <?php if ($documentos['count'] > 0): ?>
                                <span class="badge bg-info"><?= $documentos['count'] ?> documento(s)</span>
                                <?php if ($documentos['con_archivo'] > 0): ?>
                                    <span class="badge bg-success ms-2"><i class="fa-solid fa-check me-1"></i><?= $documentos['con_archivo'] ?> con archivo</span>
                                <?php else: ?>
                                    <span class="badge bg-warning ms-2"><i class="fa-solid fa-exclamation me-1"></i>Sin archivos</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="badge bg-danger"><i class="fa-solid fa-times me-1"></i>Sin documentos</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php if (!empty($resultCompleto['faltantes'])): ?>
                <hr>
                <h6 class="text-danger mb-3"><i class="fa-solid fa-exclamation-triangle me-2"></i>Elementos Faltantes</h6>
                <ul class="list-group">
                    <?php foreach ($resultCompleto['faltantes'] as $faltante): ?>
                        <li class="list-group-item list-group-item-danger">
                            <i class="fa-solid fa-times-circle me-2"></i><?= htmlspecialchars($faltante) ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            
            <?php
            exit;
        }
    }
}

// --- FETCH DATA ---
// Obtener todos los clientes con estado de expediente
$stmt = $pdo->query("
    SELECT c.id_cliente, c.alias, c.no_contrato, c.fecha_apertura,
           c.identificacion_incompleta, c.expediente_completo,
           c.fecha_ultima_actualizacion_expediente,
           tp.nombre as tipo_persona_nombre,
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
    ORDER BY c.fecha_apertura DESC
");
$clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<title>Expedientes PLD - VAL-PLD-005 y VAL-PLD-006</title>

<div class="container mt-4 mb-5">
    <div class="card shadow-sm">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <div>
                <h5 class="mb-0"><i class="fa-solid fa-folder-open me-2"></i>Expedientes de Identificación PLD</h5>
                <small class="opacity-75">VAL-PLD-005: Integración de Expediente | VAL-PLD-006: Actualización Anual</small>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Cliente</th>
                            <th>Alias / No. Contrato</th>
                            <th>Tipo</th>
                            <th class="text-center">Completitud</th>
                            <th class="text-center">Actualización</th>
                            <th class="text-center">Estado</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($clientes as $c): 
                            $resultCompleto = validateExpedienteCompleto($pdo, $c['id_cliente']);
                            $resultActualizacion = validateActualizacionExpediente($pdo, $c['id_cliente']);
                            $isValid = $resultCompleto['completo'] && $resultActualizacion['actualizado'];
                        ?>
                            <tr>
                                <td class="fw-bold"><?= htmlspecialchars($c['nombre_cliente'] ?? $c['alias']) ?></td>
                                <td>
                                    <small><?= htmlspecialchars($c['alias']) ?></small><br>
                                    <small class="text-muted"><?= htmlspecialchars($c['no_contrato'] ?? 'N/A') ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-secondary"><?= htmlspecialchars($c['tipo_persona_nombre'] ?? 'N/A') ?></span>
                                </td>
                                <td class="text-center">
                                    <?php if ($resultCompleto['completo']): ?>
                                        <span class="badge bg-success"><i class="fa-solid fa-check me-1"></i>Completo</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger"><i class="fa-solid fa-times me-1"></i>Incompleto</span>
                                        <?php if (!empty($resultCompleto['faltantes'])): ?>
                                            <br><small class="text-danger"><?= count($resultCompleto['faltantes']) ?> faltante(s)</small>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($resultActualizacion['actualizado']): ?>
                                        <span class="badge bg-success"><i class="fa-solid fa-check me-1"></i>Actualizado</span>
                                        <?php if ($c['fecha_ultima_actualizacion_expediente']): ?>
                                            <br><small class="text-muted"><?= date('d/m/Y', strtotime($c['fecha_ultima_actualizacion_expediente'])) ?></small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="badge bg-danger"><i class="fa-solid fa-exclamation-triangle me-1"></i>Vencido</span>
                                        <?php if ($resultActualizacion['dias_vencido']): ?>
                                            <br><small class="text-danger"><?= $resultActualizacion['dias_vencido'] ?> días</small>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($isValid): ?>
                                        <span class="badge bg-success"><i class="fa-solid fa-check-circle me-1"></i>Válido</span>
                                    <?php elseif ($c['identificacion_incompleta']): ?>
                                        <span class="badge bg-danger"><i class="fa-solid fa-times-circle me-1"></i>IDENTIFICACION_INCOMPLETA</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark"><i class="fa-solid fa-exclamation me-1"></i>Revisar</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-info" onclick="viewExpediente(<?= $c['id_cliente'] ?>)">
                                        <i class="fa-solid fa-eye me-1"></i>Ver
                                    </button>
                                    <button class="btn btn-sm btn-primary" onclick="validateExpediente(<?= $c['id_cliente'] ?>)">
                                        <i class="fa-solid fa-check-circle me-1"></i>Validar
                                    </button>
                                    <?php if (!$resultActualizacion['actualizado']): ?>
                                        <button class="btn btn-sm btn-success" onclick="updateFechaExpediente(<?= $c['id_cliente'] ?>)">
                                            <i class="fa-solid fa-calendar me-1"></i>Actualizar Fecha
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal para Ver Detalles del Expediente -->
<div class="modal fade" id="viewModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="fa-solid fa-folder-open me-2"></i>Detalles del Expediente</h5>
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
function viewExpediente(idCliente) {
    fetch('expedientes_pld.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=get_detalles_expediente&id_cliente=' + idCliente
    })
    .then(response => response.text())
    .then(html => {
        document.getElementById('viewModalBody').innerHTML = html;
        const modal = new bootstrap.Modal(document.getElementById('viewModal'));
        modal.show();
    })
    .catch(error => {
        Swal.fire('Error', 'No se pudieron cargar los detalles del expediente', 'error');
    });
}

function validateExpediente(idCliente) {
    fetch('expedientes_pld.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=validate_expediente&id_cliente=' + idCliente
    })
    .then(response => response.json())
    .then(data => {
        let html = '<div class="row">';
        html += '<div class="col-md-6">';
        html += '<h6>Completitud (VAL-PLD-005)</h6>';
        if (data.completitud.completo) {
            html += '<div class="alert alert-success"><i class="fa-solid fa-check-circle me-2"></i>Expediente completo</div>';
        } else {
            html += '<div class="alert alert-danger"><i class="fa-solid fa-times-circle me-2"></i>Expediente incompleto</div>';
            if (data.completitud.faltantes && data.completitud.faltantes.length > 0) {
                html += '<ul class="list-group mt-2">';
                data.completitud.faltantes.forEach(f => {
                    html += '<li class="list-group-item list-group-item-danger">' + f + '</li>';
                });
                html += '</ul>';
            }
        }
        html += '</div>';
        html += '<div class="col-md-6">';
        html += '<h6>Actualización (VAL-PLD-006)</h6>';
        if (data.actualizacion.actualizado) {
            html += '<div class="alert alert-success"><i class="fa-solid fa-check-circle me-2"></i>Expediente actualizado</div>';
            if (data.actualizacion.fecha_ultima_actualizacion) {
                html += '<p><strong>Última actualización:</strong> ' + data.actualizacion.fecha_ultima_actualizacion + '</p>';
            }
        } else {
            html += '<div class="alert alert-danger"><i class="fa-solid fa-exclamation-triangle me-2"></i>Expediente vencido</div>';
            if (data.actualizacion.dias_vencido) {
                html += '<p><strong>Días vencido:</strong> ' + data.actualizacion.dias_vencido + '</p>';
            }
        }
        html += '</div>';
        html += '</div>';
        
        if (data.valido) {
            html += '<div class="alert alert-success mt-3"><i class="fa-solid fa-check-circle me-2"></i><strong>Expediente válido para operaciones PLD</strong></div>';
        } else {
            html += '<div class="alert alert-danger mt-3"><i class="fa-solid fa-times-circle me-2"></i><strong>Expediente NO válido - Bloquea operaciones PLD</strong></div>';
        }
        
        Swal.fire({
            title: 'Resultado de Validación',
            html: html,
            width: '800px',
            icon: data.valido ? 'success' : 'error'
        });
    })
    .catch(error => {
        Swal.fire('Error', 'Error al validar expediente', 'error');
    });
}

function updateFechaExpediente(idCliente) {
    Swal.fire({
        title: '¿Actualizar fecha de expediente?',
        text: 'Esto marcará el expediente como actualizado hoy',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, actualizar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = '<input type="hidden" name="action" value="update_fecha_expediente">' +
                           '<input type="hidden" name="id_cliente" value="' + idCliente + '">';
            document.body.appendChild(form);
            form.submit();
        }
    });
}
</script>

<?php include '../templates/footer.php'; ?>

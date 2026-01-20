<?php 
include 'templates/header.php'; 
?>
<title>Recursos PLD - Plantillas e Instructivos</title>

<style>
    /* Watermark Style - MOVED TO LEFT MARGIN */
    .watermark {
        position: fixed;
        top: 50%;
        left: 0; /* Align to left edge */
        transform: translate(-30%, -50%); /* Shift left to cut it off partly */
        font-size: 40vw; /* Increased size slightly for impact */
        font-weight: bold;
        color: rgba(0, 0, 0, 0.08); 
        z-index: 0; 
        pointer-events: none; 
        font-family: 'Times New Roman', serif;
        user-select: none;
        line-height: 1;
        white-space: nowrap;
    }

    .instruction-container {
        max-width: 1400px; 
        margin: 2rem auto;
        padding: 0 20px;
        position: relative; 
        z-index: 10; 
    }
    
    .file-card {
        border: none;
        box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        border-radius: 12px;
        overflow: hidden;
        background: white; 
        height: 100%;
        display: flex;
        flex-direction: column;
    }
    
    .file-header {
        background-color: #fff;
        padding: 1.5rem;
        border-bottom: 1px solid #f0f0f0;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .pdf-viewer {
        width: 100%;
        height: 60vh; 
        border: none;
        background-color: #f8f9fa;
        flex-grow: 1;
    }
    
    .empty-placeholder {
        height: 60vh;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        background-color: #f8f9fa;
        color: #adb5bd;
        flex-grow: 1;
    }
</style>
</head>
<body>

<?php 
// Updated Include with sub_page flag
$is_sub_page = true; 
include 'templates/top_bar.php'; 
?>

<?php
// 1. Get the configured ID from config_empresa
$id_vulnerable = 0;
try {
    $stmtConfig = $pdo->query("SELECT id_vulnerable FROM config_empresa WHERE id_config = 1");
    $config = $stmtConfig->fetch(PDO::FETCH_ASSOC);
    $id_vulnerable = $config['id_vulnerable'] ?? 0;
} catch (Exception $e) {
    // Fail silently
}

// 2. Fetch Vulnerable Activity details
$activity = null;
if ($id_vulnerable > 0) {
    try {
        $stmtVuln = $pdo->prepare("SELECT nombre, ruta_template, ruta_instructivo, fraccion FROM cat_vulnerables WHERE id_vulnerable = ?");
        $stmtVuln->execute([$id_vulnerable]);
        $activity = $stmtVuln->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<?php if ($activity && !empty($activity['fraccion'])): ?>
    <div class="watermark"><?= htmlspecialchars($activity['fraccion']) ?></div>
<?php endif; ?>

<div class="instruction-container">
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold text-primary mb-0"><i class="fa-solid fa-folder-open me-2"></i>Recursos de Cumplimiento</h2>
            <?php if ($activity): ?>
                <p class="text-muted mb-0">
                    Actividad: <strong><?= htmlspecialchars($activity['nombre']) ?></strong> 
                    (Fracción <?= htmlspecialchars($activity['fraccion']) ?>)
                </p>
            <?php endif; ?>
        </div>
        </div>

    <?php if ($id_vulnerable == 0): ?>
        
        <div class="card p-5 text-center shadow-sm border-0">
            <div class="mb-3"><i class="fa-solid fa-ban fa-4x text-secondary opacity-50"></i></div>
            <h3>No aplica</h3>
            <p class="text-muted">La empresa no está configurada como Actividad Vulnerable.</p>
        </div>

    <?php elseif ($activity): ?>
        
        <div class="row g-4">
            
            <div class="col-xl-6">
                <div class="file-card">
                    <div class="file-header">
                        <div>
                            <h5 class="fw-bold mb-1"><i class="fa-solid fa-file-lines me-2 text-primary"></i>Plantilla / Formato</h5>
                            <small class="text-muted">Documento base para llenado</small>
                        </div>
                        <?php if (!empty($activity['ruta_template']) && file_exists($activity['ruta_template'])): ?>
                            <a href="<?= htmlspecialchars($activity['ruta_template']) ?>" download class="btn btn-primary btn-sm">
                                <i class="fa-solid fa-download me-1"></i> Descargar
                            </a>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (!empty($activity['ruta_template']) && file_exists($activity['ruta_template'])): ?>
                        <embed src="<?= htmlspecialchars($activity['ruta_template']) ?>" type="application/pdf" class="pdf-viewer">
                    <?php else: ?>
                        <div class="empty-placeholder">
                            <i class="fa-regular fa-file-excel fa-3x mb-3"></i>
                            <span>Plantilla no disponible</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-xl-6">
                <div class="file-card">
                    <div class="file-header">
                        <div>
                            <h5 class="fw-bold mb-1"><i class="fa-solid fa-book me-2 text-success"></i>Instructivo</h5>
                            <small class="text-muted">Guía de llenado y obligaciones</small>
                        </div>
                        <?php if (!empty($activity['ruta_instructivo']) && file_exists($activity['ruta_instructivo'])): ?>
                            <a href="<?= htmlspecialchars($activity['ruta_instructivo']) ?>" download class="btn btn-success btn-sm">
                                <i class="fa-solid fa-download me-1"></i> Descargar
                            </a>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($activity['ruta_instructivo']) && file_exists($activity['ruta_instructivo'])): ?>
                        <embed src="<?= htmlspecialchars($activity['ruta_instructivo']) ?>" type="application/pdf" class="pdf-viewer">
                    <?php else: ?>
                        <div class="empty-placeholder">
                            <i class="fa-solid fa-book-open fa-3x mb-3"></i>
                            <span>Instructivo no disponible</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>

    <?php else: ?>
        <div class="alert alert-danger">Error al cargar la información de la actividad.</div>
    <?php endif; ?>

</div>

<?php include 'templates/footer.php'; ?>
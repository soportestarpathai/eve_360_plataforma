-- ============================================
-- Migration: Snapshot KYC en operaciones PLD
-- Fecha: 2026-02-25
-- ============================================

SET @col_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'operaciones_pld'
      AND COLUMN_NAME = 'kyc_snapshot_json'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `operaciones_pld`
     ADD COLUMN `kyc_snapshot_json` JSON DEFAULT NULL COMMENT ''Snapshot KYC al momento de registrar la transacción'' AFTER `id_aviso_generado`',
    'SELECT ''Column kyc_snapshot_json already exists'' AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SELECT 'Migration completed: kyc_snapshot_json on operaciones_pld.' AS result;

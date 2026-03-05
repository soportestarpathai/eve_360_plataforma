-- ============================================
-- Agregar id_usuario a visitas_verificacion_pld
-- Filtro por usuario: VAL-PLD-013 y VAL-PLD-014
-- ============================================

ALTER TABLE visitas_verificacion_pld ADD COLUMN id_usuario INT NULL DEFAULT NULL COMMENT 'Usuario que registró la visita';
ALTER TABLE visitas_verificacion_pld ADD INDEX idx_id_usuario (id_usuario);

-- Migración 001: autenticación JWT sobre la base EXISTENTE de la veterinaria.
-- No borra ni modifica datos clínicos: solo agrega columnas y tablas nuevas.
--
-- Ejecutala UNA sola vez (hacé un respaldo antes):
--   mysql -u root -p itapebi < migrations/001_auth.sql
--
-- Si instalás desde cero con schema.sql, NO hace falta: ya incluye todo esto.

-- Rol de cada usuario. Los usuarios que ya existían pasan a ser admin,
-- porque en el sistema anterior tenían acceso a todo.
ALTER TABLE `usuario`
    ADD COLUMN `UsuarioRol` ENUM('user', 'admin') NOT NULL DEFAULT 'user' AFTER `UsuarioPwd`,
    MODIFY `UsuarioPwd` VARCHAR(255) DEFAULT NULL,
    ADD UNIQUE KEY `uq_usuario_user` (`UsuarioUser`);

UPDATE `usuario` SET `UsuarioRol` = 'admin';

-- Solo se guarda el hash SHA-256 del refresh token; fechas en UTC
CREATE TABLE IF NOT EXISTS `refresh_tokens` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `usuario_id` INT NOT NULL,
    `token_hash` CHAR(64) NOT NULL,
    `expira_en` DATETIME NOT NULL,
    `revocado` TINYINT(1) NOT NULL DEFAULT 0,
    `creado_en` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_refresh_token_hash` (`token_hash`),
    CONSTRAINT `fk_refresh_usuario`
        FOREIGN KEY (`usuario_id`) REFERENCES `usuario` (`UsuarioId`)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Intentos de login fallidos (límite por usuario + IP); fechas en UTC
CREATE TABLE IF NOT EXISTS `login_intentos` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `usuario` VARCHAR(100) NOT NULL,
    `ip` VARCHAR(45) NOT NULL,
    `creado_en` DATETIME NOT NULL,
    KEY `idx_login_usuario_ip` (`usuario`, `ip`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Esquema completo para una instalación NUEVA de la API de la veterinaria.
-- Para la base que ya está en uso, NO uses este archivo: corré migrations/001_auth.sql.
--
--   mysql -u root -p < schema.sql
--
-- Las tablas y columnas son las mismas del sistema anterior (itapebi_db.sql),
-- así los datos existentes se pueden importar sin cambios.

CREATE DATABASE IF NOT EXISTS `itapebi` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
USE `itapebi`;

CREATE TABLE IF NOT EXISTS `especies` (
    `EspecieId` INT NOT NULL AUTO_INCREMENT,
    `EspecieNombre` VARCHAR(100) DEFAULT NULL,
    `EspecieFchCreacion` DATETIME DEFAULT NULL,
    `EspecieFchModificacion` DATETIME DEFAULT NULL,
    `EspecieEstado` SMALLINT DEFAULT NULL,
    PRIMARY KEY (`EspecieId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `propietarios` (
    `PropietarioId` INT NOT NULL AUTO_INCREMENT,
    `PropietarioNombre` VARCHAR(100) DEFAULT NULL,
    `PropietarioApellido` VARCHAR(100) DEFAULT NULL,
    `PropietarioTelefono` VARCHAR(10) DEFAULT NULL,
    `PropietarioDireccion` VARCHAR(255) DEFAULT NULL,
    `PropietarioEmail` VARCHAR(100) DEFAULT NULL,
    `PropietarioFchCreacion` DATETIME DEFAULT NULL,
    `PropietarioFchModificacion` DATETIME DEFAULT NULL,
    `PropietarioEstado` SMALLINT DEFAULT NULL,
    PRIMARY KEY (`PropietarioId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `mascotas` (
    `MascotaId` INT NOT NULL AUTO_INCREMENT,
    `MascotaNombre` VARCHAR(100) DEFAULT NULL,
    `EspecieId` INT DEFAULT NULL,
    `PropietarioId` INT DEFAULT NULL,
    `MascotaFchNac` DATE DEFAULT NULL,
    `MascotaFchVencVac` DATE DEFAULT NULL,
    `MascotaFchCreacion` DATETIME DEFAULT NULL,
    `MascotaFchModificacion` DATETIME DEFAULT NULL,
    `MascotaEstado` SMALLINT DEFAULT NULL,
    PRIMARY KEY (`MascotaId`),
    KEY `FK_Especie` (`EspecieId`),
    KEY `FK_Propietario` (`PropietarioId`),
    CONSTRAINT `FK_Especie` FOREIGN KEY (`EspecieId`) REFERENCES `especies` (`EspecieId`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `FK_Propietario` FOREIGN KEY (`PropietarioId`) REFERENCES `propietarios` (`PropietarioId`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `consultas` (
    `ConsultaId` INT NOT NULL AUTO_INCREMENT,
    `ConsultaFecha` DATE DEFAULT NULL,
    `MascotaId` INT DEFAULT NULL,
    `ConsultaMotivo` VARCHAR(255) DEFAULT NULL,
    `ConsultaDiagnostico` MEDIUMTEXT,
    `ConsultaTratamiento` MEDIUMTEXT,
    `ConsultaArchivo1` VARCHAR(255) DEFAULT NULL,
    `ConsultaArchivo2` VARCHAR(255) DEFAULT NULL,
    `ConsultaArchivo3` VARCHAR(255) DEFAULT NULL,
    `ConsultaFchCreacion` DATETIME DEFAULT NULL,
    `ConsultaFchModificacion` DATETIME DEFAULT NULL,
    `ConsultaEstado` SMALLINT DEFAULT NULL,
    PRIMARY KEY (`ConsultaId`),
    KEY `consultas_FK` (`MascotaId`),
    CONSTRAINT `consultas_FK` FOREIGN KEY (`MascotaId`) REFERENCES `mascotas` (`MascotaId`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `cuotas` (
    `CuotaId` INT NOT NULL AUTO_INCREMENT,
    `CuotaFecha` DATE DEFAULT NULL,
    `MascotaId` INT DEFAULT NULL,
    `CuotaValor` BIGINT DEFAULT NULL,
    `CuotaObservacion` VARCHAR(255) DEFAULT NULL,
    `CuotaFchCreacion` DATETIME DEFAULT NULL,
    `CuotaFchModificacion` DATETIME DEFAULT NULL,
    `CuotaEstado` SMALLINT DEFAULT NULL,
    PRIMARY KEY (`CuotaId`),
    KEY `cuotas_FK` (`MascotaId`),
    CONSTRAINT `cuotas_FK` FOREIGN KEY (`MascotaId`) REFERENCES `mascotas` (`MascotaId`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `vacunas` (
    `VacunaId` INT NOT NULL AUTO_INCREMENT,
    `VacunaFchIngreso` DATE DEFAULT NULL,
    `MascotaId` INT DEFAULT NULL,
    `VacunaFchVenc` DATE DEFAULT NULL,
    `VacunaFchModificacion` DATETIME DEFAULT NULL,
    `VacunaEstado` SMALLINT DEFAULT NULL,
    PRIMARY KEY (`VacunaId`),
    KEY `vacunas_mascotas_FK` (`MascotaId`),
    CONSTRAINT `vacunas_mascotas_FK` FOREIGN KEY (`MascotaId`) REFERENCES `mascotas` (`MascotaId`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ============ Autenticación (JWT) ============

CREATE TABLE IF NOT EXISTS `usuario` (
    `UsuarioId` INT NOT NULL AUTO_INCREMENT,
    `UsuarioNombre` VARCHAR(100) DEFAULT NULL,
    `UsuarioUser` VARCHAR(100) DEFAULT NULL,
    `UsuarioPwd` VARCHAR(255) DEFAULT NULL,
    `UsuarioRol` ENUM('user', 'admin') NOT NULL DEFAULT 'user',
    `UsuarioFchCreacion` DATE DEFAULT NULL,
    `UsuarioEstado` SMALLINT DEFAULT NULL,
    PRIMARY KEY (`UsuarioId`),
    UNIQUE KEY `uq_usuario_user` (`UsuarioUser`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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

-- Especies básicas (opcional)
INSERT INTO `especies` (`EspecieNombre`, `EspecieFchCreacion`, `EspecieEstado`) VALUES
('Canino', NOW(), 1),
('Felino', NOW(), 1);

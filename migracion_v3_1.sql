-- ╔══════════════════════════════════════════════════════════════════╗
-- ║  BIFFI OLIMPIADAS — Migración v3.1                              ║
-- ║  Importar en phpMyAdmin sobre la BD olimpiadas_pro              ║
-- ╚══════════════════════════════════════════════════════════════════╝
USE `olimpiadas_pro`;

-- 1. Agregar tipo_prueba a preguntas
ALTER TABLE `preguntas`
  ADD COLUMN IF NOT EXISTS `tipo_prueba`
    ENUM('simulacro','clasificatoria','selectiva','final') NOT NULL DEFAULT 'simulacro' AFTER `grupo_grado`;

-- 2. Agregar tiempo_limite a secciones (en minutos, NULL = sin límite)
ALTER TABLE `secciones`
  ADD COLUMN IF NOT EXISTS `tiempo_limite_min` TINYINT UNSIGNED DEFAULT NULL AFTER `fecha_cierre`;

-- Tiempos por defecto para cada sección
UPDATE secciones SET tiempo_limite_min = NULL  WHERE nombre = 'general';
UPDATE secciones SET tiempo_limite_min = NULL  WHERE nombre = 'simulacros';
UPDATE secciones SET tiempo_limite_min = 60    WHERE nombre = 'clasificatoria';
UPDATE secciones SET tiempo_limite_min = 90    WHERE nombre = 'selectiva';
UPDATE secciones SET tiempo_limite_min = 120   WHERE nombre = 'final';
UPDATE secciones SET tiempo_limite_min = NULL  WHERE nombre = 'biblioteca';

-- 3. Tabla pruebas_config: configuración avanzada por tipo y grupo
CREATE TABLE IF NOT EXISTS `pruebas_config` (
  `id`              INT AUTO_INCREMENT PRIMARY KEY,
  `tipo_prueba`     ENUM('simulacro','clasificatoria','selectiva','final') NOT NULL,
  `grupo_grado`     ENUM('4-5','6-7','8-9','10-11') NOT NULL,
  `tiempo_limite_min` TINYINT UNSIGNED DEFAULT NULL,
  `num_preguntas`   TINYINT UNSIGNED DEFAULT 10,
  `habilitada`      TINYINT(1) DEFAULT 0,
  `instrucciones`   TEXT DEFAULT NULL,
  `creado_en`       DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `config_unica` (`tipo_prueba`, `grupo_grado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insertar configuración por defecto para cada combinación
INSERT IGNORE INTO `pruebas_config` (tipo_prueba, grupo_grado, tiempo_limite_min, num_preguntas, habilitada) VALUES
('simulacro',      '4-5',   NULL, 10, 1),
('simulacro',      '6-7',   NULL, 10, 1),
('simulacro',      '8-9',   NULL, 10, 1),
('simulacro',      '10-11', NULL, 10, 1),
('clasificatoria', '4-5',   45,   10, 0),
('clasificatoria', '6-7',   60,   10, 0),
('clasificatoria', '8-9',   60,   10, 0),
('clasificatoria', '10-11', 60,   10, 0),
('selectiva',      '4-5',   60,   15, 0),
('selectiva',      '6-7',   75,   15, 0),
('selectiva',      '8-9',   90,   15, 0),
('selectiva',      '10-11', 90,   15, 0),
('final',          '4-5',   60,   20, 0),
('final',          '6-7',   90,   20, 0),
('final',          '8-9',   120,  20, 0),
('final',          '10-11', 120,  20, 0);

-- 4. Asegurar que recursos tenga las columnas correctas para biblioteca
ALTER TABLE `recursos`
  ADD COLUMN IF NOT EXISTS `nivel_acceso` ENUM('todos','docente','admin') DEFAULT 'todos' AFTER `visible`;

-- Datos para probar con las preguntas existentes
UPDATE preguntas SET tipo_prueba = 'simulacro' WHERE tipo_prueba IS NULL OR tipo_prueba = '';

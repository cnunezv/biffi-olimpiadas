-- ╔══════════════════════════════════════════════════════════════╗
-- ║  BIFFI OLIMPIADAS — Migración: Google Forms                 ║
-- ╚══════════════════════════════════════════════════════════════╝
USE `olimpiadas_pro`;

CREATE TABLE IF NOT EXISTS `forms_google` (
  `id`              INT AUTO_INCREMENT PRIMARY KEY,
  `titulo`          VARCHAR(200) NOT NULL,
  `descripcion`     TEXT,
  `form_url`        VARCHAR(500) NOT NULL COMMENT 'URL embed de Google Forms',
  `sheet_csv_url`   VARCHAR(500) DEFAULT NULL COMMENT 'URL CSV público de respuestas (Google Sheets)',
  `tipo_prueba`     ENUM('simulacro','clasificatoria','selectiva','final','taller','evaluacion') DEFAULT 'evaluacion',
  `grupo_grado`     ENUM('4-5','6-7','8-9','10-11','todos') DEFAULT 'todos',
  `habilitada`      TINYINT(1) DEFAULT 1,
  `tiempo_limite_min` TINYINT UNSIGNED DEFAULT NULL,
  `fecha_inicio`    DATETIME DEFAULT NULL,
  `fecha_cierre`    DATETIME DEFAULT NULL,
  `creado_por`      INT DEFAULT NULL,
  `creado_en`       DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`creado_por`) REFERENCES `usuarios`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

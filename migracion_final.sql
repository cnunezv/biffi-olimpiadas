-- ╔══════════════════════════════════════════════════════════════════╗
-- ║  BIFFI OLIMPIADAS — Migración Final Consolidada                ║
-- ║  Corre SOLO este archivo si es instalación nueva.              ║
-- ║  Si ya tienes la BD, importa solo migracion_v3_2.sql          ║
-- ╚══════════════════════════════════════════════════════════════════╝
USE `olimpiadas_pro`;

-- ─── INSTITUCIONES ────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `instituciones` (
  `id`            INT AUTO_INCREMENT PRIMARY KEY,
  `nombre`        VARCHAR(200) NOT NULL,
  `ciudad`        VARCHAR(100) DEFAULT 'Cartagena',
  `departamento`  VARCHAR(100) DEFAULT 'Bolívar',
  `codigo`        VARCHAR(20)  DEFAULT '',
  `color`         VARCHAR(7)   DEFAULT '#7C1F30',
  `activa`        TINYINT(1)   DEFAULT 1,
  `creada_en`     DATETIME     DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE `instituciones`
  ADD COLUMN IF NOT EXISTS `departamento` VARCHAR(100) DEFAULT 'Bolívar' AFTER `ciudad`,
  ADD COLUMN IF NOT EXISTS `codigo`       VARCHAR(20)  DEFAULT ''        AFTER `departamento`;

INSERT IGNORE INTO `instituciones` (id,nombre,ciudad,departamento,codigo,color,activa) VALUES
(1,'Colegio Biffi',                   'Cartagena','Bolívar','BIFFI', '#7C1F30',1),
(2,'Colegio La Salle Cartagena',      'Cartagena','Bolívar','SALLE', '#1a3a6b',1),
(3,'Colegio San Pedro Claver',        'Cartagena','Bolívar','SPC',   '#0d5c1a',1),
(4,'IED Manuel Elkin Patarroyo',      'Cartagena','Bolívar','MEP',   '#7a4000',1),
(5,'Colegio Jorge Washington',        'Cartagena','Bolívar','JW',    '#8b1a1a',1),
(6,'Colegio Nuestra Señora del Carmen','Cartagena','Bolívar','NSC',  '#005b8e',1),
(7,'Instituto Técnico Industrial',    'Cartagena','Bolívar','ITI',   '#4a0080',1);

-- ─── USUARIOS ─────────────────────────────────────────────────────
ALTER TABLE `usuarios`
  ADD COLUMN IF NOT EXISTS `grado`          TINYINT UNSIGNED DEFAULT NULL AFTER `curso`,
  ADD COLUMN IF NOT EXISTS `institucion_id` INT DEFAULT NULL AFTER `institucion`;

UPDATE `usuarios` SET `institucion_id` = 1 WHERE `institucion_id` IS NULL;

-- ─── PREGUNTAS ────────────────────────────────────────────────────
ALTER TABLE `preguntas`
  ADD COLUMN IF NOT EXISTS `grupo_grado` ENUM('4-5','6-7','8-9','10-11') NOT NULL DEFAULT '10-11' AFTER `nivel`,
  ADD COLUMN IF NOT EXISTS `tipo_prueba` ENUM('simulacro','clasificatoria','selectiva','final') NOT NULL DEFAULT 'simulacro' AFTER `grupo_grado`;

-- ─── PRUEBAS_CONFIG ───────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `pruebas_config` (
  `id`                INT AUTO_INCREMENT PRIMARY KEY,
  `tipo_prueba`       ENUM('simulacro','clasificatoria','selectiva','final') NOT NULL,
  `grupo_grado`       ENUM('4-5','6-7','8-9','10-11') NOT NULL,
  `tiempo_limite_min` TINYINT UNSIGNED DEFAULT NULL,
  `num_preguntas`     TINYINT UNSIGNED DEFAULT 10,
  `max_intentos`      TINYINT UNSIGNED DEFAULT 0 COMMENT '0 = ilimitado',
  `habilitada`        TINYINT(1) DEFAULT 0,
  `instrucciones`     TEXT DEFAULT NULL,
  `creado_en`         DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `config_unica` (`tipo_prueba`, `grupo_grado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE `pruebas_config`
  ADD COLUMN IF NOT EXISTS `max_intentos` TINYINT UNSIGNED DEFAULT 0 AFTER `num_preguntas`;

INSERT IGNORE INTO `pruebas_config` (tipo_prueba,grupo_grado,tiempo_limite_min,num_preguntas,max_intentos,habilitada) VALUES
('simulacro','4-5',  NULL,10,0,1),('simulacro','6-7',  NULL,10,0,1),
('simulacro','8-9',  NULL,10,0,1),('simulacro','10-11',NULL,10,0,1),
('clasificatoria','4-5',  45,10,1,0),('clasificatoria','6-7',  60,10,1,0),
('clasificatoria','8-9',  60,10,1,0),('clasificatoria','10-11',60,10,1,0),
('selectiva','4-5',  60,15,1,0),('selectiva','6-7',  75,15,1,0),
('selectiva','8-9',  90,15,1,0),('selectiva','10-11',90,15,1,0),
('final','4-5', 60,20,1,0),('final','6-7', 90,20,1,0),
('final','8-9',120,20,1,0),('final','10-11',120,20,1,0);

-- ─── SECCIONES ────────────────────────────────────────────────────
ALTER TABLE `secciones`
  ADD COLUMN IF NOT EXISTS `tiempo_limite_min` TINYINT UNSIGNED DEFAULT NULL AFTER `fecha_cierre`;

-- ─── RECURSOS ─────────────────────────────────────────────────────
ALTER TABLE `recursos`
  ADD COLUMN IF NOT EXISTS `nivel_acceso` ENUM('todos','docente','admin') DEFAULT 'todos' AFTER `visible`;

-- ─── GOOGLE FORMS ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `forms_google` (
  `id`                INT AUTO_INCREMENT PRIMARY KEY,
  `titulo`            VARCHAR(200) NOT NULL,
  `descripcion`       TEXT,
  `form_url`          VARCHAR(500) NOT NULL,
  `sheet_csv_url`     VARCHAR(500) DEFAULT NULL,
  `tipo_prueba`       ENUM('simulacro','clasificatoria','selectiva','final','taller','evaluacion') DEFAULT 'evaluacion',
  `grupo_grado`       ENUM('4-5','6-7','8-9','10-11','todos') DEFAULT 'todos',
  `habilitada`        TINYINT(1) DEFAULT 1,
  `tiempo_limite_min` TINYINT UNSIGNED DEFAULT NULL,
  `fecha_inicio`      DATETIME DEFAULT NULL,
  `fecha_cierre`      DATETIME DEFAULT NULL,
  `creado_por`        INT DEFAULT NULL,
  `creado_en`         DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`creado_por`) REFERENCES `usuarios`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

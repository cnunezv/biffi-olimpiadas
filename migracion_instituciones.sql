-- ╔══════════════════════════════════════════════════════════════════╗
-- ║  BIFFI OLIMPIADAS — Migración FINAL: Instituciones completa    ║
-- ║  Importar en phpMyAdmin sobre olimpiadas_pro                   ║
-- ╚══════════════════════════════════════════════════════════════════╝
USE `olimpiadas_pro`;

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

ALTER TABLE `usuarios`
  ADD COLUMN IF NOT EXISTS `institucion_id` INT DEFAULT NULL AFTER `institucion`;

INSERT IGNORE INTO `instituciones` (id,nombre,ciudad,departamento,codigo,color,activa) VALUES
(1,'Colegio Biffi',                   'Cartagena','Bolívar','BIFFI', '#7C1F30',1),
(2,'Colegio La Salle Cartagena',      'Cartagena','Bolívar','SALLE', '#1a3a6b',1),
(3,'Colegio San Pedro Claver',        'Cartagena','Bolívar','SPC',   '#0d5c1a',1),
(4,'IED Manuel Elkin Patarroyo',      'Cartagena','Bolívar','MEP',   '#7a4000',1),
(5,'Colegio Jorge Washington',        'Cartagena','Bolívar','JW',    '#8b1a1a',1),
(6,'Colegio Nuestra Señora del Carmen','Cartagena','Bolívar','NSC',  '#005b8e',1),
(7,'Instituto Técnico Industrial',    'Cartagena','Bolívar','ITI',   '#4a0080',1);

UPDATE `usuarios` SET `institucion_id` = 1 WHERE `institucion_id` IS NULL;

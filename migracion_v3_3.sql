-- ╔══════════════════════════════════════════════════════════════════╗
-- ║  BIFFI OLIMPIADAS — Migración v3.3 (FINAL)                     ║
-- ║  Importar en phpMyAdmin sobre olimpiadas_pro                   ║
-- ║  Incluye: max_intentos, sync secciones, roles externos         ║
-- ╚══════════════════════════════════════════════════════════════════╝
USE `olimpiadas_pro`;

-- 1. Agregar max_intentos a pruebas_config (0 = ilimitado)
ALTER TABLE `pruebas_config`
  ADD COLUMN IF NOT EXISTS `max_intentos` TINYINT UNSIGNED DEFAULT 0 AFTER `num_preguntas`;

-- 2. Valores por defecto: clasificatoria/selectiva/final = 1 intento; simulacro = 0 (ilimitado)
UPDATE `pruebas_config` SET `max_intentos` = 0 WHERE `tipo_prueba` = 'simulacro';
UPDATE `pruebas_config` SET `max_intentos` = 1 WHERE `tipo_prueba` = 'clasificatoria';
UPDATE `pruebas_config` SET `max_intentos` = 1 WHERE `tipo_prueba` = 'selectiva';
UPDATE `pruebas_config` SET `max_intentos` = 1 WHERE `tipo_prueba` = 'final';

-- 3. Sincronizar secciones con pruebas_config (si secciones está habilitada, activar config)
UPDATE `pruebas_config` pc
JOIN `secciones` s ON s.nombre = pc.tipo_prueba
SET pc.habilitada = s.habilitada
WHERE pc.tipo_prueba IN ('clasificatoria','selectiva','final');

-- 4. Agregar columna institucion_id a usuarios si no existe
ALTER TABLE `usuarios`
  ADD COLUMN IF NOT EXISTS `institucion_id` INT DEFAULT NULL AFTER `institucion`;

-- 5. Asignar Biffi (id=1) a usuarios sin institución
UPDATE `usuarios` SET `institucion_id` = 1 WHERE `institucion_id` IS NULL AND `rol` != 'estudiante';

-- 6. Agregar max_intentos a pruebas_config de los grupos que no lo tengan
INSERT IGNORE INTO `pruebas_config` 
  (tipo_prueba, grupo_grado, tiempo_limite_min, num_preguntas, max_intentos, habilitada)
VALUES
  ('simulacro','4-5',NULL,10,0,1), ('simulacro','6-7',NULL,10,0,1),
  ('simulacro','8-9',NULL,10,0,1), ('simulacro','10-11',NULL,10,0,1),
  ('clasificatoria','4-5',45,10,1,0), ('clasificatoria','6-7',60,10,1,0),
  ('clasificatoria','8-9',60,10,1,0), ('clasificatoria','10-11',60,10,1,0),
  ('selectiva','4-5',60,15,1,0), ('selectiva','6-7',75,15,1,0),
  ('selectiva','8-9',90,15,1,0), ('selectiva','10-11',90,15,1,0),
  ('final','4-5',60,20,1,0), ('final','6-7',90,20,1,0),
  ('final','8-9',120,20,1,0), ('final','10-11',120,20,1,0);

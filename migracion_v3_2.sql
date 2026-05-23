-- ╔══════════════════════════════════════════════════════════════════╗
-- ║  BIFFI OLIMPIADAS — Migración v3.2 (actualización)             ║
-- ║  Solo si ya tienes la BD instalada de una versión anterior     ║
-- ╚══════════════════════════════════════════════════════════════════╝
USE `olimpiadas_pro`;

-- 1. Agregar max_intentos a pruebas_config (0 = sin límite)
ALTER TABLE `pruebas_config`
  ADD COLUMN IF NOT EXISTS `max_intentos` TINYINT UNSIGNED DEFAULT 0 COMMENT '0=ilimitado' AFTER `num_preguntas`;

-- Valores por defecto: clasificatoria/selectiva/final = 1 intento, simulacro = ilimitado
UPDATE pruebas_config SET max_intentos=0 WHERE tipo_prueba='simulacro';
UPDATE pruebas_config SET max_intentos=1 WHERE tipo_prueba='clasificatoria';
UPDATE pruebas_config SET max_intentos=1 WHERE tipo_prueba='selectiva';
UPDATE pruebas_config SET max_intentos=1 WHERE tipo_prueba='final';

-- 2. Corregir sincronización: activar pruebas_config cuando sección esté activa
-- (Soluciona el bug de clasificatoria bloqueada aunque se haya activado la sección)
UPDATE pruebas_config SET habilitada=1
  WHERE tipo_prueba IN ('clasificatoria','selectiva','final')
  AND tipo_prueba IN (SELECT nombre FROM secciones WHERE habilitada=1);

-- 3. Asegurarse que la columna grado exista en usuarios
ALTER TABLE `usuarios`
  ADD COLUMN IF NOT EXISTS `grado` TINYINT UNSIGNED DEFAULT NULL AFTER `curso`;

-- 4. Asegurarse que institucion_id exista en usuarios
ALTER TABLE `usuarios`
  ADD COLUMN IF NOT EXISTS `institucion_id` INT DEFAULT NULL AFTER `institucion`;

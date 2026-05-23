-- ╔══════════════════════════════════════════════════════════════════╗
-- ║  BIFFI OLIMPIADAS — MIGRACIÓN: Tipo de prueba + Tiempo límite   ║
-- ║  Ejecutar en phpMyAdmin sobre la BD olimpiadas_pro               ║
-- ╚══════════════════════════════════════════════════════════════════╝
USE `olimpiadas_pro`;

-- 1. Tipo de prueba en cada pregunta
ALTER TABLE `preguntas`
  ADD COLUMN IF NOT EXISTS `tipo_prueba`
    ENUM('simulacro','clasificatoria','selectiva','final') NOT NULL DEFAULT 'simulacro'
    AFTER `grupo_grado`;

-- 2. Tiempo límite (minutos) y descripción en secciones
ALTER TABLE `secciones`
  ADD COLUMN IF NOT EXISTS `tiempo_limite` SMALLINT UNSIGNED DEFAULT 0 AFTER `fecha_cierre`,
  ADD COLUMN IF NOT EXISTS `descripcion`   VARCHAR(300) DEFAULT '' AFTER `tiempo_limite`,
  ADD COLUMN IF NOT EXISTS `max_intentos`  TINYINT UNSIGNED DEFAULT 0 AFTER `descripcion`;

-- 3. Actualizar secciones existentes con valores por defecto
UPDATE secciones SET
  tiempo_limite = 0,
  descripcion   = 'Práctica libre para entrenar antes de las pruebas oficiales.',
  max_intentos  = 0
WHERE nombre = 'simulacros';

UPDATE secciones SET
  tiempo_limite = 60,
  descripcion   = 'Primera prueba oficial. Clasifica a los mejores estudiantes.',
  max_intentos  = 1
WHERE nombre = 'clasificatoria';

UPDATE secciones SET
  tiempo_limite = 90,
  descripcion   = 'Segunda fase. Solo para estudiantes clasificados.',
  max_intentos  = 1
WHERE nombre = 'selectiva';

UPDATE secciones SET
  tiempo_limite = 120,
  descripcion   = 'Gran final de las XVIII Olimpiadas Biffi 2026.',
  max_intentos  = 1
WHERE nombre = 'final';

UPDATE secciones SET
  tiempo_limite = 0,
  descripcion   = 'Banco de recursos, PDFs y material de estudio.',
  max_intentos  = 0
WHERE nombre = 'biblioteca';

-- 4. Las preguntas existentes ya tienen tipo_prueba='simulacro' por defecto (ENUM default)
-- Para mover preguntas a otro tipo ejecuta p.ej:
--   UPDATE preguntas SET tipo_prueba='clasificatoria' WHERE id IN (5,6,7);

-- ╔══════════════════════════════════════════════════════════════╗
-- ║  BIFFI OLIMPIADAS v3 — MIGRACIÓN: Grupos por grado         ║
-- ║  Ejecutar en phpMyAdmin sobre la BD olimpiadas_pro          ║
-- ╚══════════════════════════════════════════════════════════════╝
USE `olimpiadas_pro`;

-- 1. Agregar columna 'grado' a usuarios (grado escolar real: 4,5,6,7,8,9,10,11)
ALTER TABLE `usuarios`
  ADD COLUMN IF NOT EXISTS `grado` TINYINT UNSIGNED DEFAULT NULL AFTER `curso`;

-- 2. Agregar columna 'grupo_grado' a preguntas (qué grupo de grados usa esta pregunta)
ALTER TABLE `preguntas`
  ADD COLUMN IF NOT EXISTS `grupo_grado`
    ENUM('4-5','6-7','8-9','10-11') NOT NULL DEFAULT '10-11' AFTER `nivel`;

-- 3. Actualizar grado de los estudiantes de prueba según su curso
UPDATE usuarios SET grado=10 WHERE usuario='maria.perez';
UPDATE usuarios SET grado=10 WHERE usuario='juan.garcia';
UPDATE usuarios SET grado=10 WHERE usuario='laura.lopez';
UPDATE usuarios SET grado=10 WHERE usuario='diego.rodriguez';
UPDATE usuarios SET grado=11 WHERE usuario='sofia.hernandez';
UPDATE usuarios SET grado=11 WHERE usuario='miguel.torres';
UPDATE usuarios SET grado=9  WHERE usuario='valentina.vargas';
UPDATE usuarios SET grado=9  WHERE usuario='santiago.morales';
UPDATE usuarios SET grado=11 WHERE usuario='isabella.diaz';
UPDATE usuarios SET grado=10 WHERE usuario='sebastian.ruiz';

-- 4. Asignar grupo_grado a las preguntas existentes → quedan en 10-11 por defecto (ya está)
--    Si quieres moverlas a otro grupo, ejecuta p.ej:
--    UPDATE preguntas SET grupo_grado='8-9' WHERE id IN (1,2,3);

-- 5. Agregar preguntas de ejemplo para los otros grupos ─────────────────
-- GRUPO 6-7
INSERT INTO `preguntas`
  (pregunta,op1,op2,op3,op4,correcta,nivel,grupo_grado,tema,explicacion) VALUES
('¿Cuánto es $5 \times 8$?','35','40','45','50','40','basico','6-7','Aritmética','5×8=40.'),
('¿Cuánto es $3^3$?','6','9','27','18','27','basico','6-7','Potenciación','3³=27.'),
('¿Área de un rectángulo de 6×4?','20','24','18','28','24','basico','6-7','Geometría','A=6×4=24.'),
('¿Cuánto es $\frac{1}{2} + \frac{1}{4}$?','$\frac{3}{4}$','$\frac{1}{4}$','$\frac{2}{6}$','$\frac{1}{3}$','$\frac{3}{4}$','basico','6-7','Fracciones','$\frac{2}{4}+\frac{1}{4}=\frac{3}{4}$.'),
('¿Cuántos segundos hay en 2 minutos?','100','60','120','180','120','basico','6-7','Medidas','2×60=120 segundos.'),
('¿Cuánto es el 10% de 50?','5','10','15','20','5','medio','6-7','Porcentajes','10%×50=5.'),
('¿Perímetro de triángulo equilátero de lado 5?','10','15','20','25','15','medio','6-7','Geometría','P=3×5=15.');

-- GRUPO 8-9
INSERT INTO `preguntas`
  (pregunta,op1,op2,op3,op4,correcta,nivel,grupo_grado,tema,explicacion) VALUES
('¿Cuánto es $\sqrt{144}$?','10','11','12','13','12','basico','8-9','Álgebra','√144=12 porque 12²=144.'),
('Resuelve: $2x + 5 = 13$','3','4','5','6','4','basico','8-9','Ecuaciones','2x=8, x=4.'),
('¿Cuánto es $(-3)^2$?','$-9$','$9$','$-6$','$6$','$9$','basico','8-9','Aritmética','(-3)²=9.'),
('¿Pendiente de la recta $y=3x-2$?','$-2$','$3$','$2$','$-3$','$3$','medio','8-9','Álgebra','Forma y=mx+b; m=3.'),
('¿Área de círculo de radio 5? (usa $\pi\approx3.14$)','$62.8$','$78.5$','$31.4$','$157$','$78.5$','medio','8-9','Geometría','A=πr²=3.14×25=78.5.'),
('¿MCM de 6 y 9?','12','18','24','36','18','basico','8-9','MCD-MCM','MCM(6,9)=18.');

-- GRUPO 4-5
INSERT INTO `preguntas`
  (pregunta,op1,op2,op3,op4,correcta,nivel,grupo_grado,tema,explicacion) VALUES
('¿Cuánto es 7 + 8?','13','14','15','16','15','basico','4-5','Suma','7+8=15.'),
('¿Cuánto es 20 − 8?','10','12','11','13','12','basico','4-5','Resta','20−8=12.'),
('¿Cuánto es 4 × 3?','10','12','14','16','12','basico','4-5','Multiplicación','4×3=12.'),
('¿Cuánto es 15 ÷ 3?','4','5','6','3','5','basico','4-5','División','15÷3=5.'),
('¿Cuántos lados tiene un triángulo?','2','3','4','5','3','basico','4-5','Geometría','Un triángulo tiene 3 lados.'),
('¿Cuál es el número que sigue: 2, 4, 6, 8, __?','9','10','11','12','10','basico','4-5','Patrones','Serie de pares: +2 cada vez.');

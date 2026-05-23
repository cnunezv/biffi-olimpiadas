-- ╔══════════════════════════════════════════════════════════════╗
-- ║  BIFFI OLIMPIADAS v3 — Base de datos completa               ║
-- ╚══════════════════════════════════════════════════════════════╝
CREATE DATABASE IF NOT EXISTS `olimpiadas_pro` CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish_ci;
USE `olimpiadas_pro`;

CREATE TABLE IF NOT EXISTS `usuarios` (
  `id`          INT AUTO_INCREMENT PRIMARY KEY,
  `nombre`      VARCHAR(100) NOT NULL,
  `apellido`    VARCHAR(100) NOT NULL,
  `usuario`     VARCHAR(60)  NOT NULL UNIQUE,
  `correo`      VARCHAR(150) NOT NULL UNIQUE,
  `contrasena`  VARCHAR(255) NOT NULL,
  `rol`         ENUM('admin','docente','estudiante') DEFAULT 'estudiante',
  `nivel`       ENUM('basico','medio','avanzado') DEFAULT 'basico',
  `curso`       VARCHAR(60)  DEFAULT '',
  `institucion` VARCHAR(150) DEFAULT 'Colegio Biffi',
  `activo`      TINYINT(1) DEFAULT 1,
  `creado_en`   DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `preguntas` (
  `id`          INT AUTO_INCREMENT PRIMARY KEY,
  `pregunta`    TEXT NOT NULL,
  `imagen_url`  VARCHAR(500) DEFAULT '',
  `op1`         VARCHAR(300) NOT NULL,
  `op2`         VARCHAR(300) NOT NULL,
  `op3`         VARCHAR(300) NOT NULL,
  `op4`         VARCHAR(300) DEFAULT '',
  `correcta`    VARCHAR(300) NOT NULL,
  `nivel`       ENUM('basico','medio','avanzado') DEFAULT 'basico',
  `tema`        VARCHAR(100) DEFAULT 'General',
  `explicacion` TEXT DEFAULT NULL,
  `creado_en`   DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `resultados` (
  `id`          INT AUTO_INCREMENT PRIMARY KEY,
  `usuario_id`  INT NOT NULL,
  `nivel`       VARCHAR(30) NOT NULL,
  `puntaje`     INT DEFAULT 0,
  `total`       INT DEFAULT 0,
  `tiempo_seg`  INT DEFAULT 0,
  `detalle`     TEXT DEFAULT NULL,
  `fecha`       DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`usuario_id`) REFERENCES `usuarios`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `mensajes` (
  `id`            INT AUTO_INCREMENT PRIMARY KEY,
  `de_id`         INT NOT NULL,
  `para_id`       INT NOT NULL,
  `asunto`        VARCHAR(200) NOT NULL,
  `cuerpo`        TEXT NOT NULL,
  `leido`         TINYINT(1) DEFAULT 0,
  `eliminado_de`  TINYINT(1) DEFAULT 0,
  `eliminado_para` TINYINT(1) DEFAULT 0,
  `enviado_en`    DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`de_id`)   REFERENCES `usuarios`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`para_id`) REFERENCES `usuarios`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `recursos` (
  `id`          INT AUTO_INCREMENT PRIMARY KEY,
  `titulo`      VARCHAR(200) NOT NULL,
  `descripcion` TEXT,
  `tipo`        ENUM('pdf','video','zip','enlace','imagen') DEFAULT 'pdf',
  `archivo`     VARCHAR(500) NOT NULL,
  `subido_por`  INT DEFAULT NULL,
  `visible`     TINYINT(1) DEFAULT 1,
  `descargas`   INT DEFAULT 0,
  `creado_en`   DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`subido_por`) REFERENCES `usuarios`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `secciones` (
  `id`            INT AUTO_INCREMENT PRIMARY KEY,
  `nombre`        VARCHAR(60)  NOT NULL UNIQUE,
  `etiqueta`      VARCHAR(100) NOT NULL,
  `habilitada`    TINYINT(1) DEFAULT 0,
  `fecha_apertura` DATETIME DEFAULT NULL,
  `fecha_cierre`  DATETIME DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Usuarios (contraseñas generadas por fix.php)
INSERT INTO `usuarios` (nombre,apellido,usuario,correo,contrasena,rol) VALUES
('Carlos','Núñez','carlos.nunez','carlos@biffi.edu.co','PLACEHOLDER','admin');

INSERT INTO `usuarios` (nombre,apellido,usuario,correo,contrasena,rol,institucion) VALUES
('Fabiana','Ariza','fabiana.ariza','fabiana@biffi.edu.co','PLACEHOLDER','docente','Colegio Biffi'),
('Vanessa','Berrío','vanessa.berrio','vanessa@biffi.edu.co','PLACEHOLDER','docente','Colegio Biffi'),
('Andrés','Martínez','andres.martinez','andres@biffi.edu.co','PLACEHOLDER','docente','Colegio Biffi');

INSERT INTO `usuarios` (nombre,apellido,usuario,correo,contrasena,rol,nivel,curso) VALUES
('María','Pérez','maria.perez','maria@biffi.edu.co','PLACEHOLDER','estudiante','basico','10-A'),
('Juan','García','juan.garcia','juan@biffi.edu.co','PLACEHOLDER','estudiante','basico','10-A'),
('Laura','López','laura.lopez','laura@biffi.edu.co','PLACEHOLDER','estudiante','medio','10-B'),
('Diego','Rodríguez','diego.rodriguez','diego@biffi.edu.co','PLACEHOLDER','estudiante','medio','10-B'),
('Sofía','Hernández','sofia.hernandez','sofia@biffi.edu.co','PLACEHOLDER','estudiante','avanzado','11-A'),
('Miguel','Torres','miguel.torres','miguel@biffi.edu.co','PLACEHOLDER','estudiante','avanzado','11-A'),
('Valentina','Vargas','valentina.vargas','valentina@biffi.edu.co','PLACEHOLDER','estudiante','basico','9-A'),
('Santiago','Morales','santiago.morales','santiago@biffi.edu.co','PLACEHOLDER','estudiante','medio','9-B'),
('Isabella','Díaz','isabella.diaz','isabella@biffi.edu.co','PLACEHOLDER','estudiante','avanzado','11-B'),
('Sebastián','Ruiz','sebastian.ruiz','sebastian@biffi.edu.co','PLACEHOLDER','estudiante','basico','10-C');

-- Preguntas básico
INSERT INTO `preguntas` (pregunta,op1,op2,op3,op4,correcta,nivel,tema,explicacion) VALUES
('¿Cuánto es 9 × 7?','54','63','72','81','63','basico','Aritmética','9×7 = 63.'),
('¿Raíz cuadrada de 81?','7','8','9','6','9','basico','Álgebra','√81 = 9 porque 9² = 81.'),
('¿Cuánto es 12 × 12?','124','144','132','148','144','basico','Aritmética','12×12 = 144.'),
('¿Cuánto es 2⁵?','16','32','64','8','32','basico','Potenciación','2⁵ = 32.'),
('¿Cuál es el número primo entre 14 y 18?','14','15','16','17','17','basico','Números','17 es primo.'),
('¿Cuánto es 15% de 200?','20','25','30','35','30','basico','Porcentajes','0.15 × 200 = 30.'),
('¿Perímetro de un cuadrado de lado 7?','28','42','49','21','28','basico','Geometría','4 × 7 = 28.'),
('Simplifica: 4/8','1/4','1/2','2/3','3/4','1/2','basico','Fracciones','4/8 = 1/2.'),
('¿Cuántos lados tiene un hexágono?','5','6','7','8','6','basico','Geometría','Hexa = 6.'),
('¿Cuánto es √(25 + 144)?','12','13','14','15','13','basico','Álgebra','√169 = 13.');

-- Preguntas medio
INSERT INTO `preguntas` (pregunta,op1,op2,op3,op4,correcta,nivel,tema,explicacion) VALUES
('¿Cuántos divisores tiene 36?','7','8','9','10','9','medio','Divisores','1,2,3,4,6,9,12,18,36 → 9.'),
('Si f(x)=2x+3, ¿cuánto es f(5)?','10','13','15','8','13','medio','Álgebra','2(5)+3=13.'),
('¿MCD de 48 y 72?','12','24','6','36','24','medio','MCD-MCM','MCD=24.'),
('¿Diagonales de un hexágono?','6','8','9','12','9','medio','Geometría','n(n-3)/2=9.'),
('Resuelve: 3x − 7 = 14','5','6','7','8','7','medio','Ecuaciones','3x=21, x=7.'),
('¿mcm de 12 y 18?','24','36','48','72','36','medio','MCD-MCM','mcm=36.'),
('¿Área triángulo base=10 altura=6?','30','60','15','45','30','medio','Geometría','b×h/2=30.'),
('¿Cuánto es log₁₀(1000)?','2','3','4','5','3','medio','Logaritmos','10³=1000.');

-- Preguntas avanzado
INSERT INTO `preguntas` (pregunta,op1,op2,op3,op4,correcta,nivel,tema,explicacion) VALUES
('¿Cuántos primos entre 1 y 50?','13','14','15','16','15','avanzado','Números','Son 15 primos.'),
('Si log₂(32)=x, ¿x=?','4','5','6','7','5','avanzado','Logaritmos','2⁵=32.'),
('¿Suma ángulos internos de heptágono?','900°','720°','1080°','540°','900°','avanzado','Geometría','(7-2)×180=900.'),
('¿Ceros al final de 100!?','24','25','26','22','24','avanzado','Combinatoria','⌊100/5⌋+⌊100/25⌋=24.'),
('Mayor raíz de x²-5x+6=0','2','3','4','6','3','avanzado','Ecuaciones','x=3 o x=2, mayor es 3.');

-- Secciones
INSERT INTO `secciones` (nombre,etiqueta,habilitada) VALUES
('general','General',1),('simulacros','Simulacros',1),
('clasificatoria','Prueba Clasificatoria',0),('selectiva','Prueba Selectiva',0),
('final','Prueba Final',0),('biblioteca','Biblioteca',1);

-- =====================================================================
-- BIENESTAR MUNICIPAL — MÓDULO CLÍNICO
-- Base de datos MariaDB
-- =====================================================================

CREATE DATABASE IF NOT EXISTS bienestar_clinico -- Crear la base de datos si no existe
  CHARACTER SET utf8mb4 -- Soporte completo para emojis y caracteres multilingües
  COLLATE utf8mb4_unicode_ci; -- Ordenamiento que respeta acentos y mayúsculas/minúsculas

USE bienestar_clinico; -- Seleccionar la base de datos para las siguientes operaciones

-- ---------------------------------------------------------------------
-- USUARIOS
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS usuarios ( -- tabla de usuarios registrados
  id            INT AUTO_INCREMENT PRIMARY KEY, -- ID único para cada usuario
  nombre        VARCHAR(120)        NOT NULL,  -- nombre completo
  email         VARCHAR(160) UNIQUE NOT NULL,  -- correo electrónico 
  password_hash VARCHAR(255)        NOT NULL,  -- hash de la contraseña para seguridad
  edad          INT,                           -- edad en años
  sexo          ENUM('M','F','Otro'),          -- opciones de sexo (masculino, femenino, otro)
  peso          DECIMAL(5,2),                  -- en kg
  altura        DECIMAL(5,2),                  -- en cm
  creado_en     TIMESTAMP DEFAULT CURRENT_TIMESTAMP -- fecha de creación del usuario
) ENGINE=InnoDB;                                           -- motor de almacenamiento que soporta transacciones y claves foráneas

-- ---------------------------------------------------------------------
-- CUESTIONARIOS (catálogo)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS cuestionarios (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  slug        VARCHAR(60) UNIQUE NOT NULL,
  titulo      VARCHAR(160) NOT NULL,
  descripcion TEXT,
  icono       VARCHAR(40),       -- nombre semántico para mapear iconos en front
  color       VARCHAR(20),       -- token de color (ej. "sage", "coral")
  activo      TINYINT(1) DEFAULT 1
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- PREGUNTAS
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS preguntas (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  cuestionario_id INT NOT NULL,
  texto           VARCHAR(255) NOT NULL,
  orden           INT DEFAULT 0,
  CONSTRAINT fk_pregunta_cuestionario
    FOREIGN KEY (cuestionario_id) REFERENCES cuestionarios(id)
    ON DELETE CASCADE 
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- OPCIONES DE RESPUESTA (todas las preguntas son escala 1-5 con texto)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS opciones (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  pregunta_id INT NOT NULL,
  texto       VARCHAR(160) NOT NULL,
  valor       INT NOT NULL,        -- 1 a 5
  orden       INT DEFAULT 0,
  CONSTRAINT fk_opcion_pregunta
    FOREIGN KEY (pregunta_id) REFERENCES preguntas(id)
    ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- RESULTADOS (un resultado = una sesión completa de cuestionario)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS resultados (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id      INT NOT NULL,
  cuestionario_id INT NOT NULL,
  puntaje_total   INT NOT NULL,
  puntaje_max     INT NOT NULL,
  porcentaje      DECIMAL(5,2) NOT NULL,
  nivel           ENUM('bajo','medio','alto') NOT NULL,
  creado_en       TIMESTAMP DEFAULT CURRENT_TIMESTAMP, -- fecha y hora de la sesión de cuestionario
  CONSTRAINT fk_resultado_usuario                      -- relación con usuario que hizo el cuestionario
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE, -- si se borra el usuario, se borran sus resultados
  CONSTRAINT fk_resultado_cuestionario                                  -- relación con el cuestionario que se respondió
    FOREIGN KEY (cuestionario_id) REFERENCES cuestionarios(id) ON DELETE CASCADE -- si se borra el cuestionario, se borran los resultados asociados
) ENGINE=InnoDB;                                                                  -- InnoDB para soportar transacciones y claves foráneas

-- ---------------------------------------------------------------------
-- RESPUESTAS DETALLADAS
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS respuestas ( -- cada fila es la respuesta a una pregunta específica dentro de un resultado
  id            INT AUTO_INCREMENT PRIMARY KEY,
  resultado_id  INT NOT NULL,
  pregunta_id   INT NOT NULL,
  opcion_id     INT NOT NULL,
  valor         INT NOT NULL,
  CONSTRAINT fk_resp_resultado FOREIGN KEY (resultado_id) REFERENCES resultados(id) ON DELETE CASCADE, -- si se borra el resultado, se borran sus respuestas detalladas
  CONSTRAINT fk_resp_pregunta  FOREIGN KEY (pregunta_id)  REFERENCES preguntas(id)  ON DELETE CASCADE, -- si se borra la pregunta, se borran las respuestas asociadas
  CONSTRAINT fk_resp_opcion    FOREIGN KEY (opcion_id)    REFERENCES opciones(id)   ON DELETE CASCADE  -- si se borra la opción, se borran las respuestas asociadas (aunque esto es raro porque las opciones suelen ser fijas, pero es para mantener integridad referencial
) ENGINE=InnoDB;

-- =====================================================================
-- DATOS SEED — Cuestionarios + preguntas + opciones
-- =====================================================================

-- 7 cuestionarios (el último "riesgos" fue agregado posteriormente)
INSERT INTO cuestionarios (slug, titulo, descripcion, icono, color) VALUES 
('sueno',       'Calidad de sueño',     'Evalúa cómo descansas en las últimas dos semanas.',  'moon',       'indigo'),
('cansancio',   'Nivel de cansancio',   'Mide tu fatiga general y energía diaria.',           'battery',    'amber'),
('hidratacion', 'Hidratación',          'Revisa tus hábitos diarios de consumo de agua.',     'droplet',    'sky'),
('dolor',       'Dolores físicos',      'Identifica molestias frecuentes en tu cuerpo.',      'activity',   'rose'),
('energia',     'Nivel de energía',     'Mide cómo te sientes durante el día.',               'zap',        'lime'),
('habitos',     'Hábitos saludables',   'Repaso general de rutinas que cuidan tu bienestar.', 'leaf',       'emerald'),
('riesgos',     'Riesgos generales de salud',
                                        'Identifica hábitos y antecedentes familiares que pueden influir en tu salud a largo plazo. No es un diagnóstico.',
                                                                                              'heart-pulse','rose');

-- ---- 1. CALIDAD DE SUEÑO -------------------------------------------
INSERT INTO preguntas (cuestionario_id, texto, orden) VALUES
(1, '¿Cuántas horas duermes en promedio por noche?', 1),
(1, '¿Te despiertas varias veces durante la noche?', 2),
(1, '¿Te cuesta trabajo conciliar el sueño?', 3),
(1, '¿Te sientes descansado al levantarte?', 4),
(1, '¿Usas pantallas justo antes de dormir?', 5);

-- 5 opciones por cada pregunta (1=peor, 5=mejor para bienestar)
INSERT INTO opciones (pregunta_id, texto, valor, orden) VALUES
(1,'Menos de 4 horas',1,1),(1,'Entre 4 y 5 horas',2,2),(1,'Entre 5 y 6 horas',3,3),(1,'Entre 6 y 7 horas',4,4),(1,'7 horas o más',5,5), 
(2,'Siempre',1,1),(2,'Casi siempre',2,2),(2,'A veces',3,3),(2,'Casi nunca',4,4),(2,'Nunca',5,5),
(3,'Siempre',1,1),(3,'Casi siempre',2,2),(3,'A veces',3,3),(3,'Casi nunca',4,4),(3,'Nunca',5,5),
(4,'Nunca',1,1),(4,'Casi nunca',2,2),(4,'A veces',3,3),(4,'Casi siempre',4,4),(4,'Siempre',5,5),
(5,'Más de 2 horas',1,1),(5,'Entre 1 y 2 horas',2,2),(5,'30 a 60 min',3,3),(5,'Menos de 30 min',4,4),(5,'No las uso',5,5);

-- ---- 2. CANSANCIO -------------------------------------------------
INSERT INTO preguntas (cuestionario_id, texto, orden) VALUES
(2, '¿Te sientes agotado durante el día?', 1),
(2, '¿Necesitas siestas largas para funcionar?', 2),
(2, '¿Te cuesta concentrarte por falta de energía?', 3),
(2, '¿Cómo calificarías tu energía al mediodía?', 4),
(2, '¿Tu cansancio interfiere con tus actividades?', 5);

INSERT INTO opciones (pregunta_id, texto, valor, orden) VALUES
(6,'Siempre',1,1),(6,'Casi siempre',2,2),(6,'A veces',3,3),(6,'Casi nunca',4,4),(6,'Nunca',5,5),
(7,'Diario',1,1),(7,'Casi diario',2,2),(7,'A veces',3,3),(7,'Rara vez',4,4),(7,'Nunca',5,5),
(8,'Siempre',1,1),(8,'Casi siempre',2,2),(8,'A veces',3,3),(8,'Casi nunca',4,4),(8,'Nunca',5,5),
(9,'Muy baja',1,1),(9,'Baja',2,2),(9,'Regular',3,3),(9,'Buena',4,4),(9,'Excelente',5,5),
(10,'Mucho',1,1),(10,'Bastante',2,2),(10,'A veces',3,3),(10,'Poco',4,4),(10,'Nada',5,5);

-- ---- 3. HIDRATACIÓN -----------------------------------------------
INSERT INTO preguntas (cuestionario_id, texto, orden) VALUES
(3, '¿Cuántos vasos de agua tomas al día?', 1),
(3, '¿Cargas botella de agua contigo?', 2),
(3, '¿Sientes la boca seca con frecuencia?', 3),
(3, '¿Tu orina suele ser color claro?', 4),
(3, '¿Sustituyes el agua por refrescos o jugos?', 5);

INSERT INTO opciones (pregunta_id, texto, valor, orden) VALUES
(11,'Menos de 2',1,1),(11,'Entre 2 y 4',2,2),(11,'Entre 4 y 6',3,3),(11,'Entre 6 y 8',4,4),(11,'8 o más',5,5),
(12,'Nunca',1,1),(12,'Rara vez',2,2),(12,'A veces',3,3),(12,'Casi siempre',4,4),(12,'Siempre',5,5),
(13,'Siempre',1,1),(13,'Casi siempre',2,2),(13,'A veces',3,3),(13,'Casi nunca',4,4),(13,'Nunca',5,5),
(14,'Nunca',1,1),(14,'Rara vez',2,2),(14,'A veces',3,3),(14,'Casi siempre',4,4),(14,'Siempre',5,5),
(15,'Siempre',1,1),(15,'Casi siempre',2,2),(15,'A veces',3,3),(15,'Casi nunca',4,4),(15,'Nunca',5,5);

-- ---- 4. DOLORES FÍSICOS -------------------------------------------
INSERT INTO preguntas (cuestionario_id, texto, orden) VALUES
(4, '¿Sientes dolor de cabeza con frecuencia?', 1),
(4, '¿Tienes molestias en cuello o espalda?', 2),
(4, '¿Sientes tensión muscular al final del día?', 3),
(4, '¿Has tenido dolores articulares recientes?', 4),
(4, '¿El dolor te impide realizar tus actividades?', 5);

INSERT INTO opciones (pregunta_id, texto, valor, orden) VALUES
(16,'Diario',1,1),(16,'Casi diario',2,2),(16,'Semanal',3,3),(16,'Rara vez',4,4),(16,'Nunca',5,5),
(17,'Siempre',1,1),(17,'Casi siempre',2,2),(17,'A veces',3,3),(17,'Casi nunca',4,4),(17,'Nunca',5,5),
(18,'Siempre',1,1),(18,'Casi siempre',2,2),(18,'A veces',3,3),(18,'Casi nunca',4,4),(18,'Nunca',5,5),
(19,'Constantes',1,1),(19,'Frecuentes',2,2),(19,'Ocasionales',3,3),(19,'Raros',4,4),(19,'Ninguno',5,5),
(20,'Siempre',1,1),(20,'Casi siempre',2,2),(20,'A veces',3,3),(20,'Casi nunca',4,4),(20,'Nunca',5,5);

-- ---- 5. NIVEL DE ENERGÍA ------------------------------------------
INSERT INTO preguntas (cuestionario_id, texto, orden) VALUES
(5, '¿Cómo te sientes al despertar?', 1),
(5, '¿Te sientes motivado durante el día?', 2),
(5, '¿Tienes energía para hacer ejercicio?', 3),
(5, '¿Cómo es tu ánimo general?', 4),
(5, '¿Disfrutas tus actividades diarias?', 5);

INSERT INTO opciones (pregunta_id, texto, valor, orden) VALUES
(21,'Muy cansado',1,1),(21,'Cansado',2,2),(21,'Normal',3,3),(21,'Bien',4,4),(21,'Excelente',5,5),
(22,'Nada',1,1),(22,'Poco',2,2),(22,'A veces',3,3),(22,'Casi siempre',4,4),(22,'Siempre',5,5),
(23,'Nunca',1,1),(23,'Rara vez',2,2),(23,'A veces',3,3),(23,'Casi siempre',4,4),(23,'Siempre',5,5),
(24,'Muy bajo',1,1),(24,'Bajo',2,2),(24,'Estable',3,3),(24,'Bueno',4,4),(24,'Excelente',5,5),
(25,'Nada',1,1),(25,'Poco',2,2),(25,'A veces',3,3),(25,'Casi siempre',4,4),(25,'Siempre',5,5);

-- ---- 6. HÁBITOS SALUDABLES ----------------------------------------
INSERT INTO preguntas (cuestionario_id, texto, orden) VALUES
(6, '¿Comes frutas y verduras al día?', 1),
(6, '¿Realizas actividad física semanal?', 2),
(6, '¿Evitas alimentos ultraprocesados?', 3),
(6, '¿Tienes horarios regulares de comida?', 4),
(6, '¿Dedicas tiempo a relajarte?', 5);

INSERT INTO opciones (pregunta_id, texto, valor, orden) VALUES
(26,'Nunca',1,1),(26,'Rara vez',2,2),(26,'A veces',3,3),(26,'Casi siempre',4,4),(26,'Diario',5,5),
(27,'Nunca',1,1),(27,'1 día',2,2),(27,'2-3 días',3,3),(27,'4-5 días',4,4),(27,'Todos los días',5,5),
(28,'Nunca',1,1),(28,'Rara vez',2,2),(28,'A veces',3,3),(28,'Casi siempre',4,4),(28,'Siempre',5,5),
(29,'Nunca',1,1),(29,'Rara vez',2,2),(29,'A veces',3,3),(29,'Casi siempre',4,4),(29,'Siempre',5,5),
(30,'Nunca',1,1),(30,'Rara vez',2,2),(30,'A veces',3,3),(30,'Casi siempre',4,4),(30,'Diario',5,5);

-- =====================================================================
-- AMPLIACIÓN: 2 preguntas extra (P6 y P7) por cada cuestionario original
-- y el cuestionario nuevo "riesgos" con 7 preguntas.
-- Se agregan al final para no romper los IDs ya existentes (1-30).
-- Las preguntas nuevas tomarán IDs 31..49 en orden secuencial.
-- =====================================================================

-- ---- Cuestionario 1 (sueño) — preguntas extra: IDs 31, 32 ------------
INSERT INTO preguntas (cuestionario_id, texto, orden) VALUES
(1, '¿Sueles tener pesadillas o sueños que te despiertan?', 6),
(1, '¿Tomas café o bebidas con cafeína después de las 5 pm?', 7);

INSERT INTO opciones (pregunta_id, texto, valor, orden) VALUES
(31,'Siempre',1,1),(31,'Casi siempre',2,2),(31,'A veces',3,3),(31,'Casi nunca',4,4),(31,'Nunca',5,5),
(32,'Siempre',1,1),(32,'Casi siempre',2,2),(32,'A veces',3,3),(32,'Casi nunca',4,4),(32,'Nunca',5,5);

-- ---- Cuestionario 2 (cansancio) — preguntas extra: IDs 33, 34 --------
INSERT INTO preguntas (cuestionario_id, texto, orden) VALUES
(2, '¿Sientes que tu cansancio mejora después de descansar?', 6),
(2, '¿El cansancio te ha hecho faltar al trabajo o escuela?', 7);

INSERT INTO opciones (pregunta_id, texto, valor, orden) VALUES
(33,'Nunca',1,1),(33,'Rara vez',2,2),(33,'A veces',3,3),(33,'Casi siempre',4,4),(33,'Siempre',5,5),
(34,'Mucho',1,1),(34,'Bastante',2,2),(34,'A veces',3,3),(34,'Poco',4,4),(34,'Nada',5,5);

-- ---- Cuestionario 3 (hidratación) — preguntas extra: IDs 35, 36 ------
INSERT INTO preguntas (cuestionario_id, texto, orden) VALUES
(3, '¿Te acuerdas de tomar agua sin que alguien te recuerde?', 6),
(3, '¿Tomas agua durante o después de hacer ejercicio?', 7);

INSERT INTO opciones (pregunta_id, texto, valor, orden) VALUES
(35,'Nunca',1,1),(35,'Rara vez',2,2),(35,'A veces',3,3),(35,'Casi siempre',4,4),(35,'Siempre',5,5),
(36,'Nunca',1,1),(36,'Rara vez',2,2),(36,'A veces',3,3),(36,'Casi siempre',4,4),(36,'Siempre',5,5);

-- ---- Cuestionario 4 (dolor) — preguntas extra: IDs 37, 38 ------------
INSERT INTO preguntas (cuestionario_id, texto, orden) VALUES
(4, '¿Pasas muchas horas seguidas sentado o en la misma postura?', 6),
(4, '¿Haces estiramientos o pausas activas durante el día?', 7);

INSERT INTO opciones (pregunta_id, texto, valor, orden) VALUES
(37,'Siempre',1,1),(37,'Casi siempre',2,2),(37,'A veces',3,3),(37,'Casi nunca',4,4),(37,'Nunca',5,5),
(38,'Nunca',1,1),(38,'Rara vez',2,2),(38,'A veces',3,3),(38,'Casi siempre',4,4),(38,'Siempre',5,5);

-- ---- Cuestionario 5 (energía) — preguntas extra: IDs 39, 40 ----------
INSERT INTO preguntas (cuestionario_id, texto, orden) VALUES
(5, '¿Te sientes con ganas de empezar el día al despertar?', 6),
(5, '¿Sientes que tu energía baja drásticamente en la tarde?', 7);

INSERT INTO opciones (pregunta_id, texto, valor, orden) VALUES
(39,'Nunca',1,1),(39,'Rara vez',2,2),(39,'A veces',3,3),(39,'Casi siempre',4,4),(39,'Siempre',5,5),
(40,'Siempre',1,1),(40,'Casi siempre',2,2),(40,'A veces',3,3),(40,'Casi nunca',4,4),(40,'Nunca',5,5);

-- ---- Cuestionario 6 (hábitos) — preguntas extra: IDs 41, 42 ----------
INSERT INTO preguntas (cuestionario_id, texto, orden) VALUES
(6, '¿Pasas tiempo al aire libre o tomando sol?', 6),
(6, '¿Limitas el tiempo que pasas en pantallas fuera del trabajo?', 7);

INSERT INTO opciones (pregunta_id, texto, valor, orden) VALUES
(41,'Nunca',1,1),(41,'Rara vez',2,2),(41,'A veces',3,3),(41,'Casi siempre',4,4),(41,'Diario',5,5),
(42,'Nunca',1,1),(42,'Rara vez',2,2),(42,'A veces',3,3),(42,'Casi siempre',4,4),(42,'Siempre',5,5);

-- ---- Cuestionario 7 (riesgos) — 7 preguntas nuevas: IDs 43..49 -------
INSERT INTO preguntas (cuestionario_id, texto, orden) VALUES
(7, '¿Algún familiar cercano (padres, hermanos) tiene diabetes?', 1),
(7, '¿Algún familiar cercano tiene presión alta o problemas del corazón?', 2),
(7, '¿Con qué frecuencia consumes refrescos o bebidas azucaradas?', 3),
(7, '¿Con qué frecuencia consumes comida muy salada o frituras?', 4),
(7, '¿Te has medido la presión o azúcar en el último año?', 5),
(7, '¿Sientes mareos, visión borrosa o sed excesiva con frecuencia?', 6),
(7, '¿Has notado cambios bruscos de peso sin razón aparente?', 7);

INSERT INTO opciones (pregunta_id, texto, valor, orden) VALUES
(43,'Varios',1,1),(43,'Algunos',2,2),(43,'Solo uno',3,3),(43,'Creo que no',4,4),(43,'Nadie',5,5),
(44,'Varios',1,1),(44,'Algunos',2,2),(44,'Solo uno',3,3),(44,'Creo que no',4,4),(44,'Nadie',5,5),
(45,'Diario',1,1),(45,'Casi diario',2,2),(45,'A veces',3,3),(45,'Rara vez',4,4),(45,'Nunca',5,5),
(46,'Diario',1,1),(46,'Casi diario',2,2),(46,'A veces',3,3),(46,'Rara vez',4,4),(46,'Nunca',5,5),
(47,'Nunca',1,1),(47,'Hace mucho',2,2),(47,'No recuerdo',3,3),(47,'Hace pocos meses',4,4),(47,'Recientemente',5,5),
(48,'Siempre',1,1),(48,'Casi siempre',2,2),(48,'A veces',3,3),(48,'Casi nunca',4,4),(48,'Nunca',5,5),
(49,'Siempre',1,1),(49,'Casi siempre',2,2),(49,'A veces',3,3),(49,'Casi nunca',4,4),(49,'Nunca',5,5);

-- ---------------------------------------------------------------------
-- FICHA (datos clínicos del usuario — relación 1:1 con usuarios)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS ficha (
  id              INT AUTO_INCREMENT PRIMARY KEY,     -- ID único para cada ficha
  usuario_id      INT NOT NULL UNIQUE,                -- 1:1 con usuarios
  sangre          VARCHAR(5),                          -- A+, A-, B+, B-, AB+, AB-, O+, O-
  alergias        TEXT,                                -- lista de alergias separadas por comas (ej. "polen, polvo, gluten")
  medicamentos    TEXT,                                -- lista de medicamentos separados por comas (ej. "ibuprofeno, metformina")
  contacto        VARCHAR(200),                        -- contacto de emergencia
  notas           TEXT,                               -- cualquier información adicional que el usuario quiera registrar
  actualizado_en  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, -- fecha de última actualización automática
  CONSTRAINT fk_ficha_usuario                                                      -- relación con el usuario al que pertenece la ficha
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE             -- si se borra el usuario, se borra su ficha también
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- EVALUACIONES PREVENTIVAS
-- Cada fila es una "foto" de la evaluación integral generada a partir
-- del último resultado de cada cuestionario.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS evaluaciones_preventivas (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id  INT NOT NULL,
  puntaje     INT NOT NULL,                                  -- 0-100
  nivel       ENUM('bajo','moderado','elevado') NOT NULL,
  creado_en   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- FACTORES DE UNA EVALUACIÓN (relación 1:N con evaluaciones_preventivas)
-- Cada fila es un factor detectado dentro de esa evaluación.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS evaluacion_factores (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  evaluacion_id INT NOT NULL,
  factor_id     VARCHAR(30) NOT NULL,                        -- slug del factor (diabetes, insomnio, etc.)
  titulo        VARCHAR(120) NOT NULL,
  puntaje       INT NOT NULL,
  que_significa TEXT NOT NULL,
  FOREIGN KEY (evaluacion_id)
    REFERENCES evaluaciones_preventivas(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- DETALLES DE CADA FACTOR (razones de detección + recomendaciones)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS evaluacion_detalles (
  id        INT AUTO_INCREMENT PRIMARY KEY,
  factor_id INT NOT NULL,
  tipo      ENUM('detectado_por','recomendacion') NOT NULL,
  texto     TEXT NOT NULL,
  orden     INT DEFAULT 0,
  FOREIGN KEY (factor_id)
    REFERENCES evaluacion_factores(id) ON DELETE CASCADE
) ENGINE=InnoDB;

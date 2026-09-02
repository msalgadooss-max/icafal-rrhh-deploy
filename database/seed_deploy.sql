-- =====================================================================
-- Datos semilla para el despliegue de demo (Render). Se ejecuta DESPUES
-- de schema.sql en cada arranque del contenedor (disco efimero -- no
-- incluye ninguna postulacion de prueba real, solo lo minimo para que
-- el equipo pueda loguearse y postular).
-- Clave de todos los usuarios: Clave123!
-- =====================================================================
USE icafal_rrhh;

DELETE FROM usuarios WHERE correo = 'admin@icafal.cl';

INSERT INTO usuarios (nombre, correo, password, rol) VALUES
    ('Juan Perez', 'jterreno@icafal.cl', '$2y$10$7MmTUu6q5icczbMgoV/FzuoDE4lzIUsUA55XA2rMThuze2YgXJbW.', 'Jefe_Terreno'),
    ('Roberto Capataz', 'capataz@icafal.cl', '$2y$10$7MmTUu6q5icczbMgoV/FzuoDE4lzIUsUA55XA2rMThuze2YgXJbW.', 'Capataz'),
    ('Maria Soto', 'administrador@icafal.cl', '$2y$10$7MmTUu6q5icczbMgoV/FzuoDE4lzIUsUA55XA2rMThuze2YgXJbW.', 'Admin_Contrato'),
    ('Luis Vera', 'jao@icafal.cl', '$2y$10$7MmTUu6q5icczbMgoV/FzuoDE4lzIUsUA55XA2rMThuze2YgXJbW.', 'Jefe_Administrativo'),
    ('Pedro Rios', 'pedro@icafal.cl', '$2y$10$7MmTUu6q5icczbMgoV/FzuoDE4lzIUsUA55XA2rMThuze2YgXJbW.', 'Prevencionista'),
    ('Ana Diaz', 'ana@icafal.cl', '$2y$10$7MmTUu6q5icczbMgoV/FzuoDE4lzIUsUA55XA2rMThuze2YgXJbW.', 'Jefe_Bodega'),
    ('Sofia Gerente', 'gerencia@icafal.cl', '$2y$10$7MmTUu6q5icczbMgoV/FzuoDE4lzIUsUA55XA2rMThuze2YgXJbW.', 'Gerencia'),
    ('Carlos Rivas', 'porteria@icafal.cl', '$2y$10$7MmTUu6q5icczbMgoV/FzuoDE4lzIUsUA55XA2rMThuze2YgXJbW.', 'Porteria'),
    ('Desarrollador ICAFAL', 'dev@icafal.cl', '$2y$10$7MmTUu6q5icczbMgoV/FzuoDE4lzIUsUA55XA2rMThuze2YgXJbW.', 'Desarrollador');

-- Se abren algunos cupos de partida para que la demo no arranque vacia.
UPDATE cargos SET cupos_totales = 5, cupos_activos = 5 WHERE nombre_cargo IN ('Jornal Concretero', 'Albañil', 'Carpintero');

-- v9: catalogo de cursos de Prevencion -- placeholder (video de muestra,
-- libre de derechos) hasta que Prevencion (Alfredo) entregue el
-- contenido real grabado en obra; cambiar solo la URL/preguntas, sin
-- tocar codigo, cuando ese contenido este listo.
INSERT INTO cursos_induccion (categoria, titulo, descripcion, duracion_estimada, url, preguntas_evaluacion, orden) VALUES
    ('Seguridad General', 'Inducción general de seguridad en obra', 'Reglas básicas de seguridad que rigen en todas las obras de ICAFAL.', '8 min', 'https://www.w3schools.com/html/mov_bbb.mp4',
        JSON_ARRAY('¿Cuáles son los principales riesgos que identificaste en el video?', '¿Qué debes hacer si detectas una condición insegura en tu área de trabajo?'), 1),
    ('Seguridad General', 'Uso correcto de EPP', 'Cómo usar, cuidar y cuándo reemplazar tu equipo de protección personal.', '6 min', 'https://www.w3schools.com/html/mov_bbb.mp4',
        JSON_ARRAY('Nombra 3 elementos de protección personal obligatorios en tu puesto de trabajo.', '¿Qué haces si tu EPP está dañado o vencido?'), 2),
    ('Seguridad General', 'Riesgos frecuentes y cómo evitarlos', 'Los accidentes más comunes en faena y cómo prevenirlos.', '7 min', 'https://www.w3schools.com/html/mov_bbb.mp4',
        JSON_ARRAY('Menciona un riesgo frecuente visto en el video y cómo se previene.'), 3),
    ('Reglamento Interno', 'Reglamento Interno de Orden, Higiene y Seguridad (RIOHS)', 'Resumen de tus derechos y obligaciones como trabajador de ICAFAL.', '10 min', 'https://www.w3schools.com/html/mov_bbb.mp4',
        JSON_ARRAY('¿Qué obligación te pareció más importante del reglamento?', '¿A quién debes reportar un accidente de trabajo?'), 4),
    ('IRL de la Obra', 'Identificación de Riesgos Laborales de esta obra', 'Riesgos específicos del proyecto/faena a la que ingresas.', '9 min', 'https://www.w3schools.com/html/mov_bbb.mp4',
        JSON_ARRAY('¿Cuáles son los riesgos específicos de esta obra que identificaste?', '¿Qué medida de control se aplica a ese riesgo?'), 5);

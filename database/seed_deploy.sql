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
    ('Maria Soto', 'administrador@icafal.cl', '$2y$10$7MmTUu6q5icczbMgoV/FzuoDE4lzIUsUA55XA2rMThuze2YgXJbW.', 'Admin_Contrato'),
    ('Luis Vera', 'jao@icafal.cl', '$2y$10$7MmTUu6q5icczbMgoV/FzuoDE4lzIUsUA55XA2rMThuze2YgXJbW.', 'Jefe_Administrativo'),
    ('Pedro Rios', 'pedro@icafal.cl', '$2y$10$7MmTUu6q5icczbMgoV/FzuoDE4lzIUsUA55XA2rMThuze2YgXJbW.', 'Prevencionista'),
    ('Ana Diaz', 'ana@icafal.cl', '$2y$10$7MmTUu6q5icczbMgoV/FzuoDE4lzIUsUA55XA2rMThuze2YgXJbW.', 'Jefe_Bodega'),
    ('Sofia Gerente', 'gerencia@icafal.cl', '$2y$10$7MmTUu6q5icczbMgoV/FzuoDE4lzIUsUA55XA2rMThuze2YgXJbW.', 'Gerencia'),
    ('Carlos Rivas', 'porteria@icafal.cl', '$2y$10$7MmTUu6q5icczbMgoV/FzuoDE4lzIUsUA55XA2rMThuze2YgXJbW.', 'Porteria'),
    ('Desarrollador ICAFAL', 'dev@icafal.cl', '$2y$10$7MmTUu6q5icczbMgoV/FzuoDE4lzIUsUA55XA2rMThuze2YgXJbW.', 'Desarrollador');

-- Se abren algunos cupos de partida para que la demo no arranque vacia.
UPDATE cargos SET cupos_totales = 5, cupos_activos = 5 WHERE nombre_cargo IN ('Jornal Concretero', 'Albañil', 'Carpintero');

# Sistema de Reclutamiento y Onboarding — ICAFAL

Sistema web para gestionar el flujo completo de reclutamiento de personal de
obra: postulación pública por QR, aprobaciones internas por rol, firma de
datos de contratación, inducción de prevención, entrega de EPP, control de
acceso en portería y cierre administrativo con exportación para remuneraciones.

## Stack técnico (elegido para hosting compartido estándar)

| Capa            | Tecnología                                              |
|-----------------|----------------------------------------------------------|
| Base de datos   | MySQL 5.7+ / MariaDB 10.3+                               |
| Backend         | PHP 8.x nativo + PDO (sin framework, sin build step)     |
| Frontend        | HTML5 + TailwindCSS (CDN) + JavaScript vanilla (fetch)   |
| Correo          | PHPMailer (SMTP) con *fallback* a `mail()` nativo        |

**¿Por qué PHP nativo en vez de Laravel/Node?** La mayoría de hosting de bajo
costo en Chile (cPanel, DirectAdmin) ofrece PHP + MySQL "listos para usar"
sin acceso a shell persistente ni Node.js corriendo como servicio. PHP nativo
con PDO evita build steps, procesos en background y dependencias pesadas:
se sube por FTP y funciona. Laravel es válido si el hosting soporta Composer
y `public/` como document root, pero agrega complejidad innecesaria para el
alcance de este sistema. Node.js exigiría un proceso persistente (PM2, etc.)
que la mayoría del hosting compartido no ofrece.

## Estructura

```
database/schema.sql          Script SQL completo (tablas, FKs, triggers)
backend/config/              Conexión a BD y constantes de configuración
backend/includes/            Autenticación, RUT, CSRF, helpers comunes
backend/mailer/              Envío de correos (PHPMailer + plantillas)
backend/api/                 Endpoints JSON, uno por acción/rol
frontend/public/             Páginas públicas (postulación, seguimiento, QR)
frontend/dashboards/         Un dashboard HTML por rol interno
frontend/assets/             JS/CSS compartido
```

## Instalación

1. Crear la base de datos e importar `database/schema.sql`. **Importante**:
   si lo importas por línea de comandos en Windows, fuerza el charset del
   cliente para evitar que tildes/ñ se corrompan (mojibake):
   ```bash
   mysql --default-character-set=utf8mb4 -u usuario -p < database/schema.sql
   ```
   Si lo importas vía phpMyAdmin no hace falta nada extra.
2. Copiar `backend/config/config.sample.php` a `backend/config/config.php`
   y completar credenciales de BD y SMTP.
3. Si el hosting soporta Composer: `composer install` (instala PHPMailer).
   Si no, el sistema usa automáticamente `mail()` nativo de PHP.
4. Subir todo el contenido a la raíz del hosting (o a un subdominio).
5. Crear el primer usuario administrador manualmente en la tabla `usuarios`
   con una contraseña generada por `password_hash()` (ver
   `backend/tools/crear_usuario.php`).
6. Generar el QR de acceso público apuntando a `frontend/public/index.html`.

## Seguridad implementada

- Contraseñas con `password_hash()`/`password_verify()` (bcrypt).
- 100% de las consultas SQL con **prepared statements** (PDO).
- Control de roles server-side en cada endpoint (`requireRol([...])`).
- El **Jefe de Terreno** y **Portería** no tienen ninguna consulta que
  incluya la tabla `datos_contratacion` — no es un tema de UI, el propio
  backend nunca la referencia en esos endpoints.
- El rol **Gerencia** ("usuario maestro") ve TODAS las postulaciones en
  TODAS las fases en un panel único (`frontend/dashboards/gerencia.html`),
  con KPIs por etapa y cupos por cargo, pero es 100% de solo lectura:
  no existe ningún endpoint de escritura para ese rol.
- Token privado de un solo uso con expiración (`token_expira_at`) para el
  formulario de datos sensibles.
- Registro de trazabilidad automático (trigger SQL) con fecha/hora exacta
  (segundos) y usuario responsable de cada cambio de estado.
- Protección CSRF con token de sesión + cabecera personalizada.

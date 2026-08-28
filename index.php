<?php
// Redirige el dominio "pelado" al formulario público de postulación,
// para que entrar sin ninguna ruta especifica tambien funcione.
header('Location: /frontend/public/index.html');
exit;

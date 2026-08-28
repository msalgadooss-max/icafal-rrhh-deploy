<?php
require_once __DIR__ . '/../../includes/auth.php';

iniciarSesionSegura();
$_SESSION = [];
session_destroy();

responderOk();

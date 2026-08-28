<?php
/**
 * Proteccion CSRF ligera para la API de sesion (dashboards internos).
 * El token se emite en el login y se guarda en sesion; el frontend debe
 * reenviarlo en la cabecera X-CSRF-Token en toda peticion que modifique
 * datos (POST). Los endpoints publicos sin sesion (postulacion,
 * seguimiento, formulario con token) no lo requieren porque no dependen
 * de cookies de sesion autenticada.
 */

function generarCsrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function exigirCsrfValido(): void
{
    $enviado = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $esperado = $_SESSION['csrf_token'] ?? '';

    if ($esperado === '' || !hash_equals($esperado, $enviado)) {
        responderError('Token CSRF invalido o ausente.', 403);
    }
}

<?php
/**
 * v3 - Expone las listas desplegables (Buk) y el mapa Región→Comuna al
 * frontend. Publico y sin datos sensibles: son catalogos fijos, no
 * informacion de ninguna persona.
 */
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/listas_buk.php';

exigirMetodo('GET');

responderOk([
    'listas' => listasBuk(),
    'regiones_comunas' => regionesConComunas(),
]);

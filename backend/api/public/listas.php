<?php
/**
 * v3 - Expone las listas desplegables (Buk) y el mapa Región→Comuna al
 * frontend. Publico y sin datos sensibles: son catalogos fijos, no
 * informacion de ninguna persona.
 *
 * v10.5: se agrega el nombre de la obra (antes venia en
 * cargos_disponibles.php, que dejo de llamarse desde el formulario
 * publico al quitarse la eleccion de cargo -- Mejorar APP, punto 2).
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/listas_buk.php';

exigirMetodo('GET');

responderOk([
    'listas' => listasBuk(),
    'regiones_comunas' => regionesConComunas(),
    'obra' => OBRA_NOMBRE,
]);

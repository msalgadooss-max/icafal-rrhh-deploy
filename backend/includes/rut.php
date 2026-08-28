<?php
/**
 * Validacion de RUT chileno (modulo 11) - replica en servidor la misma
 * regla que se aplica en el navegador (frontend/assets/js/rut.js), para
 * que nunca se confie unicamente en la validacion del cliente.
 */

/**
 * Normaliza un RUT a formato "12345678-9" (sin puntos, con guion,
 * digito verificador en mayuscula).
 */
function normalizarRut(string $rut): string
{
    $rut = strtoupper(trim($rut));
    $rut = str_replace(['.', ' '], '', $rut);
    if (!str_contains($rut, '-')) {
        // admite que lo escriban sin guion, ej "123456789"
        $rut = substr($rut, 0, -1) . '-' . substr($rut, -1);
    }
    return $rut;
}

/**
 * Valida el digito verificador de un RUT chileno usando el algoritmo
 * de modulo 11 con secuencia de multiplicadores 2..7 ciclica.
 */
function validarRut(string $rutCompleto): bool
{
    $rutCompleto = normalizarRut($rutCompleto);

    if (!preg_match('/^(\d{7,8})-([\dkK])$/', $rutCompleto, $m)) {
        return false;
    }

    $cuerpo = $m[1];
    $dvIngresado = strtoupper($m[2]);

    $suma = 0;
    $multiplicador = 2;

    for ($i = strlen($cuerpo) - 1; $i >= 0; $i--) {
        $suma += (int)$cuerpo[$i] * $multiplicador;
        $multiplicador = $multiplicador === 7 ? 2 : $multiplicador + 1;
    }

    $resto = 11 - ($suma % 11);
    $dvCalculado = match ($resto) {
        11 => '0',
        10 => 'K',
        default => (string)$resto,
    };

    return $dvCalculado === $dvIngresado;
}

/**
 * v3: normaliza el "N° de Documento" segun tipo_documento. Para 'RUT'
 * se mantiene el formato chileno exacto de siempre (con guion). Para
 * 'Otro' (pasaporte, DNI, etc. de alguien sin cedula chilena aun) no
 * se le impone ningun formato -- solo se limpian espacios extra.
 */
function normalizarDocumento(string $tipoDocumento, string $valor): string
{
    if ($tipoDocumento === 'RUT') {
        return normalizarRut($valor);
    }
    return trim($valor);
}

/**
 * v3: valida el "N° de Documento" segun su tipo. Un documento 'Otro'
 * solo se exige no vacio -- la persona todavia no tiene RUT chileno,
 * por eso no se le puede exigir un digito verificador que no existe.
 */
function validarDocumento(string $tipoDocumento, string $valor): bool
{
    if ($tipoDocumento === 'RUT') {
        return validarRut($valor);
    }
    return trim($valor) !== '';
}

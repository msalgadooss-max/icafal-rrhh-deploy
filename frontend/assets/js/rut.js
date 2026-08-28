/**
 * Validación de RUT chileno en el cliente (UX inmediata). El backend
 * (backend/includes/rut.php) vuelve a validar todo: esto solo evita
 * viajes de red innecesarios y da feedback rápido al postulante.
 */
function normalizarRut(rut) {
  rut = (rut || '').toUpperCase().trim().replace(/[.\s]/g, '');
  if (!rut.includes('-')) {
    rut = rut.slice(0, -1) + '-' + rut.slice(-1);
  }
  return rut;
}

function validarRut(rutCompleto) {
  const rut = normalizarRut(rutCompleto);
  const match = /^(\d{7,8})-([\dkK])$/.exec(rut);
  if (!match) return false;

  const cuerpo = match[1];
  const dvIngresado = match[2].toUpperCase();

  let suma = 0;
  let multiplicador = 2;
  for (let i = cuerpo.length - 1; i >= 0; i--) {
    suma += parseInt(cuerpo[i], 10) * multiplicador;
    multiplicador = multiplicador === 7 ? 2 : multiplicador + 1;
  }

  const resto = 11 - (suma % 11);
  let dvCalculado;
  if (resto === 11) dvCalculado = '0';
  else if (resto === 10) dvCalculado = 'K';
  else dvCalculado = String(resto);

  return dvCalculado === dvIngresado;
}

function formatearRutInput(input) {
  input.addEventListener('input', () => {
    let valor = input.value.toUpperCase().replace(/[^0-9K]/g, '');
    if (valor.length > 1) {
      valor = valor.slice(0, -1) + '-' + valor.slice(-1);
    }
    input.value = valor;
  });
}

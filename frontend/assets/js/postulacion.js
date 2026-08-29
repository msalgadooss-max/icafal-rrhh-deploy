/**
 * Lógica de la Etapa 1 (formulario público de postulación).
 * v3: campos alineados a la plantilla Buk + listas desplegables reales
 * (tipo de documento, región→comuna en cascada) cargadas desde
 * /public/listas.php, para que el postulante nunca escriba libremente
 * algo que después no calce con lo que Buk espera.
 */
const tipoDocumentoSelect = document.getElementById('tipo_documento');
const numeroDocumentoInput = document.getElementById('numero_documento');
const labelDocumento = document.getElementById('label-documento');
const rutError = document.getElementById('rut-error');
const docNota = document.getElementById('doc-nota');
const regionSelect = document.getElementById('region');
const comunaSelect = document.getElementById('comuna');
const cargoSelect = document.getElementById('cargo_id');
const form = document.getElementById('form-postulacion');
const resultadoDiv = document.getElementById('resultado');
const btnEnviar = document.getElementById('btn-enviar');
const obraBanner = document.getElementById('obra-banner');
const correoUsuarioInput = document.getElementById('correo_usuario');
const correoDominioSelect = document.getElementById('correo_dominio');
const correoDominioOtroInput = document.getElementById('correo_dominio_otro');
const correoHidden = document.getElementById('correo');

// v4: dominio de correo desplegable -- el postulante solo escribe su
// nombre de usuario y elige el dominio de una lista (o "Otro..." para
// escribirlo completo), para que sea más rápido y evite errores de tipeo.
correoDominioSelect.addEventListener('change', () => {
  correoDominioOtroInput.classList.toggle('hidden', correoDominioSelect.value !== '__otro__');
  if (correoDominioSelect.value === '__otro__') correoDominioOtroInput.focus();
});

function actualizarCorreoCompuesto() {
  const dominio = correoDominioSelect.value === '__otro__'
    ? correoDominioOtroInput.value.trim()
    : correoDominioSelect.value;
  correoHidden.value = correoUsuarioInput.value.trim() + dominio;
}
correoUsuarioInput.addEventListener('input', actualizarCorreoCompuesto);
correoDominioSelect.addEventListener('change', actualizarCorreoCompuesto);
correoDominioOtroInput.addEventListener('input', actualizarCorreoCompuesto);

let REGIONES_COMUNAS = {};

async function cargarListas() {
  try {
    const data = await apiFetch('/public/listas.php');
    REGIONES_COMUNAS = data.regiones_comunas;

    tipoDocumentoSelect.innerHTML = data.listas.tipo_documento
      .map(v => `<option value="${v}">${v}</option>`).join('');

    regionSelect.innerHTML = '<option value="">Selecciona tu región</option>' +
      Object.keys(REGIONES_COMUNAS).map(r => `<option value="${r}">${r}</option>`).join('');
  } catch (e) {
    tipoDocumentoSelect.innerHTML = '<option value="">Error al cargar</option>';
  }
}

tipoDocumentoSelect.addEventListener('change', () => {
  const esRut = tipoDocumentoSelect.value === 'RUT';
  labelDocumento.textContent = esRut ? 'RUT' : 'N° de documento';
  numeroDocumentoInput.placeholder = esRut ? '12345678-9' : 'Pasaporte, DNI, etc.';
  docNota.classList.toggle('hidden', esRut);
  rutError.classList.add('hidden');
  numeroDocumentoInput.value = '';
});

numeroDocumentoInput.addEventListener('input', () => {
  if (tipoDocumentoSelect.value === 'RUT') {
    let valor = numeroDocumentoInput.value.toUpperCase().replace(/[^0-9K]/g, '');
    if (valor.length > 1) valor = valor.slice(0, -1) + '-' + valor.slice(-1);
    numeroDocumentoInput.value = valor;
  }
});
numeroDocumentoInput.addEventListener('blur', () => {
  if (tipoDocumentoSelect.value !== 'RUT') return;
  const valido = numeroDocumentoInput.value === '' || validarRut(numeroDocumentoInput.value);
  rutError.classList.toggle('hidden', valido);
});

regionSelect.addEventListener('change', () => {
  const comunas = REGIONES_COMUNAS[regionSelect.value] || [];
  if (!comunas.length) {
    comunaSelect.innerHTML = '<option value="">Primero elige tu región</option>';
    comunaSelect.disabled = true;
    return;
  }
  comunaSelect.innerHTML = '<option value="">Selecciona tu comuna</option>' +
    comunas.map(c => `<option value="${c}">${c}</option>`).join('');
  comunaSelect.disabled = false;
});

// v3: se listan TODOS los cargos activos, incluso sin cupo -- postular
// a uno sin cupo ya no se bloquea, la persona queda en el Banco de
// Postulantes (ver postular.php) en vez de no tener ninguna opción.
async function cargarCargos() {
  try {
    const data = await apiFetch('/public/cargos_disponibles.php');
    obraBanner.textContent = '📍 ' + (data.obra || 'Obra ICAFAL');
    if (!data.cargos.length) {
      cargoSelect.innerHTML = '<option value="">No hay cargos disponibles por el momento</option>';
      return;
    }
    cargoSelect.innerHTML = '<option value="">Selecciona un cargo</option>' +
      data.cargos.map(c => `<option value="${c.id}">${c.nombre_cargo} — ${c.tiene_cupo ? `${c.cupos_disponibles} cupo(s) disponible(s)` : 'sin cupo, quedarás en el Banco de Postulantes'}</option>`).join('');
  } catch (e) {
    cargoSelect.innerHTML = '<option value="">Error al cargar cargos</option>';
  }
}

cargarListas();
cargarCargos();

// v6.9: "No tengo CV" -- Ricardo pidió no bloquear al postulante que
// nunca ha trabajado o no tiene su CV a mano; en vez de eso, se le pide
// contar su última experiencia en 3 campos simples.
const sinCvCheckbox = document.getElementById('sin-cv');
const cvInput = document.getElementById('cv');
const experienciaManualDiv = document.getElementById('experiencia-manual');
sinCvCheckbox.addEventListener('change', () => {
  const sinCv = sinCvCheckbox.checked;
  experienciaManualDiv.classList.toggle('hidden', !sinCv);
  cvInput.required = !sinCv;
  cvInput.disabled = sinCv;
  if (sinCv) cvInput.value = '';
});

form.addEventListener('submit', async (e) => {
  e.preventDefault();

  if (tipoDocumentoSelect.value === 'RUT' && !validarRut(numeroDocumentoInput.value)) {
    rutError.classList.remove('hidden');
    numeroDocumentoInput.focus();
    return;
  }

  actualizarCorreoCompuesto();
  if (!correoUsuarioInput.value.trim() || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(correoHidden.value)) {
    document.getElementById('alerta').innerHTML =
      '<div class="bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg px-4 py-3 mb-4">Ingresa un correo electrónico válido.</div>';
    window.scrollTo({ top: 0, behavior: 'smooth' });
    return;
  }

  if (sinCvCheckbox.checked && !document.getElementById('experiencia_descripcion').value.trim()) {
    document.getElementById('alerta').innerHTML =
      '<div class="bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg px-4 py-3 mb-4">Cuéntanos brevemente tu experiencia (o sube tu CV en vez de marcar "No tengo CV").</div>';
    window.scrollTo({ top: 0, behavior: 'smooth' });
    return;
  }

  btnEnviar.disabled = true;
  btnEnviar.textContent = 'Enviando...';

  try {
    const formData = new FormData();
    formData.append('tipo_documento', tipoDocumentoSelect.value);
    formData.append('numero_documento', numeroDocumentoInput.value);
    formData.append('nombre', document.getElementById('nombre').value);
    formData.append('apellido', document.getElementById('apellido').value);
    formData.append('segundo_apellido', document.getElementById('segundo_apellido').value);
    formData.append('telefono', document.getElementById('telefono').value);
    formData.append('correo', document.getElementById('correo').value);
    formData.append('region', regionSelect.value);
    formData.append('comuna', comunaSelect.value);
    formData.append('cargo_id', cargoSelect.value);
    formData.append('consentimiento_ley19628', document.getElementById('consentimiento').checked ? '1' : '');
    if (sinCvCheckbox.checked) {
      formData.append('experiencia_cargo', document.getElementById('experiencia_cargo').value);
      formData.append('experiencia_fecha', document.getElementById('experiencia_fecha').value);
      formData.append('experiencia_descripcion', document.getElementById('experiencia_descripcion').value);
    } else if (cvInput.files[0]) {
      formData.append('cv', cvInput.files[0]);
    }

    const data = await apiFetchFormData('/public/postular.php', formData);

    form.classList.add('hidden');
    resultadoDiv.classList.remove('hidden');
    resultadoDiv.innerHTML = `
      <div class="text-green-600 text-4xl">✔</div>
      <h2 class="text-lg font-bold text-gray-900">${data.en_banco ? '¡Quedaste en el Banco de Postulantes!' : '¡Postulación enviada!'}</h2>
      <p class="text-sm text-gray-600">${data.mensaje}</p>
      <p class="text-sm text-gray-600 mt-2">Tu código de seguimiento es:</p>
      <p class="text-3xl font-bold tracking-widest bg-gray-100 rounded-lg py-3">${data.codigo_seguimiento}</p>
      <p class="text-xs text-gray-500">Guárdalo junto a tu documento. También te lo enviamos por correo.</p>
      <a href="seguimiento.html" class="inline-block mt-2 bg-blue-600 text-white rounded-lg px-4 py-2 text-sm font-medium">Ir a seguimiento</a>
    `;
  } catch (err) {
    document.getElementById('alerta').innerHTML =
      `<div class="bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg px-4 py-3 mb-4">${err.message}</div>`;
    window.scrollTo({ top: 0, behavior: 'smooth' });
  } finally {
    btnEnviar.disabled = false;
    btnEnviar.textContent = 'Enviar postulación';
  }
});

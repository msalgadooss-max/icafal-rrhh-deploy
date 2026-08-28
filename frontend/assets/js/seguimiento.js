/**
 * Fase 6 incluida aquí: cuando el estado llega a EPP_listo (o
 * Contratado), esta pantalla cambia a verde y genera el QR de acceso
 * para portería, codificando la URL pública de validación
 * (backend/api/porteria/validar.php) con rut + codigo_seguimiento.
 */
const ETIQUETAS_ESTADO = {
  Pendiente: 'Postulación recibida',
  Pre_aprobado_terreno: 'Pre-aprobado por Jefe de Terreno',
  Aprobado_admin: 'En revisión Jefe Administrativo',
  Induccion_ok: 'Inducción de seguridad realizada',
  EPP_listo: 'Kit de EPP listo',
  Contratado: 'Contratado',
};

const form = document.getElementById('form-seguimiento');
const rutInput = document.getElementById('rut');
const resultadoDiv = document.getElementById('resultado');
const alertaDiv = document.getElementById('alerta');

formatearRutInput(rutInput);

// Prefill si venimos desde el correo de confirmación (?rut=...)
const params = new URLSearchParams(window.location.search);
if (params.get('rut')) rutInput.value = params.get('rut');

form.addEventListener('submit', async (e) => {
  e.preventDefault();
  alertaDiv.innerHTML = '';
  resultadoDiv.innerHTML = '';

  const codigo = document.getElementById('codigo').value.trim().toUpperCase();

  try {
    const data = await apiFetch('/public/seguimiento.php', {
      method: 'POST',
      body: { rut: rutInput.value, codigo_seguimiento: codigo },
    });
    renderResultado(data.postulacion);
  } catch (err) {
    alertaDiv.innerHTML = `<div class="bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg px-4 py-3">${err.message}</div>`;
  }
});

function renderResultado(p) {
  if (p.en_banco) {
    resultadoDiv.innerHTML = `
      <div class="bg-blue-50 border border-blue-200 rounded-xl p-6 text-center">
        <p class="text-blue-700 font-bold text-lg">Estás en nuestro Banco de Postulantes</p>
        <p class="text-sm text-gray-600 mt-1">${p.nombre_completo} — interés en ${p.cargo}</p>
        <p class="text-sm text-gray-600 mt-3">Ahora mismo no hay cupos para ese cargo. Te contactaremos apenas se abra uno.</p>
        <p class="text-xs text-gray-400 mt-3">Tus datos se conservan hasta el ${p.retencion_hasta}.</p>
      </div>`;
    return;
  }

  if (p.rechazado) {
    resultadoDiv.innerHTML = `
      <div class="bg-red-50 border border-red-200 rounded-xl p-6 text-center">
        <p class="text-red-700 font-bold text-lg">Postulación no continúa en el proceso</p>
        <p class="text-sm text-red-600 mt-1">${p.nombre_completo} — ${p.cargo}</p>
      </div>`;
    return;
  }

  if (p.autorizado_ingreso) {
    // v4: 'Contratado' significa que TODO el proceso terminó y falta
    // solo firmar el contrato en oficina JAO -- mensaje distinto al de
    // 'EPP_listo', que es sobre el ingreso físico a la obra una vez
    // que el kit de EPP está listo.
    const esContratacionFinal = p.estado === 'Contratado';
    const titulo = esContratacionFinal ? 'PROCESO COMPLETADO EXITOSAMENTE' : 'CONTRATACIÓN AUTORIZADA';
    const subtitulo = esContratacionFinal
      ? 'Preséntate en oficina JAO para firmar tu contrato'
      : 'INGRESO PERMITIDO A LA OBRA';
    const pie = esContratacionFinal
      ? 'Muestra este código en oficina JAO.'
      : 'Muestra este código al guardia en portería.';
    resultadoDiv.innerHTML = `
      <div class="bg-green-500 rounded-xl p-6 text-center text-white shadow-lg">
        <p class="text-2xl font-extrabold">${titulo}</p>
        <p class="text-lg font-semibold mt-1">${subtitulo}</p>
        <p class="text-sm mt-2 opacity-90">${p.nombre_completo} — ${p.cargo}</p>
        <div id="qr" class="bg-white inline-block p-3 rounded-lg mt-4"></div>
        <p class="text-xs mt-2 opacity-80">${pie}</p>
      </div>
      ${timelineHtml(p)}
    `;
    // El QR apunta a una página HTML de resultado (no directo a la API)
    // para que el guardia vea una credencial legible, no un JSON crudo.
    const urlValidacion = `${window.location.origin}${window.location.pathname.replace('seguimiento.html', 'porteria_resultado.html')}?rut=${encodeURIComponent(p.rut)}&codigo=${encodeURIComponent(p.codigo_seguimiento)}`;
    // eslint-disable-next-line no-undef
    new QRCode(document.getElementById('qr'), { text: urlValidacion, width: 160, height: 160 });
    return;
  }

  resultadoDiv.innerHTML = `
    <div class="bg-white shadow-sm rounded-xl p-5">
      <p class="font-bold text-gray-900">${p.nombre_completo}</p>
      <p class="text-sm text-gray-500 mb-4">${p.cargo}</p>
      ${p.documento_observado ? `
        <div class="bg-amber-50 border border-amber-200 rounded-lg px-4 py-3 mb-4 text-sm text-amber-800">
          ⚠ Hay una observación en uno de tus documentos. Revisa tu correo para ver el detalle y el link para corregirlo.
        </div>` : ''}
      ${timelineHtml(p)}
    </div>`;
}

function timelineHtml(p) {
  const idxActual = p.orden_estados.indexOf(p.estado);

  // v6.5: flujo SECUENCIAL -- "Autorización Administrador de contrato" y
  // "Datos completados por el postulante" no son parte de `orden_estados`
  // (no son estados reales) -- se insertan como pasos visuales aparte,
  // justo después de "Pre-aprobado por Jefe de Terreno". El Administrador
  // autoriza PRIMERO (recién ahí el postulante recibe el acceso a Etapa
  // 2), así que ese paso siempre se enciende antes que el segundo.
  const pasos = [];
  p.orden_estados.forEach((estado, idx) => {
    pasos.push({ etiqueta: ETIQUETAS_ESTADO[estado], completado: idx <= idxActual });
    if (estado === 'Pre_aprobado_terreno') {
      pasos.push({ etiqueta: 'Autorización Administrador de contrato', completado: p.admin_autorizado || idx < idxActual });
      pasos.push({ etiqueta: 'Datos completados por el postulante', completado: p.etapa2_completada || idx < idxActual });
    }
  });

  const html = pasos.map(paso => `
      <li class="flex items-center gap-3 py-1.5">
        <span class="w-3 h-3 rounded-full flex-shrink-0 ${paso.completado ? 'bg-green-500' : 'bg-gray-300'}"></span>
        <span class="text-sm ${paso.completado ? 'text-gray-900 font-medium' : 'text-gray-400'}">${paso.etiqueta}</span>
      </li>`).join('');
  return `<ul class="mt-4">${html}</ul>`;
}

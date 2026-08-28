/**
 * Panel de Gerencia: única pantalla que trae TODAS las postulaciones,
 * sin importar su fase. No tiene ningún botón de acción -- es
 * deliberadamente de solo lectura (el control de acceso real vive en
 * el backend: /api/gerencia/listar.php es el único endpoint de este
 * rol y no existe ningún endpoint de escritura para 'Gerencia').
 */
const ETIQUETAS_ESTADO = {
  En_banco: 'En banco',
  Pendiente: 'Pendiente',
  Pre_aprobado_terreno: 'Pre-aprobado terreno',
  Aprobado_admin: 'Aprobado admin',
  Datos_completados: 'Datos completados',
  Induccion_ok: 'Inducción OK',
  EPP_listo: 'EPP listo',
  Contratado: 'Contratado',
  Rechazado: 'Rechazado',
};

const COLOR_ESTADO = {
  En_banco: 'bg-sky-100 text-sky-700',
  Pendiente: 'bg-gray-100 text-gray-700',
  Pre_aprobado_terreno: 'bg-blue-100 text-blue-700',
  Aprobado_admin: 'bg-indigo-100 text-indigo-700',
  Datos_completados: 'bg-purple-100 text-purple-700',
  Induccion_ok: 'bg-amber-100 text-amber-700',
  EPP_listo: 'bg-teal-100 text-teal-700',
  Contratado: 'bg-green-100 text-green-700',
  Rechazado: 'bg-red-100 text-red-700',
};

let TODAS_LAS_POSTULACIONES = [];

(async () => {
  const usuario = await protegerDashboard('Gerencia');
  if (!usuario) return;
  await cargarPanel();
  await cargarBitacora();
  iniciarEstadoVivo();
})();

// --- v5: bitácora de actividad en lenguaje natural -------------------------
async function cargarBitacora() {
  const lista = document.getElementById('lista-bitacora');
  const vacio = document.getElementById('bitacora-vacio');
  try {
    const data = await apiFetch('/bitacora.php');
    if (!data.eventos.length) {
      lista.innerHTML = '';
      vacio.classList.remove('hidden');
      return;
    }
    vacio.classList.add('hidden');
    lista.innerHTML = data.eventos.map(e => `
      <li class="text-sm border-b border-gray-100 pb-2 last:border-0 last:pb-0">
        <span class="text-gray-900"><strong>${e.nombre_completo}</strong> (${e.nombre_cargo}) — ${e.descripcion}</span>
        <span class="block text-xs text-gray-400 mt-0.5">${e.autor} · ${new Date(e.fecha_hora).toLocaleString('es-CL')}</span>
      </li>`).join('');
  } catch (err) {
    mostrarAlerta('alerta', err.message);
  }
}

document.getElementById('filtro-estado').addEventListener('change', renderTabla);

async function cargarPanel() {
  try {
    const data = await apiFetch('/gerencia/listar.php');
    TODAS_LAS_POSTULACIONES = data.postulaciones;
    renderKpis(data.resumen_estados);
    renderCupos(data.cargos);
    renderEstadoSistema(data.cierre_remuneraciones_activo, data.modulos);
    renderTabla();
  } catch (err) {
    mostrarAlerta('alerta', err.message);
  }
}

function renderKpis(resumen) {
  const kpisDiv = document.getElementById('kpis');
  kpisDiv.innerHTML = Object.keys(ETIQUETAS_ESTADO).map(estado => `
    <div class="bg-white rounded-xl shadow-sm p-3 text-center">
      <p class="text-2xl font-bold text-gray-900">${resumen[estado] ?? 0}</p>
      <p class="text-xs text-gray-500 mt-1">${ETIQUETAS_ESTADO[estado]}</p>
    </div>`).join('');
}

function renderEstadoSistema(cierreActivo, modulos) {
  const el = document.getElementById('estado-sistema');
  if (!el) return;
  const chips = [];
  chips.push(cierreActivo
    ? '<span class="px-2.5 py-1 rounded-full text-xs font-medium bg-amber-100 text-amber-800">Cierre de remuneraciones ACTIVO — no se pueden finalizar contrataciones</span>'
    : '<span class="px-2.5 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">Remuneraciones abiertas</span>');
  if (!modulos.prevencion) {
    chips.push('<span class="px-2.5 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-500">Prevención: pausada en esta demo</span>');
  }
  if (!modulos.bodega) {
    chips.push('<span class="px-2.5 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-500">Bodega: pausada en esta demo</span>');
  }
  el.innerHTML = chips.join(' ');
}

function renderCupos(cargos) {
  const cuposDiv = document.getElementById('cupos');
  cuposDiv.innerHTML = cargos.map(c => {
    const ocupados = c.cupos_totales - c.cupos_activos;
    const pct = c.cupos_totales > 0 ? Math.round((ocupados / c.cupos_totales) * 100) : 0;
    return `
      <div class="border border-gray-200 rounded-lg p-3">
        <p class="text-sm font-medium text-gray-800">${c.nombre_cargo}</p>
        <p class="text-xs text-gray-500 mb-1">${ocupados} / ${c.cupos_totales} cupos ocupados</p>
        <div class="w-full bg-gray-100 rounded-full h-2">
          <div class="bg-blue-600 h-2 rounded-full" style="width:${pct}%"></div>
        </div>
      </div>`;
  }).join('');
}

function renderTabla() {
  const filtro = document.getElementById('filtro-estado').value;
  const tbody = document.getElementById('tbody-postulaciones');
  const vacio = document.getElementById('vacio');
  const contador = document.getElementById('contador');

  const filas = filtro
    ? TODAS_LAS_POSTULACIONES.filter(p => p.estado === filtro)
    : TODAS_LAS_POSTULACIONES;

  contador.textContent = `${filas.length} postulación(es)`;

  if (!filas.length) {
    tbody.innerHTML = '';
    vacio.classList.remove('hidden');
    return;
  }
  vacio.classList.add('hidden');

  tbody.innerHTML = filas.map(p => `
    <tr class="border-t">
      <td class="px-4 py-3 font-mono">${p.rut}</td>
      <td class="px-4 py-3">${p.nombre_completo}</td>
      <td class="px-4 py-3">${p.nombre_cargo}</td>
      <td class="px-4 py-3">${p.comuna}</td>
      <td class="px-4 py-3"><span class="px-2 py-1 rounded-full text-xs font-medium ${COLOR_ESTADO[p.estado] || 'bg-gray-100 text-gray-700'}">${ETIQUETAS_ESTADO[p.estado] || p.estado}</span></td>
      <td class="px-4 py-3 text-gray-500">${new Date(p.actualizado_at).toLocaleString('es-CL')}</td>
    </tr>`).join('');
}

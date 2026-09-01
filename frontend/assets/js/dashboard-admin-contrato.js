/**
 * v6.5: Admin_Contrato autoriza PRIMERO -- recién en ese momento el
 * postulante recibe el enlace de Etapa 2. Esta lista solo ve lo que
 * Jefe_Terreno ya pre-aprobó: datos básicos + CV. No necesita ver
 * documentos de Etapa 2 (eso lo revisa el JAO más adelante, una vez
 * el postulante los completa).
 */
(async () => {
  const usuario = await protegerDashboard('Admin_Contrato');
  if (!usuario) return;
  await cargarLista();
  configurarTabs();
  iniciarEstadoVivo();
})();

// --- v3.4: pestañas --------------------------------------------------------
let AUTORIZADO_CARGADO = false;

function configurarTabs() {
  document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', () => cambiarTab(btn.dataset.tab));
  });
}

function cambiarTab(tab) {
  document.querySelectorAll('.tab-btn').forEach(btn => {
    const activo = btn.dataset.tab === tab;
    btn.classList.toggle('border-blue-600', activo);
    btn.classList.toggle('text-blue-600', activo);
    btn.classList.toggle('border-transparent', !activo);
    btn.classList.toggle('text-gray-500', !activo);
  });
  document.querySelectorAll('.tab-panel').forEach(panel => {
    panel.classList.toggle('hidden', panel.id !== `panel-${tab}`);
  });
  if (tab === 'autorizado' && !AUTORIZADO_CARGADO) {
    AUTORIZADO_CARGADO = true;
    cargarAutorizados();
  }
  if (tab === 'estado_proceso') {
    renderEstadoProcesoTabla(); // pinta con lo último que ya cargó el widget "Estado en vivo"
  }
  if (tab === 'solicitudes_cupo') {
    cargarSolicitudesCupo();
  }
}

// --- v6.9: aprobar/rechazar solicitudes de cupo de Jefe de Terreno --------
// (esto es lo que en la reunión con Ricardo se llamó "abrir la vacante").
async function cargarSolicitudesCupo() {
  const tbody = document.getElementById('tbody-solicitudes-cupo');
  const vacio = document.getElementById('solicitudes-cupo-vacio');
  try {
    const data = await apiFetch('/admin_contrato/solicitudes_cupo_listar.php');
    if (!data.solicitudes.length) {
      tbody.innerHTML = '';
      vacio.classList.remove('hidden');
      return;
    }
    vacio.classList.add('hidden');
    tbody.innerHTML = data.solicitudes.map(s => `
      <tr class="border-t">
        <td class="px-4 py-3">${s.nombre_cargo}${s.es_cargo_nuevo ? ' <span class="text-[10px] text-indigo-700 bg-indigo-50 px-1.5 py-0.5 rounded font-semibold align-middle">🆕 cargo nuevo</span>' : ''}</td>
        <td class="px-4 py-3 font-semibold">${s.cantidad}</td>
        <td class="px-4 py-3">${s.solicitado_por_nombre || '—'}</td>
        <td class="px-4 py-3 text-gray-500">${new Date(s.creado_at).toLocaleString('es-CL')}</td>
        <td class="px-4 py-3 text-right space-x-2">
          <button class="bg-green-600 hover:bg-green-700 text-white text-xs font-semibold px-3 py-1.5 rounded-lg" onclick="aprobarSolicitudCupo(${s.id})">Aprobar</button>
          <button class="bg-red-100 hover:bg-red-200 text-red-700 text-xs font-semibold px-3 py-1.5 rounded-lg" onclick="rechazarSolicitudCupo(${s.id})">Rechazar</button>
        </td>
      </tr>`).join('');
  } catch (err) {
    mostrarAlerta('alerta', err.message);
  }
}

async function aprobarSolicitudCupo(id) {
  if (!confirm('¿Aprobar esta solicitud de cupos? Se abrirá la vacante de inmediato.')) return;
  try {
    const data = await apiFetch('/admin_contrato/solicitudes_cupo_aprobar.php', { method: 'POST', body: { solicitud_id: id } });
    mostrarAlerta('alerta', data.mensaje, 'exito');
    await cargarSolicitudesCupo();
  } catch (err) {
    mostrarAlerta('alerta', err.message);
  }
}

async function rechazarSolicitudCupo(id) {
  const motivo = await pedirMotivoRechazo();
  if (motivo === null) return;
  try {
    const data = await apiFetch('/admin_contrato/solicitudes_cupo_rechazar.php', { method: 'POST', body: { solicitud_id: id, motivo } });
    mostrarAlerta('alerta', data.mensaje, 'exito');
    await cargarSolicitudesCupo();
  } catch (err) {
    mostrarAlerta('alerta', err.message);
  }
}

// --- v6.5: pestaña "Estado del proceso" (cada trabajador individualizado) -
// Reutiliza la misma data que el widget "Estado en vivo" (estado-vivo.js),
// que ya se refresca solo cada 10s -- no pide un endpoint aparte.
function onEstadoVivoActualizado() {
  const panel = document.getElementById('panel-estado_proceso');
  if (panel && !panel.classList.contains('hidden')) renderEstadoProcesoTabla();
}

function renderEstadoProcesoTabla() {
  const tbody = document.getElementById('tbody-estado-proceso');
  const vacio = document.getElementById('estado-proceso-vacio');
  if (!tbody) return;
  const trabajadores = ESTADO_VIVO_ULTIMO || [];
  if (!trabajadores.length) {
    tbody.innerHTML = '';
    if (vacio) vacio.classList.remove('hidden');
    return;
  }
  if (vacio) vacio.classList.add('hidden');
  tbody.innerHTML = trabajadores.map(t => `
    <tr class="border-t ${t.pendiente_de_ti ? 'bg-amber-50' : ''}">
      <td class="px-4 py-3 font-medium text-gray-900">${t.nombre_completo}</td>
      <td class="px-4 py-3">${t.nombre_cargo}</td>
      <td class="px-4 py-3">
        <span class="text-xs font-medium ${t.contratado ? 'text-green-700' : 'text-blue-700'}">${t.fase}</span>
        ${t.pendiente_de_ti ? '<span class="ml-2 text-[11px] font-semibold text-amber-700">👉 Pendiente en tu bandeja</span>' : ''}
      </td>
      <td class="px-4 py-3 text-right">
        <button class="text-xs font-semibold text-blue-600 underline" onclick="abrirDetalleTrabajador(${t.id})">Ver pasos</button>
      </td>
    </tr>`).join('');
}

async function cargarLista() {
  const tbody = document.getElementById('tbody-postulaciones');
  const vacio = document.getElementById('vacio');
  try {
    const data = await apiFetch('/admin_contrato/listar.php');
    if (!data.postulaciones.length) {
      tbody.innerHTML = '';
      vacio.classList.remove('hidden');
      return;
    }
    vacio.classList.add('hidden');
    tbody.innerHTML = data.postulaciones.map(p => `
      <tr class="border-t">
        <td class="px-4 py-3 font-mono">${celdaDocumento(p)}</td>
        <td class="px-4 py-3">${p.nombre_completo}</td>
        <td class="px-4 py-3">${p.nombre_cargo}</td>
        <td class="px-4 py-3">${p.correo}</td>
        <td class="px-4 py-3">${p.tiene_cv
          ? `<a href="${API_BASE_URL}/documentos/ver.php?postulacion_id=${p.id}&tipo=cv" target="_blank" class="text-blue-600 font-medium underline">Ver CV</a>`
          : '<span class="text-gray-400 text-xs">Sin CV</span>'}</td>
        <td class="px-4 py-3 text-right space-x-2">
          <button class="bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold px-3 py-1.5 rounded-lg" onclick="autorizar(${p.id})">Autorizar Contratación</button>
          <button class="bg-red-100 hover:bg-red-200 text-red-700 text-xs font-semibold px-3 py-1.5 rounded-lg" onclick="rechazar(${p.id})">Rechazar</button>
        </td>
      </tr>`).join('');
  } catch (err) {
    mostrarAlerta('alerta', err.message);
  }
}

async function autorizar(id) {
  if (!confirm('¿Autorizar esta contratación? Se le enviará al postulante el enlace para completar sus datos (Etapa 2).')) return;
  try {
    const data = await apiFetch('/admin_contrato/autorizar.php', { method: 'POST', body: { postulacion_id: id } });
    mostrarAlerta('alerta', data.mensaje, 'exito');
    await cargarLista();
    AUTORIZADO_CARGADO = false; // fuerza recarga la próxima vez que se abra esa pestaña
  } catch (err) {
    mostrarAlerta('alerta', err.message);
  }
}

async function rechazar(id) {
  const motivo = await pedirMotivoRechazo();
  if (motivo === null) return;
  try {
    const data = await apiFetch('/admin_contrato/rechazar.php', { method: 'POST', body: { postulacion_id: id, motivo } });
    mostrarAlerta('alerta', data.mensaje, 'exito');
    await cargarLista();
  } catch (err) {
    mostrarAlerta('alerta', err.message);
  }
}

// --- v3.4: Personal Autorizado (histórico + KPI + export) -----------------
function limpiarFiltrosAutorizados() {
  document.getElementById('desde-autorizado').value = '';
  document.getElementById('hasta-autorizado').value = '';
  cargarAutorizados();
}

function paramsRangoAutorizado() {
  const desde = document.getElementById('desde-autorizado').value; // "AAAA-MM-DDTHH:MM"
  const hasta = document.getElementById('hasta-autorizado').value;
  const params = new URLSearchParams();
  if (desde) params.set('desde', desde.replace('T', ' ') + ':00');
  if (hasta) params.set('hasta', hasta.replace('T', ' ') + ':00');
  return params;
}

async function cargarAutorizados() {
  const tbody = document.getElementById('tbody-autorizado');
  const vacio = document.getElementById('autorizado-vacio');
  try {
    const data = await apiFetch(`/admin_contrato/historico.php?${paramsRangoAutorizado().toString()}`);

    document.getElementById('kpi-promedio').textContent = data.kpi_promedio_total ?? '—';
    document.getElementById('kpi-cantidad').textContent = data.kpi_cantidad_contratados;
    document.getElementById('kpi-promedio-postulante').textContent = data.kpi_promedio_postulante ?? '—';
    document.getElementById('kpi-promedio-admin').textContent = data.kpi_promedio_admin ?? '—';
    document.getElementById('kpi-promedio-jao').textContent = data.kpi_promedio_jao ?? '—';

    if (!data.postulaciones.length) {
      tbody.innerHTML = '';
      vacio.classList.remove('hidden');
      return;
    }
    vacio.classList.add('hidden');
    tbody.innerHTML = data.postulaciones.map(p => `
      <tr class="border-t">
        <td class="px-4 py-3 font-mono">${celdaDocumento(p)}</td>
        <td class="px-4 py-3">${p.nombre_completo}</td>
        <td class="px-4 py-3">${p.nombre_cargo}</td>
        <td class="px-4 py-3">${p.estado}</td>
        <td class="px-4 py-3 text-gray-500">${new Date(p.admin_autorizado_at).toLocaleString('es-CL')}</td>
        <td class="px-4 py-3">${p.tiempo_postulante ?? '—'}</td>
        <td class="px-4 py-3">${p.tiempo_admin ?? '—'}</td>
        <td class="px-4 py-3">${p.tiempo_jao ?? '—'}</td>
        <td class="px-4 py-3 font-medium">${p.tiempo_total ?? '—'}</td>
      </tr>`).join('');
  } catch (err) {
    mostrarAlerta('alerta', err.message);
  }
}

async function exportarAutorizadosExcel() {
  try {
    const res = await apiFetch(`/admin_contrato/exportar_excel.php?${paramsRangoAutorizado().toString()}`);
    const blob = await res.blob();
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'personal_autorizado.xlsx';
    document.body.appendChild(a);
    a.click();
    a.remove();
    window.URL.revokeObjectURL(url);
  } catch (err) {
    mostrarAlerta('alerta', err.message || 'No hay datos para exportar en ese rango.');
  }
}

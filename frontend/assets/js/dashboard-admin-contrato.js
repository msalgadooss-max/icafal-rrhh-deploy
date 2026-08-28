/**
 * v3.1: Admin_Contrato autoriza en PARALELO a que el postulante llena
 * su Etapa 2 -- no espera a que termine, y no necesita ver sus
 * documentos de Etapa 2 (eso lo revisa el JAO más adelante). Solo ve
 * lo que Jefe_Terreno ya aprobó: datos básicos + CV.
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
        <td class="px-4 py-3">${p.postulante_ya_completo
          ? '<span class="text-xs text-green-700 bg-green-50 px-2 py-1 rounded-md font-medium">✓ Ya completó Etapa 2</span>'
          : '<span class="text-xs text-gray-400">Aún llenando sus datos</span>'}</td>
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
  if (!confirm('¿Autorizar la contratación? No hace falta esperar a que el postulante termine sus datos.')) return;
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
  const motivo = prompt('Motivo del rechazo (queda solo en el registro interno, el postulante recibe un mensaje genérico):') || '';
  if (!confirm('¿Rechazar esta postulación?')) return;
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

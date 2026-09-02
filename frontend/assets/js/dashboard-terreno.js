(async () => {
  const usuario = await protegerDashboard('Jefe_Terreno');
  if (!usuario) return;
  await cargarLista();
  await cargarBanco();
  configurarTabs();
  iniciarEstadoVivo();
})();

// --- v4: límite diario de aprobaciones ------------------------------------
function renderLimiteAprobaciones(usadas, limite) {
  const cont = document.getElementById('limite-aprobaciones');
  if (!cont) return;
  const alTope = usadas >= limite;
  cont.innerHTML = `
    <div class="rounded-lg px-4 py-2.5 text-sm font-medium border ${alTope ? 'bg-red-50 border-red-200 text-red-700' : 'bg-gray-50 border-gray-200 text-gray-600'}">
      Aprobaciones de hoy: <strong>${usadas} / ${limite}</strong>
      ${alTope ? ' — alcanzaste el límite diario, podrás aprobar de nuevo mañana.' : ''}
    </div>`;
}

// --- v3.2: pestañas -------------------------------------------------------
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
  if (tab === 'en_proceso' && !EN_PROCESO_CARGADO) {
    EN_PROCESO_CARGADO = true;
    inicializarFiltros('en_proceso');
    cargarHistorico('en_proceso');
  }
  if (tab === 'contratados' && !CONTRATADOS_CARGADO) {
    CONTRATADOS_CARGADO = true;
    inicializarFiltros('contratados');
    cargarHistorico('contratados');
  }
  if (tab === 'cupos') {
    cargarCupos();
    cargarMisSolicitudes();
  }
  if (tab === 'recepcion') {
    cargarRecepcion();
  }
}

// --- v7: Recepción (cierre operativo, Bodega ya entregó el EPP) -----------
async function cargarRecepcion() {
  const tbody = document.getElementById('tbody-recepcion');
  const vacio = document.getElementById('recepcion-vacio');
  try {
    const data = await apiFetch('/terreno/recepcion_listar.php');
    if (!data.postulaciones.length) {
      tbody.innerHTML = '';
      vacio.classList.remove('hidden');
      return;
    }
    vacio.classList.add('hidden');
    tbody.innerHTML = data.postulaciones.map(p => `
      <tr class="border-t">
        <td class="px-4 py-3 font-mono">${p.rut}</td>
        <td class="px-4 py-3">${p.nombre_completo}</td>
        <td class="px-4 py-3">${p.nombre_cargo}</td>
        <td class="px-4 py-3 text-right">
          <button class="bg-green-600 hover:bg-green-700 text-white text-xs font-semibold px-3 py-1.5 rounded-lg" onclick="confirmarRecepcion(${p.id})">Confirmar recepción</button>
        </td>
      </tr>`).join('');
  } catch (err) {
    mostrarAlerta('alerta', err.message);
  }
}

async function confirmarRecepcion(id) {
  if (!confirm('¿Confirmas que fuiste a buscar a esta persona? Esto da por terminado el proceso completo.')) return;
  try {
    const data = await apiFetch('/terreno/recepcion_confirmar.php', { method: 'POST', body: { postulacion_id: id } });
    mostrarAlerta('alerta', data.mensaje, 'exito');
    await cargarRecepcion();
  } catch (err) {
    mostrarAlerta('alerta', err.message);
  }
}

// --- v4: Solicitar Cupos ---------------------------------------------------
async function cargarCupos() {
  const tbody = document.getElementById('tbody-cupos');
  const select = document.getElementById('cupo-cargo');
  try {
    const data = await apiFetch('/terreno/cupos_listar.php');
    // v6.10: se agrega "Otro" al final para poder pedir cupos de un
    // cargo que todavía no existe en el catálogo.
    select.innerHTML = data.cargos.map(c => `<option value="${c.id}">${c.nombre_cargo}</option>`).join('')
      + '<option value="__otro__">➕ Otro (agregar cargo nuevo)</option>';
    tbody.innerHTML = data.cargos.map(c => `
      <tr class="border-t">
        <td class="px-4 py-3">${c.nombre_cargo}</td>
        <td class="px-4 py-3">${c.cupos_totales}</td>
        <td class="px-4 py-3 font-semibold ${c.cupos_activos > 0 ? 'text-green-700' : 'text-gray-400'}">${c.cupos_activos}</td>
      </tr>`).join('');
  } catch (err) {
    mostrarAlerta('alerta', err.message);
  }
}

// --- v6.9: historial de solicitudes con su estado (Pendiente/Aprobada/ -----
// Rechazada) -- desde la reunión con Ricardo, solicitar cupos ya no los
// abre de inmediato, así que Jefe de Terreno necesita ver si el
// Administrador de Contrato ya resolvió su pedido.
const ETIQUETA_ESTADO_SOLICITUD = {
  Pendiente: '<span class="text-xs text-amber-700 bg-amber-50 px-2 py-1 rounded-md font-medium">Pendiente</span>',
  Aprobada: '<span class="text-xs text-green-700 bg-green-50 px-2 py-1 rounded-md font-medium">✓ Aprobada</span>',
  Rechazada: '<span class="text-xs text-red-700 bg-red-50 px-2 py-1 rounded-md font-medium">✕ Rechazada</span>',
};

async function cargarMisSolicitudes() {
  const tbody = document.getElementById('tbody-mis-solicitudes');
  const vacio = document.getElementById('mis-solicitudes-vacio');
  try {
    const data = await apiFetch('/terreno/mis_solicitudes.php');
    if (!data.solicitudes.length) {
      tbody.innerHTML = '';
      vacio.classList.remove('hidden');
      return;
    }
    vacio.classList.add('hidden');
    tbody.innerHTML = data.solicitudes.map(s => `
      <tr class="border-t">
        <td class="px-4 py-3">${s.nombre_cargo}${s.es_cargo_nuevo ? ' <span class="text-[10px] text-indigo-700 bg-indigo-50 px-1.5 py-0.5 rounded font-semibold align-middle">🆕 nuevo</span>' : ''}</td>
        <td class="px-4 py-3">${s.cantidad}</td>
        <td class="px-4 py-3">${ETIQUETA_ESTADO_SOLICITUD[s.estado] || s.estado}
          ${s.estado === 'Rechazada' && s.motivo_rechazo ? `<span class="block text-[11px] text-gray-400 mt-0.5">${s.motivo_rechazo}</span>` : ''}
        </td>
        <td class="px-4 py-3 text-gray-500">${new Date(s.creado_at).toLocaleString('es-CL')}</td>
      </tr>`).join('');
  } catch (err) {
    mostrarAlerta('alerta', err.message);
  }
}

// v6.10: si elige "Otro", se muestra el campo de texto para el nombre
// del cargo nuevo en vez del listado predeterminado.
document.getElementById('cupo-cargo').addEventListener('change', (e) => {
  const esOtro = e.target.value === '__otro__';
  document.getElementById('cupo-cargo-nuevo-wrap').classList.toggle('hidden', !esOtro);
  document.getElementById('cupo-cargo-nuevo').required = esOtro;
});

document.getElementById('form-cupo').addEventListener('submit', async (e) => {
  e.preventDefault();
  const cargoSeleccionado = document.getElementById('cupo-cargo').value;
  const cantidad = Number(document.getElementById('cupo-cantidad').value);
  const esOtro = cargoSeleccionado === '__otro__';
  const cargoNuevoNombre = document.getElementById('cupo-cargo-nuevo').value.trim();

  if (esOtro && !cargoNuevoNombre) {
    mostrarAlerta('alerta', 'Escribe el nombre del cargo nuevo.');
    return;
  }

  const body = esOtro
    ? { cargo_nuevo_nombre: cargoNuevoNombre, cantidad }
    : { cargo_id: Number(cargoSeleccionado), cantidad };

  try {
    const data = await apiFetch('/terreno/solicitar_cupo.php', { method: 'POST', body });
    mostrarAlerta('alerta', data.mensaje, 'exito');
    document.getElementById('cupo-cantidad').value = '';
    document.getElementById('cupo-cargo-nuevo').value = '';
    document.getElementById('cupo-cargo-nuevo-wrap').classList.add('hidden');
    document.getElementById('cupo-cargo').value = '';
    await cargarMisSolicitudes();
  } catch (err) {
    mostrarAlerta('alerta', err.message);
  }
});

async function cargarLista() {
  const tbody = document.getElementById('tbody-postulaciones');
  const vacio = document.getElementById('vacio');
  try {
    const data = await apiFetch('/terreno/listar.php');
    renderLimiteAprobaciones(data.aprobaciones_hoy, data.limite_aprobaciones_diarias);
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
        <td class="px-4 py-3">${p.comuna}</td>
        <td class="px-4 py-3">${p.telefono}</td>
        <td class="px-4 py-3 text-gray-500">${new Date(p.creado_at).toLocaleString('es-CL')}</td>
        <td class="px-4 py-3">${p.tiene_cv
          ? `<a href="${API_BASE_URL}/terreno/ver_cv.php?postulacion_id=${p.id}" target="_blank" class="text-blue-600 font-medium underline">Ver CV</a>`
          : (p.experiencia_sin_cv
              ? `<span class="text-xs text-amber-700 bg-amber-50 px-2 py-1 rounded cursor-help" title="${p.experiencia_sin_cv.replace(/"/g, '&quot;')}">Sin CV — ver experiencia ⓘ</span>`
              : '<span class="text-gray-400 text-xs">Sin CV</span>')}</td>
        <td class="px-4 py-3 text-right space-x-2">
          <button class="bg-green-600 hover:bg-green-700 text-white text-xs font-semibold px-3 py-1.5 rounded-lg" onclick="aprobar(${p.id})" title="Pasa a selección del Capataz en terreno">Aprobar</button>
          <button class="bg-red-100 hover:bg-red-200 text-red-700 text-xs font-semibold px-3 py-1.5 rounded-lg" onclick="rechazar(${p.id})">Rechazar</button>
        </td>
      </tr>`).join('');
  } catch (err) {
    mostrarAlerta('alerta', err.message);
  }
}

async function aprobar(id) {
  try {
    const data = await apiFetch('/terreno/aprobar.php', { method: 'POST', body: { postulacion_id: id } });
    mostrarAlerta('alerta', data.mensaje, 'exito');
    await cargarLista();
  } catch (err) {
    mostrarAlerta('alerta', err.message);
  }
}

async function rechazar(id) {
  const motivo = await pedirMotivoRechazo();
  if (motivo === null) return;
  try {
    await apiFetch('/terreno/rechazar.php', { method: 'POST', body: { postulacion_id: id, motivo } });
    mostrarAlerta('alerta', 'Postulación rechazada.', 'exito');
    await cargarLista();
  } catch (err) {
    mostrarAlerta('alerta', err.message);
  }
}

// --- v2: Banco de Postulantes ---------------------------------------------
let CARGOS_CON_CUPO = [];

async function cargarBanco() {
  const tbody = document.getElementById('tbody-banco');
  const vacio = document.getElementById('banco-vacio');
  try {
    const data = await apiFetch('/terreno/banco_listar.php');
    CARGOS_CON_CUPO = data.cargos_con_cupo;
    if (!data.banco.length) {
      tbody.innerHTML = '';
      vacio.classList.remove('hidden');
      return;
    }
    vacio.classList.add('hidden');
    tbody.innerHTML = data.banco.map(p => `
      <tr class="border-t">
        <td class="px-4 py-3 font-mono">${celdaDocumento(p)}</td>
        <td class="px-4 py-3">${p.nombre_completo}</td>
        <td class="px-4 py-3">${p.cargo_interes}</td>
        <td class="px-4 py-3">${p.comuna}</td>
        <td class="px-4 py-3 text-gray-500">${new Date(p.creado_at).toLocaleDateString('es-CL')}</td>
        <td class="px-4 py-3 text-gray-500">${p.retencion_hasta}</td>
        <td class="px-4 py-3 text-right">${botonInvitar(p.id)}</td>
      </tr>`).join('');
  } catch (err) {
    mostrarAlerta('alerta', err.message);
  }
}

function botonInvitar(postulacionId) {
  if (!CARGOS_CON_CUPO.length) {
    return '<span class="text-xs text-gray-400">Sin cupos abiertos</span>';
  }
  const opciones = CARGOS_CON_CUPO.map(c => `<option value="${c.id}">${c.nombre_cargo}</option>`).join('');
  return `
    <div class="flex items-center gap-2 justify-end">
      <select id="cargo-invitar-${postulacionId}" class="border border-gray-300 rounded-lg px-2 py-1 text-xs">${opciones}</select>
      <button class="bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold px-3 py-1.5 rounded-lg" onclick="invitarDelBanco(${postulacionId})">Invitar</button>
    </div>`;
}

async function invitarDelBanco(postulacionId) {
  const select = document.getElementById(`cargo-invitar-${postulacionId}`);
  const cargoId = Number(select.value);
  try {
    await apiFetch('/terreno/banco_invitar.php', { method: 'POST', body: { postulacion_id: postulacionId, cargo_id: cargoId } });
    mostrarAlerta('alerta', 'Persona invitada al proceso.', 'exito');
    await cargarLista();
    await cargarBanco();
  } catch (err) {
    mostrarAlerta('alerta', err.message);
  }
}

// --- v3.2: Histórico (Personal Aprobado en Proceso / Contratado) ----------
let EN_PROCESO_CARGADO = false;
let CONTRATADOS_CARGADO = false;

const ETIQUETAS_ETAPA = {
  Pre_aprobado_terreno: 'Esperando Etapa 2 / autorización',
  Datos_completados: 'Datos completados',
  Aprobado_admin: 'Autorizado, en cierre',
  Induccion_ok: 'Inducción realizada',
  EPP_listo: 'EPP listo',
};

function inicializarFiltros(vista) {
  const cont = document.getElementById(`filtros-${vista}`);
  cont.innerHTML = `
    <div><label class="block text-xs text-gray-600 mb-1">Desde</label>
      <input type="date" id="desde-${vista}" class="border border-gray-300 rounded-lg px-2 py-1.5 text-sm"></div>
    <div><label class="block text-xs text-gray-600 mb-1">Hasta</label>
      <input type="date" id="hasta-${vista}" class="border border-gray-300 rounded-lg px-2 py-1.5 text-sm"></div>
    <div><label class="block text-xs text-gray-600 mb-1">Aprobado por</label>
      <select id="aprobado_por-${vista}" class="border border-gray-300 rounded-lg px-2 py-1.5 text-sm"><option value="">Todos</option></select></div>
    <button class="bg-gray-800 hover:bg-gray-900 text-white text-xs font-semibold px-3 py-2 rounded-lg" onclick="cargarHistorico('${vista}')">Filtrar</button>
    <button class="bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-semibold px-3 py-2 rounded-lg" onclick="limpiarFiltros('${vista}')">Limpiar</button>
    <button class="ml-auto bg-gray-900 hover:bg-gray-800 text-white text-xs font-semibold px-3 py-2 rounded-lg" onclick="exportarHistoricoExcel('${vista}')">Exportar Excel</button>`;
}

async function exportarHistoricoExcel(vista) {
  const desde = document.getElementById(`desde-${vista}`).value;
  const hasta = document.getElementById(`hasta-${vista}`).value;
  const aprobadoPor = document.getElementById(`aprobado_por-${vista}`).value;
  const params = new URLSearchParams({ vista });
  if (desde) params.set('desde', desde);
  if (hasta) params.set('hasta', hasta);
  if (aprobadoPor) params.set('aprobado_por', aprobadoPor);

  try {
    const res = await apiFetch(`/terreno/exportar_excel.php?${params.toString()}`);
    const blob = await res.blob();
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `${vista}.xlsx`;
    document.body.appendChild(a);
    a.click();
    a.remove();
    window.URL.revokeObjectURL(url);
  } catch (err) {
    mostrarAlerta('alerta', err.message || 'No hay datos para exportar en ese rango.');
  }
}

function limpiarFiltros(vista) {
  document.getElementById(`desde-${vista}`).value = '';
  document.getElementById(`hasta-${vista}`).value = '';
  document.getElementById(`aprobado_por-${vista}`).value = '';
  cargarHistorico(vista);
}

async function cargarHistorico(vista) {
  const tbody = document.getElementById(`tbody-${vista}`);
  const vacio = document.getElementById(`${vista}-vacio`);
  const desde = document.getElementById(`desde-${vista}`).value;
  const hasta = document.getElementById(`hasta-${vista}`).value;
  const aprobadoPor = document.getElementById(`aprobado_por-${vista}`).value;

  const params = new URLSearchParams({ vista });
  if (desde) params.set('desde', desde);
  if (hasta) params.set('hasta', hasta);
  if (aprobadoPor) params.set('aprobado_por', aprobadoPor);

  try {
    const data = await apiFetch(`/terreno/historico.php?${params.toString()}`);

    const selectAprobadoPor = document.getElementById(`aprobado_por-${vista}`);
    if (selectAprobadoPor.options.length <= 1) {
      selectAprobadoPor.innerHTML = '<option value="">Todos</option>' +
        data.jefes_terreno.map(u => `<option value="${u.id}">${u.nombre}</option>`).join('');
      selectAprobadoPor.value = aprobadoPor;
    }

    if (!data.postulaciones.length) {
      tbody.innerHTML = '';
      vacio.classList.remove('hidden');
      return;
    }
    vacio.classList.add('hidden');

    tbody.innerHTML = data.postulaciones.map(p => {
      const fecha = p.fecha_aprobacion ? new Date(p.fecha_aprobacion).toLocaleString('es-CL') : '—';
      const aprobador = p.aprobado_por_nombre || '—';
      const filasComunes = `
        <td class="px-4 py-3 font-mono">${celdaDocumento(p)}</td>
        <td class="px-4 py-3">${p.nombre_completo}</td>
        <td class="px-4 py-3">${p.nombre_cargo}</td>`;
      if (vista === 'en_proceso') {
        return `<tr class="border-t">${filasComunes}
          <td class="px-4 py-3">${ETIQUETAS_ETAPA[p.estado] || p.estado}</td>
          <td class="px-4 py-3">${aprobador}</td>
          <td class="px-4 py-3 text-gray-500">${fecha}</td>
        </tr>`;
      }
      return `<tr class="border-t">${filasComunes}
        <td class="px-4 py-3">${aprobador}</td>
        <td class="px-4 py-3 text-gray-500">${fecha}</td>
      </tr>`;
    }).join('');
  } catch (err) {
    mostrarAlerta('alerta', err.message);
  }
}

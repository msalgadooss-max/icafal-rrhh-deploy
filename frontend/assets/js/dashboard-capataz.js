/**
 * v6.9 - Panel de Capataz: la selección rápida en portería que propuso
 * Ricardo (reunión 28-ago). Reutiliza los mismos endpoints de
 * Jefe_Terreno (/terreno/listar.php, aprobar.php, rechazar.php,
 * ver_cv.php -- todos ya abiertos también al rol Capataz), pero con una
 * pantalla pensada para hacerse de pie, rápido: RUT grande para
 * comparar con la cédula, y dos botones grandes.
 *
 * No incluye Banco de Postulantes ni límite diario de aprobaciones --
 * esas son herramientas de gestión de Jefe_Terreno, no de la selección
 * en portería.
 */
(async () => {
  const usuario = await protegerDashboard('Capataz');
  if (!usuario) return;
  await cargarLista();
  setInterval(cargarLista, 15000);
})();

async function cargarLista() {
  const cont = document.getElementById('lista-postulantes');
  const vacio = document.getElementById('vacio');
  try {
    const data = await apiFetch('/terreno/listar.php');
    if (!data.postulaciones.length) {
      cont.innerHTML = '';
      vacio.classList.remove('hidden');
      return;
    }
    vacio.classList.add('hidden');
    cont.innerHTML = data.postulaciones.map(p => `
      <div class="bg-white rounded-xl shadow-sm p-5">
        <div class="flex items-start justify-between gap-4 flex-wrap">
          <div>
            <p class="text-2xl font-mono font-bold text-gray-900 tracking-wide">${celdaDocumento(p)}</p>
            <p class="text-base font-semibold text-gray-800">${p.nombre_completo}</p>
            <p class="text-sm text-gray-500">${p.nombre_cargo} · ${p.comuna}</p>
            <p class="text-xs text-green-700 mt-1">✓ Aprobado por Jefe de Terreno${p.aprobado_jt_por_nombre ? ` (${p.aprobado_jt_por_nombre})` : ''}</p>
          </div>
          ${p.tiene_cv
            ? `<a href="${API_BASE_URL}/terreno/ver_cv.php?postulacion_id=${p.id}" target="_blank" class="text-blue-600 font-medium underline text-sm">Ver CV</a>`
            : (p.experiencia_sin_cv
                ? `<span class="text-xs text-amber-700 bg-amber-50 px-2 py-1 rounded cursor-help" title="${p.experiencia_sin_cv.replace(/"/g, '&quot;')}">Sin CV — ver experiencia ⓘ</span>`
                : '<span class="text-gray-400 text-xs">Sin CV</span>')}
        </div>
        <div class="flex gap-3 mt-4">
          <button class="flex-1 bg-green-600 hover:bg-green-700 text-white font-bold text-base rounded-lg py-3" onclick="seleccionar(${p.id})">
            ✓ Selecciona — pasa a Etapa 2
          </button>
          <button class="flex-1 bg-red-100 hover:bg-red-200 text-red-700 font-bold text-base rounded-lg py-3" onclick="noSeleccionar(${p.id})">
            ✕ No selecciona
          </button>
        </div>
      </div>`).join('');
  } catch (err) {
    mostrarAlerta('alerta', err.message);
  }
}

async function seleccionar(id) {
  try {
    await apiFetch('/terreno/aprobar.php', { method: 'POST', body: { postulacion_id: id } });
    mostrarAlerta('alerta', 'Seleccionado. Pasa a revisión del Administrador de Contrato.', 'exito');
    await cargarLista();
  } catch (err) {
    mostrarAlerta('alerta', err.message);
  }
}

async function noSeleccionar(id) {
  const motivo = await pedirMotivoRechazo();
  if (motivo === null) return;
  try {
    await apiFetch('/terreno/rechazar.php', { method: 'POST', body: { postulacion_id: id, motivo } });
    mostrarAlerta('alerta', 'Postulación no continúa.', 'exito');
    await cargarLista();
  } catch (err) {
    mostrarAlerta('alerta', err.message);
  }
}

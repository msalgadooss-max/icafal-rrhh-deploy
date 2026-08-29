(async () => {
  const usuario = await protegerDashboard('Prevencionista');
  if (!usuario) return;
  await cargarLista();
})();

async function cargarLista() {
  const tbody = document.getElementById('tbody-postulaciones');
  const vacio = document.getElementById('vacio');
  try {
    const data = await apiFetch('/prevencion/listar.php');
    if (!data.postulaciones.length) {
      tbody.innerHTML = '';
      vacio.classList.remove('hidden');
      return;
    }
    vacio.classList.add('hidden');
    tbody.innerHTML = data.postulaciones.map(p => {
      const completo = p.videos_total > 0 && p.videos_vistos === p.videos_total;
      return `
      <tr class="border-t">
        <td class="px-4 py-3 font-mono">${p.rut}</td>
        <td class="px-4 py-3">${p.nombre_completo}</td>
        <td class="px-4 py-3">${p.nombre_cargo}</td>
        <td class="px-4 py-3">
          <span class="text-xs font-medium px-2 py-1 rounded-md ${completo ? 'bg-green-50 text-green-700' : 'bg-amber-50 text-amber-700'}">
            ${p.videos_vistos}/${p.videos_total} videos vistos
          </span>
        </td>
        <td class="px-4 py-3 text-right">
          <button class="bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold px-3 py-1.5 rounded-lg" onclick="marcarInduccion(${p.id})">Inducción Realizada</button>
        </td>
      </tr>`;
    }).join('');
  } catch (err) {
    mostrarAlerta('alerta', err.message);
  }
}

async function marcarInduccion(id) {
  if (!confirm('¿Confirmas que dictaste la charla ODI a esta persona?')) return;
  try {
    await apiFetch('/prevencion/marcar_induccion.php', { method: 'POST', body: { postulacion_id: id } });
    mostrarAlerta('alerta', 'Inducción registrada.', 'exito');
    await cargarLista();
  } catch (err) {
    mostrarAlerta('alerta', err.message);
  }
}

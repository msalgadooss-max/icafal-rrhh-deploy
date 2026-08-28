(async () => {
  const usuario = await protegerDashboard('Jefe_Bodega');
  if (!usuario) return;
  await cargarLista();
})();

async function cargarLista() {
  const tbody = document.getElementById('tbody-postulaciones');
  const vacio = document.getElementById('vacio');
  try {
    const data = await apiFetch('/bodega/listar.php');
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
        <td class="px-4 py-3">${p.talla_calzado}</td>
        <td class="px-4 py-3">${p.talla_pantalon}</td>
        <td class="px-4 py-3">${p.talla_polera}</td>
        <td class="px-4 py-3 text-right">
          <button class="bg-amber-500 hover:bg-amber-600 text-white text-xs font-semibold px-3 py-1.5 rounded-lg" onclick="marcarEpp(${p.id})">EPP Listo</button>
        </td>
      </tr>`).join('');
  } catch (err) {
    mostrarAlerta('alerta', err.message);
  }
}

async function marcarEpp(id) {
  try {
    await apiFetch('/bodega/marcar_epp.php', { method: 'POST', body: { postulacion_id: id } });
    mostrarAlerta('alerta', 'Kit de EPP marcado como listo.', 'exito');
    await cargarLista();
  } catch (err) {
    mostrarAlerta('alerta', err.message);
  }
}

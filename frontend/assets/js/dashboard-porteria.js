(async () => {
  const usuario = await protegerDashboard('Porteria');
  if (!usuario) return;
})();

let ULTIMA_CONSULTA = null;

document.getElementById('form-consulta').addEventListener('submit', async (e) => {
  e.preventDefault();
  const btn = document.getElementById('btn-consultar');
  const resultadoDiv = document.getElementById('resultado');
  btn.disabled = true;
  btn.textContent = 'Verificando...';
  resultadoDiv.innerHTML = '';

  const rut = document.getElementById('rut').value;
  const codigo = document.getElementById('codigo').value.trim().toUpperCase();
  ULTIMA_CONSULTA = { rut, codigo };

  try {
    const data = await apiFetch('/porteria/consultar.php', { method: 'POST', body: { rut, codigo } });
    const autorizado = data.estado_acceso === 'AUTORIZADO';
    resultadoDiv.innerHTML = `
      <div class="${autorizado ? 'bg-green-500' : 'bg-red-600'} rounded-xl p-6 text-center text-white shadow-lg">
        <p class="text-2xl font-extrabold">${data.estado_acceso}</p>
        ${data.mensaje ? `<p class="text-sm font-medium mt-1 opacity-90">${data.mensaje}</p>` : ''}
        <div class="bg-white/10 rounded-lg p-3 text-left space-y-1 mt-4 text-sm">
          <p><span class="opacity-70">Nombre:</span> <strong>${data.nombre_completo}</strong></p>
          <p><span class="opacity-70">RUT:</span> <strong>${data.rut}</strong></p>
          <p><span class="opacity-70">Cargo:</span> <strong>${data.cargo}</strong></p>
        </div>
      </div>`;
    // v7: además de la consulta de acceso a la obra (post-EPP), se
    // ofrece confirmar el ingreso a faena del día 1 (candado para JAO).
    await mostrarBotonIngresoFaena(rut, codigo);
  } catch (err) {
    mostrarAlerta('alerta', err.message);
  } finally {
    btn.disabled = false;
    btn.textContent = 'Verificar';
  }
});

async function mostrarBotonIngresoFaena(rut, codigo) {
  const cont = document.getElementById('ingreso-faena');
  cont.innerHTML = '';
  try {
    const data = await apiFetch(`/porteria/ingreso_estado.php?rut=${encodeURIComponent(rut)}&codigo=${encodeURIComponent(codigo)}`);
    if (data.ya_confirmado) {
      cont.innerHTML = `<div class="bg-green-50 border border-green-200 text-green-700 text-sm font-medium rounded-lg px-4 py-3 text-center">✓ Ingreso a faena ya confirmado</div>`;
    } else if (data.puede_confirmar) {
      cont.innerHTML = `<button id="btn-ingreso-faena" onclick="confirmarIngresoFaena()" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg py-3 text-base">Confirmar ingreso a faena</button>`;
    }
  } catch (err) {
    // Si no aplica (ej. otra fase del proceso), simplemente no se muestra nada.
  }
}

async function confirmarIngresoFaena() {
  if (!ULTIMA_CONSULTA) return;
  const btn = document.getElementById('btn-ingreso-faena');
  btn.disabled = true;
  btn.textContent = 'Confirmando...';
  try {
    const data = await apiFetch('/porteria/marcar_ingreso.php', { method: 'POST', body: ULTIMA_CONSULTA });
    mostrarAlerta('alerta', data.mensaje, 'exito');
    await mostrarBotonIngresoFaena(ULTIMA_CONSULTA.rut, ULTIMA_CONSULTA.codigo);
  } catch (err) {
    mostrarAlerta('alerta', err.message);
    btn.disabled = false;
    btn.textContent = 'Confirmar ingreso a faena';
  }
}

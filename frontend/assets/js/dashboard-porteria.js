(async () => {
  const usuario = await protegerDashboard('Porteria');
  if (!usuario) return;
})();

document.getElementById('form-consulta').addEventListener('submit', async (e) => {
  e.preventDefault();
  const btn = document.getElementById('btn-consultar');
  const resultadoDiv = document.getElementById('resultado');
  btn.disabled = true;
  btn.textContent = 'Verificando...';
  resultadoDiv.innerHTML = '';

  try {
    const data = await apiFetch('/porteria/consultar.php', {
      method: 'POST',
      body: {
        rut: document.getElementById('rut').value,
        codigo: document.getElementById('codigo').value.trim().toUpperCase(),
      },
    });
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
  } catch (err) {
    mostrarAlerta('alerta', err.message);
  } finally {
    btn.disabled = false;
    btn.textContent = 'Verificar';
  }
});

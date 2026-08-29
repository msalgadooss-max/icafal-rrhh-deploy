/**
 * v6.9 - Módulo de inducción en video (Ricardo, reunión 28-ago): el
 * postulante ve los videos de seguridad apenas es autorizado, antes de
 * presentarse -- el video se marca "visto" solo, al terminar de verlo
 * (evento `ended`), sin necesidad de un botón aparte.
 */
const form = document.getElementById('form-induccion');
const rutInput = document.getElementById('rut');
const resultadoDiv = document.getElementById('resultado');
const alertaDiv = document.getElementById('alerta');

formatearRutInput(rutInput);

const params = new URLSearchParams(window.location.search);
if (params.get('rut')) rutInput.value = params.get('rut');
if (params.get('codigo')) document.getElementById('codigo').value = params.get('codigo');

let ULTIMO_RUT = '';
let ULTIMO_CODIGO = '';

form.addEventListener('submit', async (e) => {
  e.preventDefault();
  alertaDiv.innerHTML = '';
  resultadoDiv.innerHTML = '';

  const codigo = document.getElementById('codigo').value.trim().toUpperCase();
  ULTIMO_RUT = rutInput.value;
  ULTIMO_CODIGO = codigo;

  try {
    const data = await apiFetch('/public/induccion_listar.php', {
      method: 'POST',
      body: { rut: rutInput.value, codigo_seguimiento: codigo },
    });
    renderVideos(data);
  } catch (err) {
    alertaDiv.innerHTML = `<div class="bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg px-4 py-3">${err.message}</div>`;
  }
});

function renderVideos(data) {
  const total = data.videos.length;
  const vistos = data.videos.filter(v => v.visto).length;

  resultadoDiv.innerHTML = `
    <div class="bg-white shadow-sm rounded-xl p-4">
      <p class="font-bold text-gray-900">${data.nombre_completo}</p>
      <p class="text-sm ${vistos === total ? 'text-green-600 font-semibold' : 'text-gray-500'}">${vistos} de ${total} videos vistos</p>
    </div>
    ${data.videos.map(v => `
      <div class="bg-white shadow-sm rounded-xl p-4">
        <div class="flex items-center justify-between mb-2">
          <p class="font-semibold text-gray-900 text-sm">${v.titulo}</p>
          <span id="badge-video-${v.id}" class="text-xs font-medium px-2 py-1 rounded-md ${v.visto ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-400'}">${v.visto ? '✓ Visto' : 'Pendiente'}</span>
        </div>
        <video controls class="w-full rounded-lg" onended="marcarVisto(${v.id})">
          <source src="${v.url}" type="video/mp4">
          Tu navegador no puede reproducir este video.
        </video>
      </div>`).join('')}
  `;
}

async function marcarVisto(videoId) {
  try {
    await apiFetch('/public/induccion_marcar_visto.php', {
      method: 'POST',
      body: { rut: ULTIMO_RUT, codigo_seguimiento: ULTIMO_CODIGO, video_id: videoId },
    });
    const badge = document.getElementById(`badge-video-${videoId}`);
    if (badge) {
      badge.textContent = '✓ Visto';
      badge.className = 'text-xs font-medium px-2 py-1 rounded-md bg-green-50 text-green-700';
    }
  } catch (err) {
    // silencioso: no queremos interrumpir al postulante si esto falla
  }
}

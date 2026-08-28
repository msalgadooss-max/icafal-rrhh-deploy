/**
 * v3.4 - "Estado en vivo": widget compartido por los 4 dashboards
 * (Terreno, Admin_Contrato, JAO, Gerencia). Se actualiza solo cada 10
 * segundos, estilo rastreo de pedido -- nombre + cargo + en qué fase
 * está. No requiere lógica distinta por rol: el backend ya entrega
 * texto listo para mostrar y nunca datos sensibles.
 *
 * v4: cada tarjeta es clickeable -- abre un panel con una línea de
 * progreso "inicio -> meta" (mismos pasos que ve el propio postulante
 * en su seguimiento), calculada por el backend en estado_vivo.php.
 *
 * Uso: <div id="estado-vivo"></div> en el HTML, y llamar
 * iniciarEstadoVivo() una vez cargada la página.
 */
let ESTADO_VIVO_ULTIMO = [];

function iniciarEstadoVivo() {
  const cont = document.getElementById('estado-vivo');
  if (!cont) return;

  cont.innerHTML = `
    <div class="bg-white rounded-xl shadow-sm p-4 mb-6">
      <div class="flex items-center gap-2 mb-3">
        <span class="relative flex h-2.5 w-2.5">
          <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
          <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-green-500"></span>
        </span>
        <p class="text-sm font-semibold text-gray-800">Estado en vivo</p>
        <span class="text-xs text-gray-400">se actualiza solo · toca a alguien para ver su avance</span>
      </div>
      <div id="estado-vivo-lista" class="flex gap-3 overflow-x-auto pb-1"></div>
    </div>
    <div id="estado-vivo-modal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" onclick="if(event.target===this) cerrarDetalleTrabajador()">
      <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full p-6 relative">
        <button onclick="cerrarDetalleTrabajador()" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600 text-xl leading-none">✕</button>
        <div id="estado-vivo-modal-contenido"></div>
      </div>
    </div>`;

  cargarEstadoVivo();
  setInterval(cargarEstadoVivo, 10000);
}

async function cargarEstadoVivo() {
  const lista = document.getElementById('estado-vivo-lista');
  if (!lista) return;
  try {
    const data = await apiFetch('/estado_vivo.php');
    ESTADO_VIVO_ULTIMO = data.trabajadores;
    if (!data.trabajadores.length) {
      lista.innerHTML = '<p class="text-sm text-gray-400 py-2">No hay trabajadores en proceso ahora mismo.</p>';
      return;
    }
    lista.innerHTML = data.trabajadores.map(t => `
      <div class="shrink-0 w-56 border rounded-lg px-3 py-2.5 cursor-pointer transition hover:shadow-md hover:-translate-y-0.5 ${t.contratado ? 'bg-green-50 border-green-200' : (t.pendiente_de_ti ? 'bg-amber-50 border-amber-300' : 'bg-gray-50 border-gray-200')}"
           onclick="abrirDetalleTrabajador(${t.id})">
        <p class="text-sm font-semibold text-gray-900 truncate">${t.nombre_completo}</p>
        <p class="text-xs text-gray-500 mb-1.5">${t.nombre_cargo}</p>
        <p class="text-xs font-medium ${t.contratado ? 'text-green-700' : 'text-blue-700'}">${t.fase}</p>
        ${t.pendiente_de_ti ? '<p class="text-[11px] font-semibold text-amber-700 mt-1">👉 Pendiente en tu bandeja</p>' : ''}
      </div>`).join('');

    // Si el modal de detalle está abierto para alguien que sigue en la
    // lista, refresca su contenido con el dato nuevo (sin cerrarlo).
    const modal = document.getElementById('estado-vivo-modal');
    if (modal && !modal.classList.contains('hidden')) {
      const idAbierto = Number(modal.dataset.idAbierto);
      const actualizado = data.trabajadores.find(t => t.id === idAbierto);
      if (actualizado) renderDetalleTrabajador(actualizado);
    }
  } catch (err) {
    // silencioso: no queremos que un widget secundario tape el resto del dashboard
    lista.innerHTML = '<p class="text-sm text-gray-400 py-2">No se pudo cargar el estado en vivo.</p>';
  }
}

function abrirDetalleTrabajador(id) {
  const trabajador = ESTADO_VIVO_ULTIMO.find(t => t.id === id);
  if (!trabajador) return;
  const modal = document.getElementById('estado-vivo-modal');
  modal.dataset.idAbierto = id;
  modal.classList.remove('hidden');
  renderDetalleTrabajador(trabajador);
}

function cerrarDetalleTrabajador() {
  const modal = document.getElementById('estado-vivo-modal');
  if (modal) { modal.classList.add('hidden'); delete modal.dataset.idAbierto; }
}

function renderDetalleTrabajador(t) {
  const cont = document.getElementById('estado-vivo-modal-contenido');
  if (!cont) return;

  const pasos = t.pasos || [];
  const completados = pasos.filter(p => p.completado).length;
  const pctLinea = pasos.length > 1 ? (Math.max(completados - 1, 0) / (pasos.length - 1)) * 100 : 0;
  const idxActual = Math.min(completados, pasos.length - 1);

  const puntos = pasos.map((p, idx) => {
    const esActual = idx === idxActual && !t.contratado;
    let circulo;
    if (p.completado && !esActual) {
      circulo = `<div class="w-7 h-7 rounded-full bg-green-500 text-white flex items-center justify-center text-xs font-bold shadow-sm">✓</div>`;
    } else if (esActual) {
      circulo = `<div class="w-7 h-7 rounded-full bg-blue-600 text-white flex items-center justify-center text-[11px] font-bold shadow-md ring-4 ring-blue-100 animate-pulse">●</div>`;
    } else {
      circulo = `<div class="w-7 h-7 rounded-full bg-gray-200 border border-gray-300"></div>`;
    }
    return `
      <div class="flex flex-col items-center text-center gap-2" style="flex:1; min-width:0;">
        ${circulo}
        <p class="text-[11px] leading-tight ${p.completado || esActual ? 'text-gray-800 font-medium' : 'text-gray-400'}" style="max-width:88px;">${p.etiqueta}</p>
      </div>`;
  }).join('<div style="flex:0.4;"></div>');

  cont.innerHTML = `
    <div class="mb-5">
      <p class="text-xs uppercase tracking-wide text-gray-400 font-semibold mb-1">Avance del proceso</p>
      <h3 class="text-lg font-bold text-gray-900">${t.nombre_completo}</h3>
      <p class="text-sm text-gray-500">${t.nombre_cargo}</p>
    </div>

    <div class="relative mb-2 px-3">
      <div class="absolute left-3 right-3 h-1 bg-gray-200 rounded-full" style="top:14px;"></div>
      <div class="absolute left-3 h-1 bg-green-500 rounded-full transition-all duration-500" style="top:14px; width:calc((100% - 24px) * ${pctLinea / 100});"></div>
      <div class="relative flex items-start">${puntos}</div>
    </div>

    <div class="mt-6 rounded-xl px-4 py-3 text-sm font-semibold text-center ${t.contratado ? 'bg-green-50 text-green-700' : 'bg-blue-50 text-blue-700'}">
      ${t.fase}
    </div>
    ${t.pendiente_de_ti ? '<div class="mt-3 rounded-xl px-4 py-3 text-sm font-semibold text-center bg-amber-50 text-amber-800 border border-amber-200">👉 El postulante está pendiente en tu bandeja</div>' : ''}`;
}

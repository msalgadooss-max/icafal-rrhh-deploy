/**
 * Guard de acceso compartido por todos los dashboards internos.
 * Verifica sesión + rol contra /auth/me.php (control real de todos
 * modos ocurre en el servidor en cada endpoint; esto solo evita mostrar
 * pantallas equivocadas y mejora la UX).
 */
async function protegerDashboard(rolEsperado) {
  try {
    const data = await apiFetch('/auth/me.php');
    setCsrfToken(data.csrf_token);
    if (data.usuario.rol !== rolEsperado) {
      window.location.href = 'no-autorizado.html';
      return null;
    }
    document.querySelectorAll('[data-usuario-nombre]').forEach(el => {
      el.textContent = data.usuario.nombre;
    });
    if (data.modo_desarrollador) {
      mostrarBannerDesarrollador(data.dev_nombre);
    }
    return data.usuario;
  } catch (e) {
    window.location.href = '../public/login.html';
    return null;
  }
}

/**
 * v5: cuando un Desarrollador "entró como" este rol (ver
 * dev/entrar_como.php), se muestra una franja fija arriba de cualquier
 * dashboard para que sea obvio que es una sesión prestada, con un link
 * directo para volver al panel de desarrollador.
 */
function mostrarBannerDesarrollador(devNombre) {
  const banner = document.createElement('div');
  banner.className = 'bg-amber-500 text-white text-xs font-semibold text-center py-1.5 px-4 sticky top-0 z-40';
  banner.innerHTML = `🛠 Modo desarrollador — ${devNombre || 'Desarrollador'} está viendo este dashboard prestado.
    <button onclick="volverAPanelDev()" class="underline ml-2">Volver al panel de desarrollador</button>`;
  document.body.prepend(banner);
}

async function volverAPanelDev() {
  try {
    await apiFetch('/dev/volver.php', { method: 'POST' });
  } finally {
    window.location.href = '../dashboards/dev.html';
  }
}

async function cerrarSesion() {
  try {
    await apiFetch('/auth/logout.php', { method: 'POST' });
  } finally {
    sessionStorage.removeItem('csrf_token');
    window.location.href = '../public/login.html';
  }
}

/**
 * v3: RUT + indicador visual cuando tipo_documento no es 'RUT' (persona
 * sin cédula chilena todavía -- ej. extranjero recién llegado).
 */
function celdaDocumento(p) {
  if (p.tipo_documento && p.tipo_documento !== 'RUT') {
    return `${p.rut} <span class="inline-block bg-amber-100 text-amber-700 text-[10px] font-bold px-1.5 py-0.5 rounded ml-1" title="Sin RUT chileno todavía">${p.tipo_documento}</span>`;
  }
  return p.rut;
}

// --- v6.9: motivo de rechazo estandarizado ---------------------------------
// Antes cada dashboard pedía el motivo con un prompt() de texto libre.
// Ricardo pidió (reunión 28-ago) motivos estandarizados y auditables para
// poder defender legalmente cada rechazo. Se usa en Terreno, Capataz y
// Admin_Contrato -- por eso vive en el archivo compartido por todos.
const MOTIVOS_RECHAZO_ESTANDAR = [
  'No hay cupos disponibles',
  'No cumple con los requisitos del cargo',
  'Documentación incompleta o ilegible',
  'No se presentó / no fue posible contactarlo',
  'Otro motivo',
];

function pedirMotivoRechazo() {
  return new Promise((resolve) => {
    let modal = document.getElementById('modal-motivo-rechazo');
    if (!modal) {
      modal = document.createElement('div');
      modal.id = 'modal-motivo-rechazo';
      modal.className = 'fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4';
      document.body.appendChild(modal);
    }

    const cerrar = (valor) => { modal.classList.add('hidden'); resolve(valor); };

    modal.innerHTML = `
      <div class="bg-white rounded-2xl shadow-2xl max-w-sm w-full p-6">
        <h3 class="font-bold text-gray-900 mb-1">Motivo del rechazo</h3>
        <p class="text-xs text-gray-500 mb-4">Queda solo en el registro interno. El postulante recibe un mensaje genérico, nunca este motivo.</p>
        <select id="select-motivo-rechazo" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm mb-3">
          ${MOTIVOS_RECHAZO_ESTANDAR.map(m => `<option value="${m}">${m}</option>`).join('')}
        </select>
        <textarea id="detalle-motivo-rechazo" class="hidden w-full border border-gray-300 rounded-lg px-3 py-2 text-sm mb-3" rows="2" placeholder="Especifica el motivo..."></textarea>
        <div class="flex gap-3">
          <button id="btn-cancelar-motivo" class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-semibold rounded-lg py-2">Cancelar</button>
          <button id="btn-confirmar-motivo" class="flex-1 bg-red-600 hover:bg-red-700 text-white text-sm font-semibold rounded-lg py-2">Rechazar</button>
        </div>
      </div>`;
    modal.classList.remove('hidden');

    const select = document.getElementById('select-motivo-rechazo');
    const detalle = document.getElementById('detalle-motivo-rechazo');
    select.addEventListener('change', () => {
      detalle.classList.toggle('hidden', select.value !== 'Otro motivo');
    });

    document.getElementById('btn-cancelar-motivo').addEventListener('click', () => cerrar(null));
    modal.addEventListener('click', (e) => { if (e.target === modal) cerrar(null); });
    document.getElementById('btn-confirmar-motivo').addEventListener('click', () => {
      const motivo = select.value === 'Otro motivo'
        ? (detalle.value.trim() || 'Otro motivo')
        : select.value;
      cerrar(motivo);
    });
  });
}

function mostrarAlerta(contenedorId, mensaje, tipo = 'error') {
  const el = document.getElementById(contenedorId);
  if (!el) return;
  const clases = tipo === 'error'
    ? 'bg-red-50 text-red-700 border-red-200'
    : 'bg-green-50 text-green-700 border-green-200';
  el.innerHTML = `<div class="border rounded-lg px-4 py-3 text-sm ${clases}">${mensaje}</div>`;
  setTimeout(() => { el.innerHTML = ''; }, 6000);
}

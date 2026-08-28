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

function mostrarAlerta(contenedorId, mensaje, tipo = 'error') {
  const el = document.getElementById(contenedorId);
  if (!el) return;
  const clases = tipo === 'error'
    ? 'bg-red-50 text-red-700 border-red-200'
    : 'bg-green-50 text-green-700 border-green-200';
  el.innerHTML = `<div class="border rounded-lg px-4 py-3 text-sm ${clases}">${mensaje}</div>`;
  setTimeout(() => { el.innerHTML = ''; }, 6000);
}

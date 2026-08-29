/**
 * v5 - Panel de Desarrollador: entrar a cualquier rol sin contraseña,
 * más QR/correo del link de postulación.
 */
const RUTA_POR_ROL = {
  Jefe_Terreno: 'terreno.html',
  Capataz: 'capataz.html',
  Admin_Contrato: 'admin_contrato.html',
  Prevencionista: 'prevencion.html',
  Jefe_Bodega: 'bodega.html',
  Jefe_Administrativo: 'admin_general.html',
  Gerencia: 'gerencia.html',
  Porteria: 'porteria.html',
};

const ETIQUETAS_ROL = {
  Jefe_Terreno: 'Jefe de Terreno',
  Capataz: 'Capataz',
  Admin_Contrato: 'Admin. de Contrato',
  Jefe_Administrativo: 'Jefe Administrativo (JAO)',
  Prevencionista: 'Prevencionista',
  Jefe_Bodega: 'Jefe de Bodega',
  Porteria: 'Portería',
  Gerencia: 'Gerencia',
};

(async () => {
  const usuario = await protegerDashboard('Desarrollador');
  if (!usuario) return;
  await cargarUsuarios();
  configurarTabs();
  mostrarQr();
})();

let USUARIOS_INTERNOS = [];

async function cargarUsuarios() {
  const cont = document.getElementById('lista-usuarios');
  try {
    const data = await apiFetch('/dev/listar_usuarios.php');
    USUARIOS_INTERNOS = data.usuarios;
    cont.innerHTML = data.usuarios.map(u => `
      <button onclick="entrarComo(${u.id})"
              class="flex items-center justify-between border border-gray-200 rounded-lg px-4 py-3 text-left hover:border-blue-400 hover:bg-blue-50 transition">
        <span>
          <span class="block text-sm font-semibold text-gray-900">${ETIQUETAS_ROL[u.rol] || u.rol}</span>
          <span class="block text-xs text-gray-500">${u.nombre} · ${u.correo}</span>
        </span>
        <span class="text-blue-600 text-xs font-semibold">Entrar →</span>
      </button>`).join('');

    const selectRol = document.getElementById('rol-qr');
    selectRol.innerHTML = data.usuarios.map(u =>
      `<option value="${u.id}">${ETIQUETAS_ROL[u.rol] || u.rol} (${u.nombre})</option>`).join('');
  } catch (err) {
    mostrarAlerta('alerta', err.message);
  }
}

async function entrarComo(usuarioId) {
  try {
    const data = await apiFetch('/dev/entrar_como.php', { method: 'POST', body: { usuario_id: usuarioId } });
    const ruta = RUTA_POR_ROL[data.usuario.rol];
    if (!ruta) {
      mostrarAlerta('alerta', 'No hay dashboard configurado para ese rol.');
      return;
    }
    window.location.href = ruta;
  } catch (err) {
    mostrarAlerta('alerta', err.message);
  }
}

function configurarTabs() {
  document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.tab-btn').forEach(b => {
        const activo = b === btn;
        b.classList.toggle('border-blue-600', activo);
        b.classList.toggle('text-blue-600', activo);
        b.classList.toggle('border-transparent', !activo);
        b.classList.toggle('text-gray-500', !activo);
      });
      document.querySelectorAll('.tab-panel').forEach(panel => {
        panel.classList.toggle('hidden', panel.id !== `panel-${btn.dataset.tab}`);
      });
    });
  });
}

function mostrarQr() {
  const url = `${window.location.origin}/frontend/public/index.html`;
  document.getElementById('url-postulacion').textContent = url;
  // eslint-disable-next-line no-undef
  new QRCode(document.getElementById('qr'), { text: url, width: 200, height: 200 });
}

// --- v6.4: QR de acceso directo por rol (sin correo ni clave) --------------
document.getElementById('btn-generar-qr-rol').addEventListener('click', async () => {
  const usuarioId = Number(document.getElementById('rol-qr').value);
  if (!usuarioId) return;
  try {
    const data = await apiFetch('/dev/generar_qr_acceso.php', { method: 'POST', body: { usuario_id: usuarioId } });
    document.getElementById('resultado-qr-rol').classList.remove('hidden');
    document.getElementById('titulo-qr-rol').textContent = `${ETIQUETAS_ROL[data.rol] || data.rol} — ${data.nombre}`;
    document.getElementById('url-qr-rol').textContent = data.url;
    document.getElementById('qr-rol').innerHTML = '';
    // eslint-disable-next-line no-undef
    new QRCode(document.getElementById('qr-rol'), { text: data.url, width: 200, height: 200 });
  } catch (err) {
    mostrarAlerta('alerta', err.message);
  }
});

document.getElementById('form-enviar-link').addEventListener('submit', async (e) => {
  e.preventDefault();
  try {
    const data = await apiFetch('/dev/enviar_link.php', {
      method: 'POST',
      body: {
        nombre: document.getElementById('link-nombre').value,
        correo: document.getElementById('link-correo').value,
      },
    });
    mostrarAlerta('alerta', data.mensaje, 'exito');
    document.getElementById('form-enviar-link').reset();
  } catch (err) {
    mostrarAlerta('alerta', err.message);
  }
});

/**
 * v6.5 - Guía de uso compartida por los 3 dashboards internos que
 * participan del flujo de contratación (Jefe_Terreno, Admin_Contrato,
 * Jefe_Administrativo/JAO). Un solo archivo con el contenido de las 3
 * guías para no duplicar el resumen general del proceso en cada
 * dashboard -- cada uno solo pide su sección con abrirGuia('rol').
 *
 * Uso: <button onclick="abrirGuia('admin_contrato')">Guía de uso</button>
 * y agregar <script src="../assets/js/guia-proceso.js"></script>.
 */

const GUIA_RESUMEN_GENERAL = `
  <ol class="space-y-3">
    <li class="flex gap-3"><span class="shrink-0 w-6 h-6 rounded-full bg-gray-200 text-gray-700 text-xs font-bold flex items-center justify-center">0</span>
      <div><p class="font-semibold text-gray-900">Jefe de Terreno solicita cupos, Administrador abre la vacante</p>
      <p class="text-gray-600">Todo cargo parte sin cupos. Jefe de Terreno pide, por ejemplo, "5 jornales", y esa solicitud queda <b>Pendiente</b> hasta que el Administrador de Contrato la aprueba. Recién ahí se abre la vacante: el cargo muestra cupos disponibles y la gente puede postular a él.</p></div></li>
    <li class="flex gap-3"><span class="shrink-0 w-6 h-6 rounded-full bg-gray-200 text-gray-700 text-xs font-bold flex items-center justify-center">1</span>
      <div><p class="font-semibold text-gray-900">El postulante postula solo</p>
      <p class="text-gray-600">Llena sus datos básicos, sube su CV y su cédula de identidad (ambos lados) desde el formulario público (QR en portería). Queda en estado <b>Pendiente</b>.</p></div></li>
    <li class="flex gap-3"><span class="shrink-0 w-6 h-6 rounded-full bg-gray-200 text-gray-700 text-xs font-bold flex items-center justify-center">2</span>
      <div><p class="font-semibold text-gray-900">Jefe de Terreno hace el primer filtro</p>
      <p class="text-gray-600">Revisa el CV/experiencia y decide si sigue adelante. Esto <b>no cambia el estado</b> de la postulación (sigue Pendiente) -- solo la deja lista para que el Capataz la vea en su propio panel. Si rechaza, el postulante recibe un correo genérico con un motivo estandarizado.</p></div></li>
    <li class="flex gap-3"><span class="shrink-0 w-6 h-6 rounded-full bg-gray-200 text-gray-700 text-xs font-bold flex items-center justify-center">3</span>
      <div><p class="font-semibold text-gray-900">El Capataz selecciona en persona, en portería</p>
      <p class="text-gray-600">Segundo y último filtro en terreno: compara el RUT declarado contra la cédula física y que traiga lo básico. Si selecciona, <b>recién ahí</b> la postulación pasa a <b>Pre-aprobado por Terreno</b> y avanza al Administrador de Contrato.</p></div></li>
    <li class="flex gap-3"><span class="shrink-0 w-6 h-6 rounded-full bg-blue-100 text-blue-700 text-xs font-bold flex items-center justify-center">4</span>
      <div><p class="font-semibold text-gray-900">Administrador de Contrato autoriza <u>primero</u></p>
      <p class="text-gray-600">Este es el paso clave del flujo actual: <b>hasta que el Administrador no autoriza, el postulante no recibe ningún enlace nuevo.</b> Al autorizar, recién ahí se le envía por correo el acceso a la Etapa 2 ("tu postulación ha sido autorizada, completa tus datos").</p></div></li>
    <li class="flex gap-3"><span class="shrink-0 w-6 h-6 rounded-full bg-gray-200 text-gray-700 text-xs font-bold flex items-center justify-center">5</span>
      <div><p class="font-semibold text-gray-900">El postulante completa la Etapa 2</p>
      <p class="text-gray-600">Con el enlace recibido, carga sus datos de contratación y el resto de sus documentos (contrato, Fonasa/Isapre, AFP, etc.). Al terminar, recibe un correo con un QR para <b>presentarse en la obra</b>.</p></div></li>
    <li class="flex gap-3"><span class="shrink-0 w-6 h-6 rounded-full bg-gray-200 text-gray-700 text-xs font-bold flex items-center justify-center">6</span>
      <div><p class="font-semibold text-gray-900">Día 1: se presenta en obra -- JAO verifica y Prevención hace la inducción</p>
      <p class="text-gray-600">Portería confirma su ingreso con el QR. Recién ahí el JAO puede verificar que el RUT coincida con la cédula, y Prevención puede marcar la inducción de seguridad realizada.</p></div></li>
    <li class="flex gap-3"><span class="shrink-0 w-6 h-6 rounded-full bg-gray-200 text-gray-700 text-xs font-bold flex items-center justify-center">7</span>
      <div><p class="font-semibold text-gray-900">Día 2, 8am: JAO firma el contrato y Bodega entrega el EPP</p>
      <p class="text-gray-600">El trabajador vuelve al otro día a firmar. Bodega ya sabe que viene y tiene su kit de EPP listo -- al entregarlo, el postulante queda <b>✔ Contratado</b> y se descuenta el cupo.</p></div></li>
    <li class="flex gap-3"><span class="shrink-0 w-6 h-6 rounded-full bg-green-100 text-green-700 text-xs font-bold flex items-center justify-center">8</span>
      <div><p class="font-semibold text-gray-900">Cierre: Capataz/Jefe de Terreno lo van a buscar</p>
      <p class="text-gray-600">Con el EPP entregado, se avisa a Capataz y Jefe de Terreno para que lo busquen en sala de reuniones o Bodega. Al confirmar que lo recibieron, el proceso queda <b>✔ completo</b> de punta a punta.</p></div></li>
  </ol>
  <p class="text-xs text-gray-400 mt-4">En cualquier etapa antes de esto, Capataz, Jefe de Terreno o Administrador de Contrato pueden rechazar la postulación con un motivo estandarizado; el postulante siempre recibe el mismo mensaje genérico, nunca el motivo real (ese queda solo en el registro interno).</p>
`;

const GUIA_POR_ROL = {
  terreno: {
    titulo: 'Guía de uso · Jefe de Terreno',
    contenido: `
      <p class="text-gray-700 mb-4">Desde el rediseño de la reunión con Ricardo (31-ago), la selección en terreno quedó en <b>dos pasos secuenciales</b>: tú haces el primer filtro, y recién lo que apruebas le llega al Capataz para la selección final en portería.</p>
      <ul class="space-y-2.5 text-gray-700">
        <li>• <b>Pestaña "Solicitar Cupos":</b> pide, por ejemplo, "5 jornales". La solicitud queda <b>Pendiente</b> hasta que el Administrador de Contrato la aprueba -- recién ahí se abre la vacante y el cargo muestra cupos disponibles. En la tabla de abajo ves el estado de tus solicitudes (Pendiente/Aprobada/Rechazada).</li>
        <li>• <b>¿El cargo que necesitas no está en la lista?</b> Elige "➕ Otro (agregar cargo nuevo)" y escribe su nombre -- el cargo se crea en el catálogo recién cuando el Administrador de Contrato apruebe esa solicitud, no antes.</li>
        <li>• <b>Pestaña "Pendientes":</b> primer filtro (paso 1 de 2). Revisa el CV/experiencia y decide <b>Aprobar</b> o <b>Rechazar</b>. Al aprobar, la postulación <u>no</u> avanza de estado todavía -- solo pasa a aparecer en el panel del Capataz, que hace la selección final en persona.</li>
        <li>• <b>Límite diario:</b> el Capataz puede seleccionar hasta 25 postulaciones por día en su paso. El contador se reinicia a medianoche.</li>
        <li>• <b>Banco de Postulantes:</b> si un cargo no tiene cupos disponibles en este momento, el postulante queda "En banco" en vez de perderse. Desde esa pestaña puedes invitarlo más adelante a cualquier cargo que sí tenga cupo -- eso equivale a pre-aprobarlo.</li>
        <li>• <b>Rechazar:</b> eliges un motivo estandarizado (no hay cupos, no cumple requisitos, etc.) -- queda solo en el registro interno, el postulante recibe siempre el mismo mensaje genérico y legal.</li>
        <li>• <b>Pestaña "Recepción":</b> aparecen quienes Bodega ya marcó Contratado (EPP ya entregado). Ve a sala de reuniones o Bodega a buscarlos y confirma aquí -- con eso el proceso queda 100% cerrado. La ve también el Capataz: cualquiera de los dos puede confirmar.</li>
        <li>• <b>Estado en vivo:</b> el widget de arriba te muestra en qué fase está cada postulante activo, aunque ya no dependa de ti. Se pone ámbar y dice "pendiente en tu bandeja" solo cuando de verdad te toca actuar a ti.</li>
      </ul>`,
  },
  capataz: {
    titulo: 'Guía de uso · Capataz',
    contenido: `
      <p class="text-gray-700 mb-4">Tu trabajo es la <b>selección final en portería</b>, en persona -- el segundo y último filtro en terreno. Solo ves a quienes el Jefe de Terreno ya aprobó en su propio panel (paso 1 de 2).</p>
      <ul class="space-y-2.5 text-gray-700">
        <li>• Ves a cada postulante en espera con su <b>RUT bien grande</b>, para compararlo al toque con su cédula física.</li>
        <li>• Revisa que traiga lo básico (por ejemplo, su certificado de AFP) -- no se piden antecedentes, eso ya está resuelto por diseño legal.</li>
        <li>• <b>"✓ Selecciona":</b> recién aquí la postulación avanza de estado y pasa a revisión del Administrador de Contrato.</li>
        <li>• <b>"✕ No selecciona":</b> eliges un motivo estandarizado (no hay cupos, documentación incompleta, etc.). El postulante recibe un correo genérico, nunca el motivo real completo.</li>
        <li>• Solo ves postulantes de cargos con <b>vacante abierta</b> (una solicitud de cupos ya aprobada por el Administrador de Contrato) <b>y</b> ya aprobados por Jefe de Terreno -- si no ves a alguien que debería estar ahí, revisa esos dos requisitos primero.</li>
        <li>• La pantalla se actualiza sola cada 15 segundos, para que la puedas dejar abierta mientras atiendes a la fila.</li>
        <li>• <b>Pestaña "Recepción":</b> cuando Bodega ya entregó el EPP de alguien, aparece aquí -- ve a buscarlo y confirma. Con eso el proceso de esa persona queda 100% cerrado. La ve también Jefe de Terreno: cualquiera de los dos puede confirmar.</li>
      </ul>`,
  },
  admin_contrato: {
    titulo: 'Guía de uso · Administrador de Contrato',
    contenido: `
      <p class="text-gray-700 mb-4">Tu autorización es el <b>paso que activa la Etapa 2</b>: el postulante no recibe el enlace para completar sus datos hasta que tú autorizas.</p>
      <ul class="space-y-2.5 text-gray-700">
        <li>• <b>Pestaña "Por Autorizar":</b> lista lo que Jefe de Terreno ya pre-aprobó. Revisa el CV y decide <b>Autorizar Contratación</b> o <b>Rechazar</b>.</li>
        <li>• Al autorizar, se envía automáticamente el correo al postulante con el enlace para completar la Etapa 2 (datos de contratación + documentos). Antes de tu autorización, ese enlace simplemente no existe.</li>
        <li>• <b>Pestaña "Personal Autorizado":</b> histórico de todos los que ya autorizaste, con KPIs de tiempo promedio por tramo (tu autorización, el postulante llenando Etapa 2, y el JAO hasta finalizar) y exportación a Excel.</li>
        <li>• <b>Pestaña "Estado del proceso":</b> cada trabajador activo, individualizado, con la etapa exacta en la que está ahora mismo -- útil para responder "¿cómo va tal persona?" sin tener que preguntarle a otro rol.</li>
        <li>• <b>Rechazar:</b> igual que en Terreno, el motivo queda solo interno; el postulante recibe el mensaje legal genérico.</li>
      </ul>`,
  },
  jao: {
    titulo: 'Guía de uso · Jefe Administrativo (JAO)',
    contenido: `
      <p class="text-gray-700 mb-4">Desde el rediseño de la reunión con Ricardo (31-ago), tu parte quedó en <b>dos acciones separadas, en días distintos</b>: verificar identidad el día 1, y firmar el contrato el día 2. Ves a todos los que están en cualquiera de las dos etapas desde el comienzo -- lo que cambia es cuándo se te habilita cada botón.</p>
      <ul class="space-y-2.5 text-gray-700">
        <li>• <b>Día 1 -- Verificar identidad:</b> el postulante ya completó la Etapa 2, pero el botón solo se habilita <u>después</u> de que Portería confirme que se presentó en la obra (con el QR que le llegó por correo). Compara el RUT declarado contra su cédula subida y confirma.</li>
        <li>• <b>Observar un documento:</b> si algo está mal o ilegible, puedes rechazar ese documento puntual -- el postulante recibe un correo pidiéndole que lo vuelva a subir, sin afectar el resto de sus documentos ya aprobados.</li>
        <li>• <b>Día 2, 8am -- Firmar Contrato:</b> se habilita recién cuando ya verificaste la identidad Y no queda ningún documento observado. Al firmar, <u>todavía no</u> se descuenta el cupo ni queda Contratado -- eso pasa cuando Bodega entrega el kit de EPP (que espera tu firma para poder entregarlo).</li>
        <li>• <b>Pestaña "Contratados":</b> histórico de todos los que Bodega ya cerró (EPP entregado).</li>
        <li>• <b>Pestaña "Rechazados":</b> los que fueron rechazados en cualquier etapa anterior (Terreno o Administrador), solo para trazabilidad -- tú no rechazas desde aquí, eso ya pasó antes de llegar a ti.</li>
      </ul>`,
  },
};

function abrirGuia(rol) {
  const datos = GUIA_POR_ROL[rol];
  if (!datos) return;

  let modal = document.getElementById('guia-proceso-modal');
  if (!modal) {
    modal = document.createElement('div');
    modal.id = 'guia-proceso-modal';
    modal.className = 'hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4';
    modal.onclick = (e) => { if (e.target === modal) cerrarGuia(); };
    document.body.appendChild(modal);
  }

  modal.innerHTML = `
    <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full p-6 relative max-h-[85vh] overflow-y-auto">
      <button onclick="cerrarGuia()" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600 text-xl leading-none">✕</button>
      <h2 class="text-lg font-bold text-gray-900 mb-1">${datos.titulo}</h2>
      <p class="text-xs text-gray-400 mb-5">Cómo funciona el proceso completo y qué te toca hacer a ti en cada etapa.</p>

      <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-3">Proceso completo, de punta a punta</p>
      <div class="bg-gray-50 rounded-xl p-4 mb-6 text-sm">${GUIA_RESUMEN_GENERAL}</div>

      <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-3">Tu parte en el proceso</p>
      <div class="text-sm">${datos.contenido}</div>
    </div>`;

  modal.classList.remove('hidden');
}

function cerrarGuia() {
  const modal = document.getElementById('guia-proceso-modal');
  if (modal) modal.classList.add('hidden');
}

// --- v6.7: "Flujo del proceso" -- diagrama visual del pipeline completo ----
// tal como está hoy (flujo SECUENCIAL: Terreno pre-aprueba, Administrador
// autoriza, RECIÉN AHÍ el postulante llena Etapa 2, JAO cierra). Mismo
// contenido para los 3 dashboards -- no depende del rol que lo abre.
function pasoFlujo(numero, titulo, detalle, opciones = {}) {
  const { rama = '', ultimo = false } = opciones;
  return `
    <div class="flex gap-3">
      <div class="flex flex-col items-center">
        <span class="shrink-0 w-8 h-8 rounded-full ${ultimo ? 'bg-green-600' : 'bg-blue-600'} text-white text-sm font-bold flex items-center justify-center">${ultimo ? '✔' : numero}</span>
        ${!ultimo ? '<span class="w-px flex-1 bg-gray-300 my-1" style="min-height:14px;"></span>' : ''}
      </div>
      <div class="pb-6 flex-1">
        <p class="font-semibold text-gray-900 text-sm">${titulo}</p>
        <p class="text-xs text-gray-500 mt-0.5">${detalle}</p>
        ${rama}
      </div>
    </div>`;
}

function ramaFlujo(texto, tipo = 'rechazo') {
  const estilos = {
    rechazo: 'bg-red-50 border-red-200 text-red-700',
    observacion: 'bg-amber-50 border-amber-200 text-amber-800',
  };
  const icono = tipo === 'rechazo' ? '✕' : '↺';
  return `<div class="mt-2 border rounded-lg px-3 py-2 text-xs ${estilos[tipo]}"><b>${icono}</b> ${texto}</div>`;
}

const FLUJO_DIAGRAMA_HTML = `
  <p class="text-sm text-gray-600 mb-5">Así funciona hoy el proceso completo, de punta a punta. Las cajas rojas y ámbar son las ramas donde el proceso se desvía de la ruta principal.</p>
  <div>
    ${pasoFlujo(1, 'Jefe de Terreno solicita cupos', 'Ej: "necesito 5 jornales". La solicitud queda Pendiente.', {
      rama: ramaFlujo('Administrador rechaza la solicitud → no se abre ningún cupo, el cargo sigue igual.'),
    })}
    ${pasoFlujo(2, 'Administrador de Contrato aprueba → se abre la vacante', 'Recién aquí el cargo suma cupos disponibles. Sin esto, nadie puede postular a ese cargo.')}
    ${pasoFlujo(3, 'Postulante postula (Etapa 1)', 'Llena datos básicos, sube su CV y su cédula (frente y reverso), desde el formulario público (QR en portería).')}
    ${pasoFlujo(4, 'Jefe de Terreno hace el primer filtro', 'Revisa el CV/experiencia y decide. No cambia el estado de la postulación todavía -- solo la deja lista para que el Capataz la vea en su panel.', {
      rama: ramaFlujo('Rechaza → el postulante recibe un correo genérico con un motivo estandarizado, sin el motivo real completo. No sigue el proceso.'),
    })}
    ${pasoFlujo(5, 'Capataz selecciona en persona, en portería', 'Segundo y último filtro en terreno: compara el RUT declarado contra la cédula física y revisa documentos básicos. Recién aquí la postulación avanza de estado.', {
      rama: ramaFlujo('No selecciona → mismo correo genérico que en el paso anterior. No sigue el proceso.'),
    })}
    ${pasoFlujo(6, 'Administrador de Contrato revisa', 'Ve lo que seleccionó el Capataz y decide.', {
      rama: ramaFlujo('Rechaza → mismo correo genérico que en los pasos anteriores. No sigue el proceso.'),
    })}
    ${pasoFlujo(7, 'Administrador autoriza → se activa la Etapa 2', 'Este es el paso clave: recién aquí el postulante recibe el correo con el enlace para completar sus datos. Antes de esto, ese enlace no existe.')}
    ${pasoFlujo(8, 'Postulante completa Etapa 2', 'Datos personales, previsionales, bancarios + documentos: cédula, certificado de AFP, de salud, de residencia y (si aplica) último finiquito. Al terminar, recibe un correo con un código QR para presentarse en la obra.')}
    ${pasoFlujo(9, 'Se presenta en obra -- Portería confirma el ingreso', 'Muestra el QR en Portería (o dicta RUT + código). Sin este ingreso confirmado, el JAO todavía no puede verificar su identidad.')}
    ${pasoFlujo(10, 'Día 1: JAO verifica identidad y Prevención hace la inducción', 'El JAO compara el RUT declarado contra la cédula subida. En paralelo, Prevención marca la inducción de seguridad realizada.', {
      rama: ramaFlujo('El JAO observa un documento → el postulante recibe un correo pidiéndole que lo vuelva a subir, y vuelve a este mismo paso apenas lo corrige. El resto de lo ya aprobado no se pierde.', 'observacion'),
    })}
    ${pasoFlujo(11, 'Día 2, 8am: JAO firma el Contrato', 'Se habilita recién con la identidad verificada y sin documentos observados. Bodega ya sabe que el trabajador viene y tiene su kit de EPP preparado.')}
    ${pasoFlujo(12, 'Bodega entrega el EPP → Contratado', 'Recién aquí se descuenta el cupo del cargo. El postulante recibe el correo de éxito con el QR final, y se avisa a Capataz/Jefe de Terreno para que lo vayan a buscar.')}
    ${pasoFlujo(13, 'Capataz o Jefe de Terreno confirman la recepción', 'Van a sala de reuniones o Bodega a buscarlo y confirman en su panel. Con eso el ciclo completo queda cerrado, desde la postulación hasta el primer día en el frente de trabajo.', { ultimo: true })}
  </div>`;

function abrirFlujo() {
  let modal = document.getElementById('flujo-proceso-modal');
  if (!modal) {
    modal = document.createElement('div');
    modal.id = 'flujo-proceso-modal';
    modal.className = 'hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4';
    modal.onclick = (e) => { if (e.target === modal) cerrarFlujo(); };
    document.body.appendChild(modal);
  }

  modal.innerHTML = `
    <div class="bg-white rounded-2xl shadow-2xl max-w-xl w-full p-6 relative max-h-[85vh] overflow-y-auto">
      <button onclick="cerrarFlujo()" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600 text-xl leading-none">✕</button>
      <h2 class="text-lg font-bold text-gray-900 mb-1">Flujo del proceso</h2>
      ${FLUJO_DIAGRAMA_HTML}
    </div>`;

  modal.classList.remove('hidden');
}

function cerrarFlujo() {
  const modal = document.getElementById('flujo-proceso-modal');
  if (modal) modal.classList.add('hidden');
}

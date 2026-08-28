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
    <li class="flex gap-3"><span class="shrink-0 w-6 h-6 rounded-full bg-gray-200 text-gray-700 text-xs font-bold flex items-center justify-center">1</span>
      <div><p class="font-semibold text-gray-900">El postulante postula solo</p>
      <p class="text-gray-600">Llena sus datos básicos, sube su CV y su cédula de identidad (ambos lados) desde el formulario público. Queda en estado <b>Pendiente</b>.</p></div></li>
    <li class="flex gap-3"><span class="shrink-0 w-6 h-6 rounded-full bg-gray-200 text-gray-700 text-xs font-bold flex items-center justify-center">2</span>
      <div><p class="font-semibold text-gray-900">Jefe de Terreno pre-aprueba o rechaza</p>
      <p class="text-gray-600">Revisa el CV y decide. Si pre-aprueba, pasa a <b>Pre-aprobado por Terreno</b>. Si rechaza, el postulante recibe un correo genérico (no se le indica el motivo real, para resguardar a la empresa de la Ley 20.609).</p></div></li>
    <li class="flex gap-3"><span class="shrink-0 w-6 h-6 rounded-full bg-blue-100 text-blue-700 text-xs font-bold flex items-center justify-center">3</span>
      <div><p class="font-semibold text-gray-900">Administrador de Contrato autoriza <u>primero</u></p>
      <p class="text-gray-600">Este es el paso clave del flujo actual: <b>hasta que el Administrador no autoriza, el postulante no recibe ningún enlace nuevo.</b> Al autorizar, recién ahí se le envía por correo el acceso a la Etapa 2 ("tu postulación ha sido autorizada, completa tus datos").</p></div></li>
    <li class="flex gap-3"><span class="shrink-0 w-6 h-6 rounded-full bg-gray-200 text-gray-700 text-xs font-bold flex items-center justify-center">4</span>
      <div><p class="font-semibold text-gray-900">El postulante completa la Etapa 2</p>
      <p class="text-gray-600">Con el enlace recibido, carga sus datos de contratación y el resto de sus documentos (contrato, Fonasa/Isapre, AFP, etc.). Al terminar, pasa a <b>En revisión Jefe Administrativo</b>.</p></div></li>
    <li class="flex gap-3"><span class="shrink-0 w-6 h-6 rounded-full bg-gray-200 text-gray-700 text-xs font-bold flex items-center justify-center">5</span>
      <div><p class="font-semibold text-gray-900">JAO revisa, digita y finaliza</p>
      <p class="text-gray-600">El Jefe Administrativo revisa cada documento (puede observar/rechazar uno individual, lo que le pide al postulante corregirlo), digita el código de ficha y los datos finales, y finaliza. Ahí el postulante queda <b>✔ Contratado</b>.</p></div></li>
    <li class="flex gap-3"><span class="shrink-0 w-6 h-6 rounded-full bg-green-100 text-green-700 text-xs font-bold flex items-center justify-center">6</span>
      <div><p class="font-semibold text-gray-900">Cierre: correo con QR, Prevención y Bodega</p>
      <p class="text-gray-600">El postulante recibe un correo de "contratación exitosa" con un código QR para presentar en Portería. Si los módulos están activos, Prevención y Bodega son notificados (Bodega recibe también las tallas) para preparar inducción y EPP.</p></div></li>
  </ol>
  <p class="text-xs text-gray-400 mt-4">En cualquier etapa antes de esto, Jefe de Terreno o Administrador de Contrato pueden rechazar la postulación; el postulante siempre recibe el mismo mensaje genérico, nunca el motivo real (ese queda solo en el registro interno).</p>
`;

const GUIA_POR_ROL = {
  terreno: {
    titulo: 'Guía de uso · Jefe de Terreno',
    contenido: `
      <p class="text-gray-700 mb-4">Tu rol es el <b>primer filtro</b>: decides quién sigue en el proceso apenas llega la postulación.</p>
      <ul class="space-y-2.5 text-gray-700">
        <li>• <b>Pestaña "Pendientes":</b> revisa el CV de cada postulación nueva y decide <b>Pre-aprobar</b> o <b>Rechazar</b>. Al pre-aprobar, la postulación pasa al Administrador de Contrato (tú ya no la ves más en esta pestaña).</li>
        <li>• <b>Límite diario:</b> puedes pre-aprobar hasta 25 postulaciones por día. El contador se reinicia a medianoche.</li>
        <li>• <b>Banco de Postulantes:</b> si un cargo no tiene cupos disponibles en este momento, el postulante queda "En banco" en vez de perderse. Desde esa pestaña puedes invitarlo más adelante a cualquier cargo que sí tenga cupo -- eso equivale a pre-aprobarlo.</li>
        <li>• <b>Cupos por cargo:</b> puedes ver y solicitar más cupos activos por cargo cuando se necesiten (sujeto a aprobación).</li>
        <li>• <b>Rechazar:</b> el motivo que escribes queda solo en el registro interno -- el postulante recibe siempre el mismo mensaje genérico y legal, nunca el motivo real.</li>
        <li>• <b>Estado en vivo:</b> el widget de arriba te muestra en qué fase está cada postulante activo, aunque ya no dependa de ti. Se pone ámbar y dice "pendiente en tu bandeja" solo cuando de verdad te toca actuar a ti.</li>
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
      <p class="text-gray-700 mb-4">Eres el <b>último filtro</b> antes de la contratación: revisas que todo esté correcto y das el cierre formal.</p>
      <ul class="space-y-2.5 text-gray-700">
        <li>• <b>Pestaña "Pendientes de Aprobación":</b> postulantes que ya completaron la Etapa 2 (Administrador autorizó y el postulante ya cargó sus documentos). Revisa cada documento uno por uno.</li>
        <li>• <b>Observar un documento:</b> si algo está mal o ilegible, puedes rechazar ese documento puntual -- el postulante recibe un correo pidiéndole que lo vuelva a subir, sin afectar el resto de sus documentos ya aprobados.</li>
        <li>• <b>Finalizar contratación:</b> digitas el código de ficha y los datos finales. Al finalizar: (1) el postulante queda <b>Contratado</b>, (2) recibe un correo de éxito con un código QR para presentar en Portería, (3) si los módulos están activos, Prevención y Bodega son notificados (Bodega recibe también las tallas), (4) se genera automáticamente la carpeta local del trabajador con sus documentos de Etapa 2.</li>
        <li>• <b>Pestaña "Contratados":</b> histórico de todos los que ya finalizaste.</li>
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

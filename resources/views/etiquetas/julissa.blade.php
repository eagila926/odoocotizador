@php
    $prefill = session('etiqueta_preview', []);
    $soPrefill = $prefill['so'] ?? null;
    $medicoPrefill = $prefill['medico'] ?? null;
    $pacientePrefill = $prefill['paciente'] ?? null;

    // DR(a): mostrar solo "primer nombre" + "primer/último apellido"
    function nombreCorto($full) {
        $full = trim((string)$full);
        if ($full === '') return null;
        $p = preg_split('/\s+/', $full);
        $first = $p[0] ?? '';
        $last  = $p[count($p)-1] ?? '';
        return trim($first.' '.$last);
    }
    $medicoCorto = nombreCorto($medicoPrefill);

    // ===== Helpers de vista =====
    function abreviarNombreActivoView($nombre) {
        $nombre = preg_replace('/\s*\(.*?\)\s*/', '', (string)$nombre);
        if (stripos($nombre, 'BIFIDUMBACTERIUM') === 0) {
            return 'BIFIDUM' . substr($nombre, strlen('BIFIDUMBACTERIUM'));
        } elseif (stripos($nombre, 'LACTOBACILUS') === 0) {
            return 'LACTO' . substr($nombre, strlen('LACTOBACILUS'));
        }
        return $nombre;
    }

    // ===== Códigos =====
    // Pastilleros existentes (NO deben mostrarse en composición)
    $codPastilleros = [70274, 70272, 70276, 70275, 70273, 70271, 71497, 1219];

    // Excluir auxiliares (cápsulas, estearato, pastilleros, sobres, etc.)
    $excluir = array_merge($codPastilleros, [1101, 1078, 1077]);

    // IMPORTANTÍSIMO:
    // El número de pastilleros se recupera DESDE LA TABLA (relación items()) sumando "cantidad"
    // Ej: cod_odoo=1219 cantidad=2 => 2 pastilleros
    $numPastilleros = 0;
    if (isset($formula) && method_exists($formula, 'items')) {
        $numPastilleros = (float) $formula->items()
            ->whereIn('cod_odoo', $codPastilleros)
            ->sum('cantidad');
    }

    // Items mostrados en composición (sin auxiliares)
    $items  = $items ?? ($formula->items ?? collect())->filter(fn($it) => !in_array((int)$it->cod_odoo, $excluir))->values();
    $totalActivos = $items->count();

    // Espaciado extra
    if ($totalActivos >= 1 && $totalActivos <= 4) {
        $espaciadoExtra = 'margin-top: 40px; margin-bottom: 20px;';
    } elseif ($totalActivos >= 5 && $totalActivos <= 10) {
        $espaciadoExtra = 'margin-top: 10px; margin-bottom: 10px;';
    } else {
        $espaciadoExtra = '';
    }

    // Columnas
    $columnas = 2;
    if ($totalActivos >= 16 && $totalActivos <= 30) $columnas = 3;

    // Partir ítems en columnas equilibradas
    $porCol   = max(1, (int) ceil($totalActivos / $columnas));
    $chunks   = $items->chunk($porCol);

    // Pie (valores por defecto que luego el usuario puede editar)
    $tomas   = (int) ($formula->tomas_diarias ?? 3);
    $dias    = 30;

    // TOTAL cápsulas base (antes de dividir)
    $contieneCapsTotal = $tomas * $dias;

    // Cálculo final a imprimir en "CONTIENE"
    // - Si hay 0 o 1 pastillero => NO dividir
    // - Si hay 2+ pastilleros => dividir "cápsulas por pastillero"
    if ($numPastilleros > 1) {
        $contieneCapsEtiqueta = (int) ceil($contieneCapsTotal / $numPastilleros);
    } else {
        $contieneCapsEtiqueta = (int) $contieneCapsTotal;
    }

    // Título dinámico
    $nombreEtiqueta = (string) ($formula->nombre_etiqueta ?? '');
    $fontSizeTitulo = (strlen($nombreEtiqueta) > 31) ? '25px' : '28px';

    $qf = $qf ?? 'Q.F. EVELYN GARCÍA';

    // Fechas (robusto: evita 500 si llega en otro formato)
    $fechaElaboracion = $fechaElaboracion ?? now()->format('d-m-Y');
    try {
        $dtElab = \Carbon\Carbon::createFromFormat('d-m-Y', $fechaElaboracion);
    } catch (\Exception $e) {
        $dtElab = now();
        $fechaElaboracion = $dtElab->format('d-m-Y');
    }
    $fechaExp = $dtElab->copy()->addMonthsNoOverflow(2)->format('d-m-Y');
@endphp
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Etiqueta – {{ $formula->codigo }}</title>
<style>
    body {
        font-family: Helvetica, Arial, sans-serif;
        margin: 22px;
    }

    /* ===== Layout tipo 12 columnas ===== */
    .container {
        width: 90%;
        margin: 0 auto;
        font-family: Helvetica, Arial, sans-serif;
    }

    .row {
        display: flex;
        flex-wrap: wrap;
        width: 100%;
        margin-bottom: 4px;
        box-sizing: border-box;
    }

    .col-12 {
        width: 100%;
        box-sizing: border-box;
        margin-top: -15px;
        
    }

    .col-6 {
        width: 50%;
        box-sizing: border-box;
    }

    .header {
        text-align: center;
        font-weight: bold;
    
        margin-bottom: 5px;
    }

    .footer {
        margin-top: 5px;
        font-size: 20px;
    }

    .editable {
        border-bottom: 1px dashed #bbb;
        padding: 2px 4px;
        display: inline-block;
    }

    .editable:focus {
        outline: 2px solid #ddeaff;
        border-bottom-color: transparent;
    }

    .nombre-etiqueta {
        font-size: {{ $fontSizeTitulo }};
        font-weight: 700;
        background: transparent;
        width: 100%;
        text-align: center;
    }

    .pte {
        font-weight: bold;
        font-size: 18px;
        background: transparent;
        width: 100%;
        text-align: left;
    }

    .codigo-formula {
        text-align: center;
    }

    .so {
        font-weight: bold;
        font-size: 22px;
        background: transparent;
        text-align: right;
    }

    /* ===== Composición limitada a media etiqueta ===== */
    .composicion-wrap {
        width: 50%;
        box-sizing: border-box;
        padding-right: 12px;
    }

    .composicion-grid {
        display: flex;
        gap: 16px;
        width: 100%;
    }

    .composicion-col {
        flex: 1;
        min-width: 0;
    }

    .comp-row {
        display: flex;
        justify-content: space-between;
        align-items: baseline;
        gap: 8px;
        margin: 2px 0;
    }

    .comp-nombre {
        font-size: 17px;
        line-height: 1.1;
        white-space: normal;
    }

    .comp-cant {
        text-align: right;
        font-size: 17px;
        line-height: 1.1;
        white-space: nowrap;
    }

    .detalle-etiqueta {
        width: 50%;
        margin-top: 5px;
        font-size: 16px;
        font-weight: bold;
        box-sizing: border-box;
    }

    /* ===== QF / ELAB / SO ocupa todo el ancho ===== */
    .footer-etiqueta {
        width: 100%;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 20px;
        margin-top: 180px;
        font-family: Arial, sans-serif;
        font-size: 20px;
        font-weight: 450;
        color: #111;
        box-sizing: border-box;
        
    }

    .footer-etiqueta .footer-col {
        width: 33.3333%;
        box-sizing: border-box;
    }

    .footer-etiqueta .footer-col.center {
        text-align: center;
    }

    .footer-etiqueta .footer-col.right {
        text-align: right;
    }

    @media print {
        .editable { border: none !important; }
        input { caret-color: transparent; }
    }
</style>
</head>
<body>
    <!-- ===== MODAL de Recetas ===== -->
<style>
  #dlg-recetas::backdrop {
    background: rgba(15,23,42,.45);
  }

  #dlg-recetas {
    border: none;
    border-radius: 18px;
    padding: 0;
    max-width: 780px;
    width: 96%;
    box-shadow: 0 18px 45px rgba(15,23,42,0.38);
    font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
  }

  .dlg-recetas__header {
    padding: 16px 24px;
    border-bottom: 1px solid #e5e7eb;
    background: linear-gradient(135deg,#0d6efd,#2563eb);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    border-top-left-radius: 18px;
    border-top-right-radius: 18px;
  }
  .dlg-recetas__title {
    margin: 0;
    font-size: 20px;
    font-weight: 650;
  }
  .dlg-recetas__subtitle {
    margin: 2px 0 0;
    font-size: 13px;
    opacity: .9;
  }
  .dlg-recetas__badge {
    padding: 4px 10px;
    border-radius: 999px;
    background: rgba(15,23,42,.18);
    font-size: 12px;
    font-weight: 500;
  }

  .dlg-recetas__body {
    padding: 18px 24px 16px;
    background: #f9fafb;
  }

  .dlg-recetas__grid {
    display: grid;
    gap: 14px 18px;
  }
  @media (min-width: 640px) {
    .dlg-recetas__grid {
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }
  }

  .form-row {
    margin: 0;
    font-size: 14px;
  }
  .form-row label {
    display: block;
    font-weight: 600;
    margin-bottom: 4px;
    color: #111827;
  }
  .form-row small {
    font-size: 12px;
  }

  .dlg-recetas__input,
  .dlg-recetas__input-date {
    width: 100%;
    padding: 7px 9px;
    border-radius: 8px;
    border: 1px solid #d1d5db;
    font-size: 14px;
    transition: border-color .15s ease, box-shadow .15s ease, background-color .15s ease;
    background: #ffffff;
  }
  .dlg-recetas__input:focus,
  .dlg-recetas__input-date:focus {
    outline: none;
    border-color: #2563eb;
    box-shadow: 0 0 0 1px rgba(37,99,235,.25);
  }

  .dlg-recetas__input[readonly] {
    background:#f3f4f6;
    color:#6b7280;
  }

  .dlg-recetas__footer {
    padding: 10px 24px 16px;
    border-top: 1px solid #e5e7eb;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    background: #f9fafb;
    border-bottom-left-radius: 18px;
    border-bottom-right-radius: 18px;
  }

  .btn-primary {
    padding: 8px 16px;
    background:#0d6efd;
    border:none;
    color:#fff;
    border-radius:999px;
    font-size: 14px;
    font-weight: 600;
    cursor:pointer;
    display:inline-flex;
    align-items:center;
    gap:6px;
    box-shadow:0 6px 14px rgba(37,99,235,.35);
    transition: background .15s ease, transform .08s ease, box-shadow .15s ease, opacity .15s ease;
  }
  .btn-primary:hover:not(:disabled) {
    background:#0b5ed7;
    transform: translateY(-1px);
    box-shadow:0 10px 24px rgba(37,99,235,.40);
  }
  .btn-primary:disabled {
    opacity:.55;
    cursor:not-allowed;
    box-shadow:none;
  }

  .btn-outline {
    padding: 8px 16px;
    border-radius:999px;
    border:1px solid #d1d5db;
    background:#ffffff;
    font-size:14px;
    font-weight:500;
    cursor:pointer;
    transition: background .15s ease, border-color .15s ease, color .15s ease;
  }
  .btn-outline:hover {
    background:#f3f4f6;
    border-color:#9ca3af;
  }

  .sugs-panel {
    position:absolute;
    left:0;
    top:calc(100% + 4px);
    z-index:1000;
    background:#fff;
    border:1px solid #d1d5db;
    width:100%;
    max-height:230px;
    overflow-y:auto;
    box-shadow:0 14px 30px rgba(15,23,42,.25);
    border-radius:10px;
  }
  .sugs-item {
    padding:8px 11px;
    cursor:pointer;
    font-size:13px;
  }
  .sugs-item:hover {
    background:#eff6ff;
  }
  .sugs-item.empty {
    color:#9ca3af;
    cursor:default;
  }

  #medico_sel {
    font-size:12px;
    color:#4b5563;
  }
  
  /* Las reglas de etiqueta están definidas en el primer bloque <style>. */

  /* ===== Modal de recetas ===== */
  #dlg-recetas::backdrop {
    background: rgba(15,23,42,.45);
  }

  #dlg-recetas {
    border: none;
    border-radius: 18px;
    padding: 0;
    max-width: 780px;
    width: 96%;
    box-shadow: 0 18px 45px rgba(15,23,42,0.38);
    font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
  }

  .dlg-recetas__header {
    padding: 16px 24px;
    border-bottom: 1px solid #e5e7eb;
    background: linear-gradient(135deg,#0d6efd,#2563eb);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    border-top-left-radius: 18px;
    border-top-right-radius: 18px;
  }

  .dlg-recetas__title {
    margin: 0;
    font-size: 20px;
    font-weight: 650;
  }

  .dlg-recetas__subtitle {
    margin: 2px 0 0;
    font-size: 13px;
    opacity: .9;
  }

  .dlg-recetas__badge {
    padding: 4px 10px;
    border-radius: 999px;
    background: rgba(15,23,42,.18);
    font-size: 12px;
    font-weight: 500;
  }

  .dlg-recetas__body {
    padding: 18px 24px 16px;
    background: #f9fafb;
  }

  .dlg-recetas__grid {
    display: grid;
    gap: 14px 18px;
  }

  @media (min-width: 640px) {
    .dlg-recetas__grid {
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }
  }

  .form-row {
    margin: 0;
    font-size: 14px;
  }

  .form-row label {
    display: block;
    font-weight: 600;
    margin-bottom: 4px;
    color: #111827;
  }

  .form-row small {
    font-size: 12px;
  }

  .dlg-recetas__input,
  .dlg-recetas__input-date {
    width: 100%;
    padding: 7px 9px;
    border-radius: 8px;
    border: 1px solid #d1d5db;
    font-size: 14px;
    transition: border-color .15s ease, box-shadow .15s ease, background-color .15s ease;
    background: #ffffff;
  }

  .dlg-recetas__input:focus,
  .dlg-recetas__input-date:focus {
    outline: none;
    border-color: #2563eb;
    box-shadow: 0 0 0 1px rgba(37,99,235,.25);
  }

  .dlg-recetas__footer {
    padding: 10px 24px 16px;
    border-top: 1px solid #e5e7eb;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    background: #f9fafb;
    border-bottom-left-radius: 18px;
    border-bottom-right-radius: 18px;
  }

  .btn-primary {
    padding: 8px 16px;
    background:#0d6efd;
    border:none;
    color:#fff;
    border-radius:999px;
    font-size: 14px;
    font-weight: 600;
    cursor:pointer;
    display:inline-flex;
    align-items:center;
    gap:6px;
    box-shadow:0 6px 14px rgba(37,99,235,.35);
    transition: background .15s ease, transform .08s ease, box-shadow .15s ease, opacity .15s ease;
  }

  .btn-primary:hover:not(:disabled) {
    background:#0b5ed7;
    transform: translateY(-1px);
    box-shadow:0 10px 24px rgba(37,99,235,.40);
  }

  .btn-primary:disabled {
    opacity:.55;
    cursor:not-allowed;
    box-shadow:none;
  }

  .btn-outline {
    padding: 8px 16px;
    border-radius:999px;
    border:1px solid #d1d5db;
    background:#ffffff;
    font-size:14px;
    font-weight:500;
    cursor:pointer;
    transition: background .15s ease, border-color .15s ease, color .15s ease;
  }

  .btn-outline:hover {
    background:#f3f4f6;
    border-color:#9ca3af;
  }

  .sugs-panel {
    position:absolute;
    left:0;
    top:calc(100% + 4px);
    z-index:1000;
    background:#fff;
    border:1px solid #d1d5db;
    width:100%;
    max-height:230px;
    overflow-y:auto;
    box-shadow:0 14px 30px rgba(15,23,42,.25);
    border-radius:10px;
  }

  .sugs-item {
    padding:8px 11px;
    cursor:pointer;
    font-size:13px;
  }

  .sugs-item:hover {
    background:#eff6ff;
  }

  .sugs-item.empty {
    color:#9ca3af;
    cursor:default;
  }

  #medico_sel {
    font-size:12px;
    color:#4b5563;
  }

  @media print{
    input{ caret-color: transparent; }
  }
  
</style>
@php
  $canRecetas = auth()->check() && auth()->user()->hasRole(['Admin','Laboratorio']);
@endphp

@if($canRecetas)
  <dialog id="dlg-recetas">
    <form method="POST" action="{{ route('recetas.store') }}" id="frm-recetas" novalidate>
      @csrf

      <div class="dlg-recetas__header">
        <div>
          <h3 class="dlg-recetas__title">Datos para guardar recetas</h3>
          <p class="dlg-recetas__subtitle">
            Completa la información para generar las etiquetas de esta fórmula.
          </p>
        </div>
        <span class="dlg-recetas__badge">
          {{ $formula->codigo ?? 'FÓRMULA' }}
        </span>
      </div>

      <div class="dlg-recetas__body">
        <div class="dlg-recetas__grid">

          <div class="form-row">
            <label for="num_etiquetas">Número de etiquetas a imprimir</label>
            <input type="number" name="num_etiquetas" id="num_etiquetas"
                   class="dlg-recetas__input"
                   value="1" min="1" max="200" required>
            <small>Si eliges más de 1, el paciente se llenará de forma automática.</small>
          </div>

          <div class="form-row">
            <label>Código de fórmula</label>
            <input type="text" name="codigo_formula"
                   class="dlg-recetas__input"
                   value="{{ $formula->codigo }}" readonly>
          </div>

          <div class="form-row">
            <label for="so">SO</label>
            <input type="text" name="so" id="so"
                   class="dlg-recetas__input"
                   inputmode="numeric" pattern="\d+" maxlength="50"
                   placeholder="Solo números" required>
          </div>

          <div class="form-row">
            <label for="fecha">Fecha</label>
            <input type="date" name="fecha" id="fecha"
                   class="dlg-recetas__input-date"
                   value="{{ now()->toDateString() }}" required>
          </div>

          <div class="form-row" style="grid-column:1 / -1;">
            <label>Médico (buscar por nombre/apellido)</label>
            <div style="position:relative; max-width:420px;">
              <input type="text" id="medico_search"
                     class="dlg-recetas__input"
                     placeholder="Ej. 'María López'"
                     autocomplete="off">
              <div id="medico_sugs"></div>
            </div>
            <input type="hidden" name="cedula_medico" id="cedula_medico" required>
            <input type="hidden" name="medico_nombre" id="medico_nombre">
            <small id="medico_sel" style="display:block;margin-top:4px;"></small>
          </div>

          <div id="grp_paciente" class="form-row" style="grid-column:1 / -1; max-width:420px;">
            <label for="paciente">Paciente (opcional si solo 1 etiqueta)</label>
            <input type="text" name="paciente" id="paciente"
                   class="dlg-recetas__input"
                   maxlength="50"
                   placeholder="Dejar vacío para nombre aleatorio">
          </div>

        </div>

        <input type="hidden" name="redirect_to" value="{{ url()->current() }}">
      </div>

      <div class="dlg-recetas__footer">
        <button type="button" id="btn-cancel" class="btn-outline">Cancelar</button>
        <button type="submit" id="btn-save" class="btn-primary" disabled>
          Guardar recetas y continuar
        </button>
      </div>

    </form>
  </dialog>

  <script>
    // ---------- Apertura del modal ----------
    const mustOpen = false;
    const dlg = document.getElementById('dlg-recetas');

    if (mustOpen && dlg && !dlg.open) dlg.showModal();

    document.getElementById('btn-cancel')?.addEventListener('click', () => dlg.close());

    // ---------- Referencias ----------
    const form   = document.getElementById('frm-recetas');
    const btnSave= document.getElementById('btn-save');
    const so     = document.getElementById('so');
    const numEt  = document.getElementById('num_etiquetas');
    const grpPac = document.getElementById('grp_paciente');
    const inpPac = document.getElementById('paciente');
    const inpMed = document.getElementById('medico_search');
    const cedulaHidden = document.getElementById('cedula_medico');
    const selText= document.getElementById('medico_sel');
    const sugBox = document.getElementById('medico_sugs');

    // ---------- SO: solo dígitos ----------
    so?.addEventListener('input', (e) => {
      e.target.value = (e.target.value || '').replace(/[^0-9]/g, '');
      e.target.setCustomValidity('');
      updateSaveEnabled();
    });
    so?.addEventListener('invalid', function() {
      if (this.validity.patternMismatch) {
        this.setCustomValidity('SO solo acepta dígitos (0-9).');
      }
    });

    // ---------- Mostrar/ocultar paciente ----------
    function togglePaciente() {
      const n = parseInt(numEt.value || '1', 10);
      if (n > 1) {
        grpPac.style.opacity = '0.5';
        inpPac.value = '';
        inpPac.disabled = true;
        inpPac.placeholder = 'Se generarán nombres aleatorios';
      } else {
        grpPac.style.opacity = '1';
        inpPac.disabled = false;
        inpPac.placeholder = 'Dejar vacío para nombre aleatorio';
      }
      updateSaveEnabled();
    }
    numEt?.addEventListener('input', togglePaciente);
    togglePaciente();

    // ---------- Autocompletar de médicos ----------
    let sugPanel = null;
    function clearSugs(){ if (sugPanel){ sugPanel.remove(); sugPanel=null; } }

    async function buscarMedicos(q){
      if (!q || q.length < 2) { clearSugs(); return; }
      const url = "{{ route('medicos.buscar') }}" + "?q=" + encodeURIComponent(q);

      let list = [];
      try {
        const resp = await fetch(url, {
          headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
          }
        });

        if (!resp.ok) { clearSugs(); return; }

        const data = await resp.json();
        list = Array.isArray(data) ? data : (Array.isArray(data?.data) ? data.data : []);
      } catch (e) {
        clearSugs();
        return;
      }

      clearSugs();
      sugPanel = document.createElement('div');
      sugPanel.className = 'sugs-panel';

      if (!list.length) {
        const empty = document.createElement('div');
        empty.className = 'sugs-item empty';
        empty.textContent = 'Sin resultados';
        sugPanel.appendChild(empty);
        sugBox.appendChild(sugPanel);
        return;
      }

      list.forEach(item => {
        const opt = document.createElement('div');
        opt.className = 'sugs-item';
        opt.textContent = item.label + ' — C.I. ' + item.cedula;
        opt.addEventListener('click', () => {
          inpMed.value = item.label;
          cedulaHidden.value = item.cedula;
          document.getElementById('medico_nombre').value = item.label;
          selText.textContent = 'Seleccionado: ' + item.label + ' (C.I. ' + item.cedula + ')';
          clearSugs();
          updateSaveEnabled();
        });
        sugPanel.appendChild(opt);
      });

      sugBox.appendChild(sugPanel);
    }

    inpMed?.addEventListener('input', (e)=> {
      cedulaHidden.value = '';
      selText.textContent = '';
      buscarMedicos(e.target.value.trim());
      updateSaveEnabled();
    });

    document.addEventListener('click', (e)=> {
      if (!sugBox.contains(e.target) && e.target !== inpMed) clearSugs();
    });

    // ---------- Habilitar/Deshabilitar botón Guardar ----------
    function updateSaveEnabled(){
      const soOk = !!so.value && /^\d+$/.test(so.value);
      const medOk = !!cedulaHidden.value;
      const n = parseInt(numEt.value || '1', 10);
      btnSave.disabled = !(soOk && medOk && n >= 1);
    }
    updateSaveEnabled();

    // ---------- Validación final ----------
    form.addEventListener('submit', (e) => {
      if (!cedulaHidden.value) {
        e.preventDefault();
        alert('Selecciona un médico de la lista.');
        inpMed.focus();
      }
    });
  </script>
@endif
<!-- ===== FIN MODAL ===== -->

<div class="container">

    {{-- Título: ocupa solo media etiqueta, col-6 --}}
    <div class="row">
        <div class="col-6">
            <div class="header">
                <div class="editable nombre-etiqueta" contenteditable="true">
                    {{ $nombreEtiqueta }}
                </div>
            </div>
        </div>
    </div>

    {{-- Paciente y Código: ocupan media etiqueta, col-6 --}}
    <div class="row">
        <div class="col-6">
            <div class="row">
                <div class="col-6">
                    <div class="editable pte" contenteditable="true">
                        PTE: {{ $pacientePrefill ?? ($formula->paciente ?? '-') }}
                    </div>
                </div>

                <div class="col-6">
                    <div class="editable pte codigo-formula" contenteditable="true">
                        {{ $formula->codigo }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Composición: ocupa solo 6 de 12 columnas --}}
    <div class="row" style="{{ $espaciadoExtra }}">
        <div class="composicion-wrap">
            <div class="composicion-grid">
                @foreach($chunks as $col)
                    <div class="composicion-col">
                        @foreach($col as $it)
                            <div class="comp-row">
                                <div class="comp-nombre">
                                    {{ abreviarNombreActivoView($it->activo) }}
                                </div>

                                <div class="comp-cant">
                                    {{ number_format((float)$it->cantidad, 2) }} {{ $it->unidad ?? 'mg' }}
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Contiene y Posología: también solo media etiqueta --}}
    <div class="row">
        <div class="detalle-etiqueta">
            <div>
                <strong>CONTIENE: </strong>
                <strong>
                    <span class="editable js-only-numbers" contenteditable="true">{{ $contieneCapsEtiqueta }}</span>
                    CÁPSULAS
                </strong>
            </div>

            <div>
                <strong>POSOLOGÍA: TOMAR </strong>
                <strong>
                    <span class="editable js-only-numbers" contenteditable="true">{{ $tomas }}</span>
                    CÁPSULAS DIARIAS
                </strong>
            </div>
            
            <div style="display:flex; justify-content:flex-end; margin-top:-60px;">

                        <div style="display:flex; flex-direction:column; align-items:center;">
                    
                            <div style="display:flex; align-items:center; gap:8px;">
                    
                                <svg width="45" height="60" viewBox="0 0 300 400" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Frasco vacío con tapa abierta">
                                              <g fill="none" stroke="#000" stroke-width="8" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M75 78 C115 45, 220 35, 245 48 C252 52, 252 60, 244 66 C198 88, 120 104, 82 97 C74 95, 69 84, 75 78Z"/>
                                                <path d="M78 97 L84 124 C88 135, 200 113, 244 75 L245 48"/>
                                                <path d="M92 98 L96 126"/>
                                                <path d="M108 97 L113 124"/>
                                                <path d="M124 94 L130 121"/>
                                                <path d="M140 91 L147 118"/>
                                                <path d="M156 87 L164 114"/>
                                                <path d="M173 83 L181 110"/>
                                                <path d="M190 78 L198 105"/>
                                                <path d="M207 73 L215 99"/>
                                                <path d="M224 67 L232 93"/>
                                                <ellipse cx="165" cy="130" rx="92" ry="24"/>
                                                <path d="M73 130 C75 154, 255 154, 257 130"/>
                                                <path d="M75 151 C75 176, 255 176, 255 151"/>
                                                <path d="M82 151 C86 167, 79 177, 70 184"/>
                                                <path d="M248 151 C244 167, 252 177, 260 184"/>
                                                <path d="M70 184 C50 200, 45 220, 45 250 L45 322 C45 365, 72 382, 115 382 L215 382 C255 382, 280 360, 280 322 L280 250 C280 220, 276 200, 260 184"/>
                                                <path d="M82 207 C65 220, 60 238, 60 270"/>
                                                <path d="M95 226 C84 238, 80 252, 80 285"/>
                                                <path d="M60 323 C60 350, 72 368, 98 375"/>
                                                <path d="M78 348 C95 370, 125 374, 170 374"/>
                                                <path d="M82 158 C92 164, 105 165, 118 165"/>
                                              </g>
                                            </svg>
                    
                                <strong style="font-size:20px;">60 días</strong>
                    
                            </div>
                    
                        </div>
                    
                    </div>
        </div>
    </div>

    {{-- QF / ELAB / SO: ocupa los 12 columnas --}}
    <div class="row">
        <div class="col-12">
            <div class="footer-etiqueta">
                <div class="footer-col">
                    <strong class="editable" contenteditable="true">{{ $qf }}</strong>
                </div>

                <div class="footer-col center">
                    <strong>
                        ELAB:
                        <span class="editable" contenteditable="true">{{ $fechaElaboracion }}</span>
                    </strong>
                </div>

                <div class="footer-col right" style="margin-right:150px">
                    <strong class="editable so" contenteditable="true">
                        SO{{ $soPrefill ? $soPrefill : '' }}
                    </strong>
                </div>
                
                
            </div>
        </div>
    </div>

</div>

<script>
  document.querySelectorAll('.editable').forEach(el => {
    // Evitar enter
    el.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') {
        e.preventDefault();
        el.blur();
      }
    });

    // Pegar solo texto plano
    el.addEventListener('paste', (e) => {
      e.preventDefault();

      const text = (e.clipboardData || window.clipboardData).getData('text/plain');

      // insertar texto sin formato
      document.execCommand('insertText', false, text.replace(/\r?\n/g, ' '));
    });
  });

  // Limitar a números los campos marcados como solo-números
  document.querySelectorAll('.js-only-numbers').forEach(el => {
    el.addEventListener('input', () => {
      el.textContent = (el.textContent || '').replace(/[^\d]/g, '');
    });

    el.addEventListener('paste', (e) => {
      e.preventDefault();
      const text = (e.clipboardData || window.clipboardData).getData('text/plain');
      const clean = (text || '').replace(/[^\d]/g, '');
      document.execCommand('insertText', false, clean);
    });
  });
</script>
</body>
</html>

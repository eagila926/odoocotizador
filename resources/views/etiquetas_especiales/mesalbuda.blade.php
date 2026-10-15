{{-- resources/views/etiquetas_especiales/mesalbuda.blade.php --}}
@extends('layouts.etiqueta')

@section('title', 'Etiqueta Mesalbuda')

@php
    $prefill = session('etiqueta_preview', []);

    $soPrefill       = $prefill['so'] ?? '';
    $medicoPrefill   = $prefill['medico'] ?? null;
    $pacientePrefill = $prefill['paciente'] ?? null;

    function nombreCorto($full)
    {
        $full = trim((string) $full);

        if ($full === '') {
            return null;
        }

        $partes = preg_split('/\s+/', $full);
        $primerNombre = $partes[0] ?? '';
        $ultimoApellido = $partes[count($partes) - 1] ?? '';

        return trim($primerNombre . ' ' . $ultimoApellido);
    }

    $medicoCorto = nombreCorto($medicoPrefill);

    /* Valores predeterminados de la etiqueta */
    $titulo = $titulo ?? 'MESALBUDA';

    $a1  = $a1 ?? 'BUDESONIDA';
    $a1v = $a1v ?? '1';
    $a1u = $a1u ?? 'mg';

    $a2  = $a2 ?? 'MESALAZINA';
    $a2v = $a2v ?? '2';
    $a2u = $a2u ?? 'g';

    $ind1 = $ind1 ?? 'Enfermedad de Crohn';
    $ind2 = $ind2 ?? 'Recto colitis ulcerativa inespecífica';
    $ind3 = $ind3 ?? 'Enema rectal por 133 mL';

    $qf   = $qf ?? 'Q.F. EVELYN GARCÍA';
    $dr   = $dr ?? ($medicoCorto ?: 'DR. ESTEBAN GONZÁLEZ');
    $elab = $elab ?? now()->format('d-m-Y');

    $canRecetas = auth()->check()
        && auth()->user()->hasRole(['Admin', 'Laboratorio']);
@endphp

@push('styles')
<style>
    .etq {
        position: relative;
        width: 100%;
        height: 100vh;
        font-family: Arial, Helvetica, sans-serif;
        color: #111;
    }

    /* Inputs con estilo de impresión */
    .etq input {
        border: none;
        outline: none;
        background: transparent;
        font-family: inherit;
        font-size: inherit;
        font-weight: inherit;
        color: inherit;
        padding: 0;
        margin: 0;
    }

    /* Título */
    .titulo {
        position: fixed;
        top: 40px;
        left: 0;
        right: 0;
        text-align: center;
        font-size: 54px;
        font-weight: 900;
        font-style: italic;
        letter-spacing: 1px;
        color: #333;
    }

    .titulo input {
        width: 520px;
        text-align: center;
        font-size: 54px;
        font-weight: 900;
        font-style: italic;
        letter-spacing: 1px;
        color: #333;
    }

    /* Zona central */
    .cuerpo {
        position: fixed;
        top: 200px;
        left: 80px;
        right: 80px;
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
    }

    .izq {
        font-size: 30px;
        font-weight: 900;
        line-height: 1.9;
    }

    .izq .linea {
        display: flex;
        gap: 18px;
        align-items: baseline;
    }

    .izq .nom {
        width: 260px;
    }

    .izq .val {
        width: 140px;
    }

    .der {
        text-align: right;
        font-size: 30px;
        font-weight: 500;
        line-height: 1.45;
    }

    .der .fuerte {
        margin-top: 18px;
        font-weight: 900;
    }

    /* Pie de la etiqueta */
    .footer {
        position: fixed;
        left: 80px;
        right: 80px;
        bottom: 80px;
        display: grid;
        grid-template-columns: minmax(450px, 1fr) auto minmax(200px, 1fr);
        align-items: end;
        column-gap: 30px;
    }

    .footer-izq {
        font-size: 22px;
        font-weight: 900;
        line-height: 1.35;
    }

    .footer-izq > div {
        min-height: 30px;
    }

    .footer-izq > div > input {
        width: 420px;
    }

    /* Paciente debajo del médico */
    .campo-paciente {
        display: flex;
        align-items: baseline;
        gap: 8px;
    }

    .campo-paciente .lbl {
        white-space: nowrap;
    }

    .footer-izq .campo-paciente input {
        width: 320px;
    }

    /* SO encima de Elab */
    .footer-centro {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        gap: 4px;
        font-size: 22px;
        font-weight: 900;
        white-space: nowrap;
    }

    .campo-footer {
        display: flex;
        align-items: baseline;
    }

    .campo-footer .lbl {
        display: inline-block;
        width: 58px;
        margin-right: 8px;
        text-align: right;
    }

    .footer-centro .campo-footer input {
        width: 150px;
        text-align: left;
    }

    /* Modal de recetas */
    #dlg-recetas::backdrop {
        background: rgba(15, 23, 42, .45);
    }

    #dlg-recetas {
        width: 96%;
        max-width: 780px;
        padding: 0;
        border: none;
        border-radius: 18px;
        box-shadow: 0 18px 45px rgba(15, 23, 42, .38);
        font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
    }

    .dlg-recetas__header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 16px 24px;
        border-bottom: 1px solid #e5e7eb;
        border-radius: 18px 18px 0 0;
        background: linear-gradient(135deg, #0d6efd, #2563eb);
        color: #fff;
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
        background: rgba(15, 23, 42, .18);
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

    .form-row {
        margin: 0;
        font-size: 14px;
    }

    .form-row label {
        display: block;
        margin-bottom: 4px;
        color: #111827;
        font-weight: 600;
    }

    .form-row small {
        font-size: 12px;
    }

    .dlg-recetas__input,
    .dlg-recetas__input-date {
        width: 100%;
        padding: 7px 9px;
        border: 1px solid #d1d5db;
        border-radius: 8px;
        background: #fff;
        font-size: 14px;
        transition:
            border-color .15s ease,
            box-shadow .15s ease,
            background-color .15s ease;
    }

    .dlg-recetas__input:focus,
    .dlg-recetas__input-date:focus {
        outline: none;
        border-color: #2563eb;
        box-shadow: 0 0 0 1px rgba(37, 99, 235, .25);
    }

    .dlg-recetas__footer {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        padding: 10px 24px 16px;
        border-top: 1px solid #e5e7eb;
        border-radius: 0 0 18px 18px;
        background: #f9fafb;
    }

    .btn-primary {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 16px;
        border: none;
        border-radius: 999px;
        background: #0d6efd;
        color: #fff;
        box-shadow: 0 6px 14px rgba(37, 99, 235, .35);
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition:
            background .15s ease,
            transform .08s ease,
            box-shadow .15s ease,
            opacity .15s ease;
    }

    .btn-primary:hover:not(:disabled) {
        background: #0b5ed7;
        transform: translateY(-1px);
        box-shadow: 0 10px 24px rgba(37, 99, 235, .40);
    }

    .btn-primary:disabled {
        opacity: .55;
        cursor: not-allowed;
        box-shadow: none;
    }

    .btn-outline {
        padding: 8px 16px;
        border: 1px solid #d1d5db;
        border-radius: 999px;
        background: #fff;
        font-size: 14px;
        font-weight: 500;
        cursor: pointer;
        transition:
            background .15s ease,
            border-color .15s ease,
            color .15s ease;
    }

    .btn-outline:hover {
        border-color: #9ca3af;
        background: #f3f4f6;
    }

    .sugs-panel {
        position: absolute;
        z-index: 1000;
        top: calc(100% + 4px);
        left: 0;
        width: 100%;
        max-height: 230px;
        overflow-y: auto;
        border: 1px solid #d1d5db;
        border-radius: 10px;
        background: #fff;
        box-shadow: 0 14px 30px rgba(15, 23, 42, .25);
    }

    .sugs-item {
        padding: 8px 11px;
        font-size: 13px;
        cursor: pointer;
    }

    .sugs-item:hover {
        background: #eff6ff;
    }

    .sugs-item.empty {
        color: #9ca3af;
        cursor: default;
    }

    #medico_sel {
        color: #4b5563;
        font-size: 12px;
    }

    @media (min-width: 640px) {
        .dlg-recetas__grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media print {
        input {
            caret-color: transparent;
        }

        .etq input::placeholder {
            color: transparent;
        }
    }
</style>
@endpush

@section('content')

@if($canRecetas)
    <dialog id="dlg-recetas">
        <form
            method="POST"
            action="{{ route('recetas.store') }}"
            id="frm-recetas"
            novalidate
        >
            @csrf

            <div class="dlg-recetas__header">
                <div>
                    <h3 class="dlg-recetas__title">
                        Datos para guardar recetas
                    </h3>

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
                        <label for="num_etiquetas">
                            Número de etiquetas a imprimir
                        </label>

                        <input
                            type="number"
                            name="num_etiquetas"
                            id="num_etiquetas"
                            class="dlg-recetas__input"
                            value="1"
                            min="1"
                            max="200"
                            required
                        >

                        <small>
                            Si eliges más de 1, el paciente se llenará automáticamente.
                        </small>
                    </div>

                    <div class="form-row">
                        <label for="codigo_formula">
                            Código de fórmula
                        </label>

                        <input
                            type="text"
                            name="codigo_formula"
                            id="codigo_formula"
                            class="dlg-recetas__input"
                            value="{{ $formula->codigo ?? '' }}"
                            required
                        >

                        <small>Este código es editable.</small>
                    </div>

                    <div class="form-row">
                        <label for="so_modal">SO</label>

                        <input
                            type="text"
                            name="so"
                            id="so_modal"
                            class="dlg-recetas__input"
                            inputmode="numeric"
                            pattern="\d+"
                            maxlength="50"
                            placeholder="Solo números"
                            value="{{ $soPrefill }}"
                            required
                        >
                    </div>

                    <div class="form-row">
                        <label for="fecha">Fecha</label>

                        <input
                            type="date"
                            name="fecha"
                            id="fecha"
                            class="dlg-recetas__input-date"
                            value="{{ now()->toDateString() }}"
                            required
                        >
                    </div>

                    <div
                        class="form-row"
                        style="grid-column: 1 / -1;"
                    >
                        <label>
                            Médico (buscar por nombre/apellido)
                        </label>

                        <div style="position: relative; max-width: 420px;">
                            <input
                                type="text"
                                id="medico_search"
                                class="dlg-recetas__input"
                                placeholder="Ej. María López"
                                autocomplete="off"
                                value="{{ $medicoPrefill }}"
                            >

                            <div id="medico_sugs"></div>
                        </div>

                        <input
                            type="hidden"
                            name="cedula_medico"
                            id="cedula_medico"
                            required
                        >

                        <input
                            type="hidden"
                            name="medico_nombre"
                            id="medico_nombre"
                            value="{{ $medicoPrefill }}"
                        >

                        <small
                            id="medico_sel"
                            style="display: block; margin-top: 4px;"
                        ></small>
                    </div>

                    <div
                        id="grp_paciente"
                        class="form-row"
                        style="grid-column: 1 / -1; max-width: 420px;"
                    >
                        <label for="paciente">
                            Pte: (opcional si solo es una etiqueta)
                        </label>

                        <input
                            type="text"
                            name="paciente"
                            id="paciente"
                            class="dlg-recetas__input"
                            maxlength="50"
                            placeholder="Dejar vacío para nombre aleatorio"
                            value="{{ $pacientePrefill }}"
                        >
                    </div>
                </div>

                <input
                    type="hidden"
                    name="redirect_to"
                    value="{{ url()->current() }}"
                >
            </div>

            <div class="dlg-recetas__footer">
                <button
                    type="button"
                    id="btn-cancel"
                    class="btn-outline"
                >
                    Cancelar
                </button>

                <button
                    type="submit"
                    id="btn-save"
                    class="btn-primary"
                    disabled
                >
                    Guardar recetas y continuar
                </button>
            </div>
        </form>
    </dialog>
@endif

<div class="etq">

    {{-- Título --}}
    <div class="titulo">
        <input
            type="text"
            value="{{ $titulo }}"
            aria-label="Título de la fórmula"
        >
    </div>

    {{-- Contenido central --}}
    <div class="cuerpo">

        <div class="izq">
            <div class="linea">
                <input
                    class="nom"
                    type="text"
                    value="{{ $a1 }}"
                    aria-label="Primer componente"
                >

                <div style="display: flex; gap: 10px; align-items: baseline;">
                    <input
                        class="val"
                        type="text"
                        value="{{ $a1v }}"
                        aria-label="Cantidad del primer componente"
                    >

                    <input
                        style="width: 80px;"
                        type="text"
                        value="{{ $a1u }}"
                        aria-label="Unidad del primer componente"
                    >
                </div>
            </div>

            <div class="linea">
                <input
                    class="nom"
                    type="text"
                    value="{{ $a2 }}"
                    aria-label="Segundo componente"
                >

                <div style="display: flex; gap: 10px; align-items: baseline;">
                    <input
                        class="val"
                        type="text"
                        value="{{ $a2v }}"
                        aria-label="Cantidad del segundo componente"
                    >

                    <input
                        style="width: 80px;"
                        type="text"
                        value="{{ $a2u }}"
                        aria-label="Unidad del segundo componente"
                    >
                </div>
            </div>
        </div>

        <div class="der">
            <div>
                <input
                    style="width: 520px; text-align: right;"
                    type="text"
                    value="{{ $ind1 }}"
                    aria-label="Primera indicación"
                >
            </div>

            <div>
                <input
                    style="width: 520px; text-align: right;"
                    type="text"
                    value="{{ $ind2 }}"
                    aria-label="Segunda indicación"
                >
            </div>

            <div class="fuerte">
                <input
                    style="width: 520px; text-align: right; font-weight: 900;"
                    type="text"
                    value="{{ $ind3 }}"
                    aria-label="Presentación"
                >
            </div>
        </div>
    </div>

    {{-- Pie de la etiqueta --}}
    <div class="footer">

        <div class="footer-izq">
            <div>
                <input
                    type="text"
                    value="{{ $qf }}"
                    aria-label="Químico farmacéutico"
                >
            </div>

            <div>
                <input
                    type="text"
                    value="{{ $dr }}"
                    aria-label="Médico"
                >
            </div>

            {{-- Paciente editable: siempre inicia vacío --}}
            <div class="campo-paciente">
                <span class="lbl">Pte:</span>

                <input
                    type="text"
                    id="paciente_etiqueta"
                    name="paciente_etiqueta"
                    value=""
                    placeholder="Nombre del paciente"
                    maxlength="80"
                    autocomplete="off"
                    aria-label="Paciente"
                >
            </div>
        </div>

        <div class="footer-centro">

            {{-- SO editable encima de Elab --}}
            <div class="campo-footer">
                <span class="lbl">SO:</span>

                <input
                    type="text"
                    id="so_etiqueta"
                    name="so_etiqueta"
                    value=""
                    placeholder="Número"
                    inputmode="numeric"
                    maxlength="50"
                    autocomplete="off"
                    aria-label="SO"
                >
            </div>

            <div class="campo-footer">
                <span class="lbl">Elab:</span>

                <input
                    type="text"
                    value="{{ $elab }}"
                    autocomplete="off"
                    aria-label="Fecha de elaboración"
                >
            </div>
        </div>

        <div></div>
    </div>
</div>

@if($canRecetas)
<script>
    const mustOpen = false;
    const dlg = document.getElementById('dlg-recetas');

    if (mustOpen && dlg && !dlg.open) {
        dlg.showModal();
    }

    document
        .getElementById('btn-cancel')
        ?.addEventListener('click', () => dlg.close());

    const form          = document.getElementById('frm-recetas');
    const btnSave       = document.getElementById('btn-save');
    const so            = document.getElementById('so_modal');
    const numEt         = document.getElementById('num_etiquetas');
    const grpPac        = document.getElementById('grp_paciente');
    const inpPac        = document.getElementById('paciente');
    const inpMed        = document.getElementById('medico_search');
    const cedulaHidden  = document.getElementById('cedula_medico');
    const selText       = document.getElementById('medico_sel');
    const sugBox        = document.getElementById('medico_sugs');
    const codigoFormula = document.getElementById('codigo_formula');

    so?.addEventListener('input', (event) => {
        event.target.value = (event.target.value || '')
            .replace(/[^0-9]/g, '');

        event.target.setCustomValidity('');
        updateSaveEnabled();
    });

    so?.addEventListener('invalid', function () {
        if (this.validity.patternMismatch) {
            this.setCustomValidity(
                'SO solo acepta dígitos del 0 al 9.'
            );
        }
    });

    function togglePaciente() {
        const cantidad = parseInt(numEt?.value || '1', 10);

        if (cantidad > 1) {
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
    numEt?.addEventListener('change', togglePaciente);

    togglePaciente();

    let sugPanel = null;

    function clearSugs() {
        if (sugPanel) {
            sugPanel.remove();
            sugPanel = null;
        }
    }

    async function buscarMedicos(consulta) {
        if (!consulta || consulta.length < 2) {
            clearSugs();
            return;
        }

        const url =
            "{{ route('medicos.buscar') }}"
            + "?q="
            + encodeURIComponent(consulta);

        let lista = [];

        try {
            const respuesta = await fetch(url, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            });

            if (!respuesta.ok) {
                clearSugs();
                return;
            }

            const datos = await respuesta.json();

            lista = Array.isArray(datos)
                ? datos
                : (Array.isArray(datos?.data) ? datos.data : []);
        } catch (error) {
            clearSugs();
            return;
        }

        clearSugs();

        sugPanel = document.createElement('div');
        sugPanel.className = 'sugs-panel';

        if (!lista.length) {
            const empty = document.createElement('div');

            empty.className = 'sugs-item empty';
            empty.textContent = 'Sin resultados';

            sugPanel.appendChild(empty);
            sugBox.appendChild(sugPanel);

            return;
        }

        lista.forEach((item) => {
            const opcion = document.createElement('div');

            opcion.className = 'sugs-item';
            opcion.textContent =
                item.label + ' — C.I. ' + item.cedula;

            opcion.addEventListener('click', () => {
                inpMed.value = item.label;
                cedulaHidden.value = item.cedula;

                document.getElementById('medico_nombre').value =
                    item.label;

                selText.textContent =
                    'Seleccionado: '
                    + item.label
                    + ' (C.I. '
                    + item.cedula
                    + ')';

                clearSugs();
                updateSaveEnabled();
            });

            sugPanel.appendChild(opcion);
        });

        sugBox.appendChild(sugPanel);
    }

    inpMed?.addEventListener('input', (event) => {
        cedulaHidden.value = '';
        selText.textContent = '';

        buscarMedicos(event.target.value.trim());
        updateSaveEnabled();
    });

    document.addEventListener('click', (event) => {
        if (
            !sugBox.contains(event.target)
            && event.target !== inpMed
        ) {
            clearSugs();
        }
    });

    function updateSaveEnabled() {
        const soOk = Boolean(so?.value)
            && /^\d+$/.test(so.value);

        const medOk = Boolean(cedulaHidden?.value);
        const codOk = Boolean(codigoFormula?.value.trim());
        const cantidad = parseInt(numEt?.value || '1', 10);

        btnSave.disabled = !(
            soOk
            && medOk
            && codOk
            && cantidad >= 1
        );
    }

    codigoFormula?.addEventListener(
        'input',
        updateSaveEnabled
    );

    updateSaveEnabled();

    form?.addEventListener('submit', (event) => {
        const cantidad = parseInt(numEt?.value || '1', 10);

        if (!cedulaHidden.value) {
            event.preventDefault();
            alert('Selecciona un médico de la lista.');
            inpMed.focus();
            return;
        }

        if (!codigoFormula.value.trim()) {
            event.preventDefault();
            alert('Ingresa el código de fórmula.');
            codigoFormula.focus();
            return;
        }

        if (cantidad > 1) {
            inpPac.disabled = true;
            inpPac.value = '';
        }
    });
</script>
@endif

<script>
    /*
     * El campo SO de la etiqueta solo admite números.
     * Este campo es independiente del modal y puede editarse
     * directamente antes de imprimir.
     */
    const soEtiqueta = document.getElementById('so_etiqueta');

    soEtiqueta?.addEventListener('input', (event) => {
        event.target.value = (event.target.value || '')
            .replace(/[^0-9]/g, '');
    });
</script>

@endsection
@extends('layouts.app')

@section('title', 'Fórmulas establecidas homeopáticas | Ortomed')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
  <div>
    <h2 class="h4 mb-1">Fórmulas establecidas homeopáticas</h2>
    <p class="text-muted mb-0">Busca y agrega únicamente las fórmulas que vas a utilizar.</p>
  </div>
  <a href="{{ route('formulas-homeo.nueva') }}" class="btn btn-primary">
    <i class="bi bi-plus-circle me-1"></i> Nueva fórmula
  </a>
</div>

@if($errors->any())
  <div class="alert alert-danger">
    @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
  </div>
@endif

<div id="mensajeEstablecidas" class="alert d-none" role="alert"></div>

<div class="card shadow-sm border-0 mb-4">
  <div class="card-body">
    <h3 class="h5 mb-3">Buscar fórmula</h3>
    <form method="POST" action="{{ route('formulas-homeo.establecidas.agregar') }}"
          id="formAgregarFormula" class="row g-2 align-items-end">
      @csrf
      <div class="col-md-10 position-relative">
        <label for="buscarFormulaHomeo" class="form-label">Código, nombre o categoría</label>
        <input type="search" id="buscarFormulaHomeo" class="form-control"
               placeholder="Ej.: F-HOMEO-000001" autocomplete="off">
        <input type="hidden" name="formula_id" id="formulaHomeoId">
        <div id="resultadosFormulasHomeo" class="list-group position-absolute w-100 shadow d-none"
             style="z-index:1050; max-height:300px; overflow-y:auto;"></div>
      </div>
      <div class="col-md-2">
        <button class="btn btn-primary w-100" id="agregarFormulaHomeo" disabled>
          <i class="bi bi-plus-circle me-1"></i> Agregar
        </button>
      </div>
    </form>
  </div>
</div>

<div class="card shadow-sm border-0">
  <div class="card-header bg-transparent d-flex flex-wrap justify-content-between align-items-center gap-2 py-3">
    <h3 class="h5 mb-0">
      Fórmulas seleccionadas
      <span class="badge text-bg-primary ms-1">{{ $formulas->count() }}</span>
    </h3>
    <div class="d-flex gap-2">
      <button type="button" class="btn btn-success btn-sm" id="abrirGenerarRecetas"
              @disabled($formulas->isEmpty())>
        <i class="bi bi-file-earmark-pdf me-1"></i> Generar todas las recetas
      </button>
      <form method="POST" action="{{ route('formulas-homeo.establecidas.limpiar') }}">
        @csrf @method('DELETE')
        <button class="btn btn-outline-danger btn-sm" @disabled($formulas->isEmpty())>
          Eliminar todas
        </button>
      </form>
    </div>
  </div>

  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th>Código / Fórmula</th>
          <th>Composición</th>
          <th>Médico</th>
          <th>Presentación cotizada</th>
          <th style="min-width:170px;">Cantidad</th>
          <th class="text-end">Acciones</th>
        </tr>
      </thead>
      <tbody id="tablaFormulasSeleccionadas">
        @forelse($formulas as $formula)
          @php
            $composicion = $formula->items->map(fn($item) => trim(
              $item->activo.($item->dilusion !== '' ? ' '.$item->dilusion : '')
            ))->implode("\n");
          @endphp
          <tr @class(['table-success' => (int) session('ultima_formula_homeo_id') === (int) $formula->id])>
            <td>
              <div class="fw-semibold">{{ $formula->codigo }}</div>
              <small class="text-muted">{{ $formula->nombre_etiqueta }} · {{ $formula->categoria }}</small>
            </td>
            <td style="min-width:240px; white-space:pre-line;">{{ $composicion }}</td>
            <td>
              {{ $formula->medico ?: '—' }}
              @if($formula->cedula_medico)
                <small class="d-block text-muted">{{ $formula->cedula_medico }}</small>
              @endif
            </td>
            <td><span class="badge text-bg-info">{{ $formula->presentacion }}</span></td>
            <td>
              <input type="number" class="form-control form-control-sm cantidad-formula-homeo"
                     name="cantidades[{{ $formula->id }}]" value="1" min="1" max="500"
                     form="formRecetasSeleccionadas" required
                     data-resumen="resumenCantidad{{ $formula->id }}">
              <small class="text-muted d-block mt-1" id="resumenCantidad{{ $formula->id }}">
                1 receta · 1 mes
              </small>
            </td>
            <td class="text-end text-nowrap">
              <button type="button" class="btn btn-sm btn-outline-secondary btn-copiar-composicion"
                      data-composicion="{{ $composicion }}" title="Copiar composición">
                <i class="bi bi-clipboard"></i> Copiar
              </button>
              <a href="{{ route('formulas-homeo.editar', $formula) }}"
                 class="btn btn-sm btn-primary" title="Crear una fórmula nueva con esta composición">
                <i class="bi bi-files"></i> Usar como base
              </a>
              <form method="POST" action="{{ route('formulas-homeo.establecidas.quitar', $formula) }}"
                    class="d-inline">
                @csrf @method('DELETE')
                <button class="btn btn-sm btn-outline-danger" title="Quitar de la lista">
                  <i class="bi bi-trash"></i>
                </button>
              </form>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="6" class="text-center text-muted py-5">
              La lista está vacía. Busca una fórmula y agrégala.
            </td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

<div class="modal fade" id="modalRecetasSeleccionadas" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form id="formRecetasSeleccionadas" method="POST"
            action="{{ route('formulas-homeo.establecidas.recetas') }}">
        @csrf
        <div class="modal-header">
          <h5 class="modal-title">Generar {{ $formulas->count() }} receta(s)</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body">
          <div class="alert alert-info py-2">
            Cada receta tendrá como máximo 6 frascos. Un frasco equivale a un mes de tratamiento.
            Todo se descargará dentro de un solo PDF.
          </div>
          <div class="alert alert-light border py-2" id="resumenLoteRecetas"></div>

          <div class="mb-3">
            <label for="presentacionLote" class="form-label">Presentación para todas las fórmulas</label>
            <select class="form-select" id="presentacionLote" name="presentacion" required>
              <option value="">Confirma una presentación</option>
              @foreach($presentaciones as $presentacion)
                <option value="{{ $presentacion }}">{{ $presentacion }}</option>
              @endforeach
            </select>
          </div>

          <div class="mb-3">
            <label for="soLote" class="form-label">SO</label>
            <input type="text" class="form-control" id="soLote" name="so"
                   inputmode="numeric" pattern="\d+" required>
          </div>

          <div class="mb-3">
            <label for="medicoLote" class="form-label">Cambiar médico para todas (opcional)</label>
            <div class="position-relative">
              <input type="search" class="form-control" id="medicoLote"
                     placeholder="Déjalo vacío para usar el médico de cada fórmula" autocomplete="off">
              <input type="hidden" name="cedula_medico" id="cedulaMedicoLote">
              <div id="resultadosMedicosLote" class="list-group position-absolute w-100 shadow d-none"
                   style="z-index:1060; max-height:230px; overflow-y:auto;"></div>
            </div>
            <div class="form-text" id="estadoMedicoLote">
              Si seleccionas otro médico, debe tener firma y reemplazará al de todas las fórmulas.
            </div>
          </div>

          <div class="mb-3">
            <label for="pacienteLote" class="form-label">Paciente (opcional)</label>
            <input type="text" class="form-control" id="pacienteLote" name="paciente" maxlength="255">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-success" id="generarLote">
            <i class="bi bi-file-earmark-pdf me-1"></i> Generar PDF y enlaces
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
  const buscarUrl = @json(route('formulas-homeo.establecidas.buscar'));
  const medicosUrl = @json(route('medicos.buscar'));
  const buscador = document.getElementById('buscarFormulaHomeo');
  const resultados = document.getElementById('resultadosFormulasHomeo');
  const formulaId = document.getElementById('formulaHomeoId');
  const botonAgregar = document.getElementById('agregarFormulaHomeo');
  const mensaje = document.getElementById('mensajeEstablecidas');
  let timerFormula;

  function escapar(valor) {
    const div = document.createElement('div');
    div.textContent = valor ?? '';
    return div.innerHTML;
  }

  function avisar(texto, tipo = 'success') {
    mensaje.className = `alert alert-${tipo}`;
    mensaje.textContent = texto;
    setTimeout(() => mensaje.classList.add('d-none'), 2500);
  }

  buscador.addEventListener('input', () => {
    formulaId.value = '';
    botonAgregar.disabled = true;
    clearTimeout(timerFormula);
    const q = buscador.value.trim();
    if (!q) {
      resultados.classList.add('d-none');
      return;
    }
    timerFormula = setTimeout(() => {
      fetch(`${buscarUrl}?q=${encodeURIComponent(q)}`, { headers: { Accept: 'application/json' } })
        .then(response => response.json())
        .then(formulas => {
          resultados.innerHTML = formulas.length
            ? formulas.map(formula => `
                <button type="button" class="list-group-item list-group-item-action"
                        data-id="${formula.id}" data-display="${escapar(formula.codigo)} · ${escapar(formula.nombre_etiqueta)}">
                  <strong>${escapar(formula.codigo)} · ${escapar(formula.nombre_etiqueta)}</strong>
                  <small class="d-block text-muted">${escapar(formula.categoria || 'Sin categoría')} · ${escapar(formula.presentacion)}</small>
                </button>`).join('')
            : '<div class="list-group-item text-muted">No se encontraron fórmulas.</div>';
          resultados.classList.remove('d-none');
        });
    }, 250);
  });

  resultados.addEventListener('click', event => {
    const opcion = event.target.closest('[data-id]');
    if (!opcion) return;
    formulaId.value = opcion.dataset.id;
    buscador.value = opcion.dataset.display;
    botonAgregar.disabled = false;
    resultados.classList.add('d-none');
  });

  document.querySelectorAll('.btn-copiar-composicion').forEach(boton => {
    boton.addEventListener('click', () => {
      const texto = boton.dataset.composicion || '';
      const copiar = navigator.clipboard?.writeText
        ? navigator.clipboard.writeText(texto)
        : Promise.reject();
      copiar.then(() => {
        const original = boton.innerHTML;
        boton.innerHTML = '<i class="bi bi-check2"></i> Copiado';
        setTimeout(() => boton.innerHTML = original, 1300);
      }).catch(() => avisar('No se pudo copiar la composición.', 'danger'));
    });
  });

  document.addEventListener('click', event => {
    if (!event.target.closest('#buscarFormulaHomeo') && !event.target.closest('#resultadosFormulasHomeo')) {
      resultados.classList.add('d-none');
    }
  });

  const abrirModal = document.getElementById('abrirGenerarRecetas');
  if (!abrirModal) return;
  const modal = new bootstrap.Modal(document.getElementById('modalRecetasSeleccionadas'));
  const form = document.getElementById('formRecetasSeleccionadas');
  const generar = document.getElementById('generarLote');
  const medicoInput = document.getElementById('medicoLote');
  const cedulaMedico = document.getElementById('cedulaMedicoLote');
  const resultadosMedicos = document.getElementById('resultadosMedicosLote');
  const estadoMedico = document.getElementById('estadoMedicoLote');
  const resumenLote = document.getElementById('resumenLoteRecetas');
  const cantidades = [...document.querySelectorAll('.cantidad-formula-homeo')];
  let timerMedico;

  function gruposReceta(cantidad) {
    let restantes = Math.max(0, parseInt(cantidad, 10) || 0);
    const grupos = [];
    while (restantes > 0) {
      grupos.push(Math.min(6, restantes));
      restantes -= 6;
    }
    return grupos;
  }

  function actualizarCantidades() {
    let totalRecetas = 0;
    let totalFrascos = 0;
    cantidades.forEach(input => {
      const grupos = gruposReceta(input.value);
      totalRecetas += grupos.length;
      totalFrascos += grupos.reduce((suma, valor) => suma + valor, 0);
      const resumenFila = document.getElementById(input.dataset.resumen);
      if (resumenFila) {
        const duraciones = grupos.map(valor => `${valor} ${valor === 1 ? 'mes' : 'meses'}`);
        resumenFila.textContent = grupos.length
          ? `${grupos.length} receta(s): ${duraciones.join(' + ')}`
          : '';
      }
    });
    resumenLote.textContent = `${totalRecetas} receta(s) · ${totalFrascos} frasco(s) en total`;
  }

  cantidades.forEach(input => input.addEventListener('input', actualizarCantidades));
  abrirModal.addEventListener('click', () => {
    actualizarCantidades();
    modal.show();
  });

  medicoInput.addEventListener('input', () => {
    cedulaMedico.value = '';
    estadoMedico.className = 'form-text';
    estadoMedico.textContent = medicoInput.value.trim()
      ? 'Selecciona un médico del listado.'
      : 'Se utilizará el médico guardado en cada fórmula.';
    clearTimeout(timerMedico);
    const q = medicoInput.value.trim();
    if (!q) {
      resultadosMedicos.classList.add('d-none');
      return;
    }
    timerMedico = setTimeout(() => {
      fetch(`${medicosUrl}?q=${encodeURIComponent(q)}`, { headers: { Accept: 'application/json' } })
        .then(response => response.json())
        .then(medicos => {
          resultadosMedicos.innerHTML = medicos.length
            ? medicos.map(medico => `
                <button type="button" class="list-group-item list-group-item-action"
                        data-cedula="${escapar(medico.cedula)}" data-nombre="${escapar(medico.label)}"
                        data-firma="${medico.firma ? '1' : '0'}">
                  <div class="d-flex justify-content-between gap-2">
                    <span><strong>${escapar(medico.label)}</strong><small class="d-block text-muted">${escapar(medico.cedula)}</small></span>
                    <span class="badge ${medico.firma ? 'text-bg-success' : 'text-bg-danger'} align-self-center">
                      ${medico.firma ? 'Con firma' : 'Sin firma'}
                    </span>
                  </div>
                </button>`).join('')
            : '<div class="list-group-item text-muted">No se encontraron médicos.</div>';
          resultadosMedicos.classList.remove('d-none');
        });
    }, 250);
  });

  resultadosMedicos.addEventListener('click', event => {
    const opcion = event.target.closest('[data-cedula]');
    if (!opcion) return;
    medicoInput.value = opcion.dataset.nombre;
    resultadosMedicos.classList.add('d-none');
    if (opcion.dataset.firma !== '1') {
      cedulaMedico.value = '';
      estadoMedico.className = 'form-text text-danger';
      estadoMedico.textContent = 'Este médico no tiene firma y no puede utilizarse.';
      return;
    }
    cedulaMedico.value = opcion.dataset.cedula;
    estadoMedico.className = 'form-text text-success';
    estadoMedico.textContent = `Este médico reemplazará al original en todas las recetas · ${opcion.dataset.cedula}`;
  });

  form.addEventListener('submit', event => {
    event.preventDefault();
    if (!form.reportValidity()) return;
    if (medicoInput.value.trim() !== '' && cedulaMedico.value === '') {
      estadoMedico.className = 'form-text text-danger';
      estadoMedico.textContent = 'Selecciona un médico válido o deja el campo completamente vacío.';
      return;
    }

    generar.disabled = true;
    modal.hide();
    window.submitPdfFormWithPublicLinks(form, {
      defaultFilename: 'recetas-homeopaticas.pdf',
      errorMessage: 'No se pudieron generar las recetas.',
    }).then(() => {
      generar.disabled = true;
      avisar('PDF y enlaces generados. La selección fue procesada.');
    }).catch(error => {
      generar.disabled = false;
      alert(error.message || 'No se pudieron generar las recetas.');
      modal.show();
    });
  });

  actualizarCantidades();
});
</script>
@endpush

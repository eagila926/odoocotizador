@extends('layouts.app')

@section('title', 'Nueva fórmula homeopática | Ortomed')

@push('head')
<style>
  .homeo-search { position: relative; }
  .homeo-results {
    position: absolute;
    z-index: 1050;
    inset: calc(100% + 2px) 0 auto;
    max-height: 300px;
    overflow-y: auto;
  }
  .homeo-result { cursor: pointer; }
  .homeo-result:hover, .homeo-result:focus { background: var(--bs-tertiary-bg); }
  .homeo-summary { background: linear-gradient(135deg, #eef7ff, #f0fff8); }
  [data-bs-theme="dark"] .homeo-summary { background: #17212b; }
</style>
@endpush

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
  <div>
    <h2 class="h4 mb-1">
      {{ $formulaEditando ? 'Nueva fórmula basada en '.$formulaEditando->codigo : 'Nueva fórmula homeopática' }}
    </h2>
    <p class="text-muted mb-0">Selecciona los activos, la presentación y registra la cotización.</p>
  </div>
  <div class="d-flex align-items-center gap-2">
    @if($formulaEditando)
      <form method="POST" action="{{ route('formulas-homeo.cancelar-edicion') }}">
        @csrf
        <button class="btn btn-outline-secondary btn-sm">Cancelar copia</button>
      </form>
    @endif
    <span class="badge text-bg-primary fs-6" id="contadorActivos">0 activos</span>
  </div>
</div>

@if(session('ok'))
  <div class="alert alert-success alert-dismissible fade show" role="alert">
    {{ session('ok') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
  </div>
@endif

@if($errors->any())
  <div class="alert alert-danger">
    <strong>No se pudo guardar la cotización.</strong>
    <ul class="mb-0 mt-1">
      @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
    </ul>
  </div>
@endif

<div id="mensajeModulo" class="alert d-none" role="alert"></div>

<div class="row g-4">
  <div class="col-lg-7">
    <div class="card shadow-sm border-0 mb-4">
      <div class="card-body p-4">
        <h3 class="h5 mb-3">1. Agregar activos</h3>
        <div class="row g-3 align-items-end">
          <div class="col-md-8 homeo-search">
            <label for="buscarActivoHomeo" class="form-label">Buscar por nombre del activo</label>
            <input type="search" class="form-control" id="buscarActivoHomeo"
                   placeholder="Ej.: Arnica montana" autocomplete="off">
            <div id="resultadosHomeo" class="homeo-results list-group shadow d-none"></div>
          </div>
          <div class="col-md-4">
            <label for="dilusionHomeo" class="form-label">
              Dilución <span class="text-muted">(opcional)</span>
            </label>
            <input type="text" class="form-control" id="dilusionHomeo" maxlength="50"
                   placeholder="Ej.: 30 CH">
          </div>
          <div class="col-12 d-flex gap-2">
            <button type="button" class="btn btn-primary" id="agregarActivo" disabled>
              <i class="bi bi-plus-circle me-1"></i> Agregar activo
            </button>
            <span class="small text-muted align-self-center" id="activoElegido">Selecciona un resultado.</span>
          </div>
        </div>
      </div>
    </div>

    <div class="card shadow-sm border-0">
      <div class="card-header bg-transparent d-flex justify-content-between align-items-center py-3">
        <h3 class="h5 mb-0">Activos seleccionados</h3>
        <button type="button" class="btn btn-sm btn-outline-danger" id="limpiarActivos">
          Eliminar todos
        </button>
      </div>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>Activo</th>
              <th>Categoría</th>
              <th>Dilución</th>
              <th class="text-end">Acción</th>
            </tr>
          </thead>
          <tbody id="itemsHomeo">
            <tr><td colspan="4" class="text-center text-muted py-4">Cargando activos…</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="col-lg-5">
    <form method="POST" action="{{ route('formulas-homeo.guardar') }}" class="card shadow-sm border-0">
      @csrf
      <div class="card-body p-4">
        <h3 class="h5 mb-3">2. Datos de la cotización</h3>

        <div class="mb-3">
          <label for="nombre_etiqueta" class="form-label">Nombre de la etiqueta</label>
          <input type="text" class="form-control" id="nombre_etiqueta" name="nombre_etiqueta"
                 value="{{ old('nombre_etiqueta', $formulaEditando?->nombre_etiqueta) }}" maxlength="150" required>
        </div>

        <div class="mb-3">
          <label for="categoria" class="form-label">Categoría</label>
          <select class="form-select" id="categoria" name="categoria" required>
            <option value="">Selecciona una categoría</option>
            @foreach($categorias as $categoria)
              <option value="{{ $categoria }}" @selected(old('categoria', $formulaEditando?->categoria) === $categoria)>
                {{ $categoria }}
              </option>
            @endforeach
          </select>
        </div>

        <div class="mb-3">
          <label for="medicoCotizacion" class="form-label">Médico</label>
          <div class="position-relative">
            <input type="search" class="form-control" id="medicoCotizacion" name="medico"
                   value="{{ old('medico', $formulaEditando?->medico) }}"
                   placeholder="Buscar por nombre o cédula" maxlength="150"
                   autocomplete="off" required>
            <input type="hidden" id="cedulaMedicoCotizacion" name="cedula_medico"
                   value="{{ old('cedula_medico', $formulaEditando?->cedula_medico) }}" required>
            <div id="resultadosMedicosCotizacion"
                 class="list-group position-absolute w-100 shadow d-none"
                 style="z-index:1060; max-height:230px; overflow-y:auto;"></div>
          </div>
          <div class="form-text" id="estadoMedicoCotizacion">
            @if(old('cedula_medico', $formulaEditando?->cedula_medico))
              Médico seleccionado · Cédula: {{ old('cedula_medico', $formulaEditando?->cedula_medico) }}
            @else
              Selecciona un médico con firma registrada.
            @endif
          </div>
        </div>

        <div class="mb-3">
          <label for="presentacion" class="form-label">Presentación</label>
          <select class="form-select" id="presentacion" name="presentacion" required>
            <option value="">Selecciona una presentación</option>
            @foreach($presentaciones as $presentacion)
              <option value="{{ $presentacion }}" @selected(old('presentacion', $formulaEditando?->presentacion) === $presentacion)>
                {{ $presentacion }}
              </option>
            @endforeach
          </select>
        </div>

        <button type="submit" class="btn btn-success btn-lg w-100" id="guardarCotizacion" disabled>
          <i class="bi bi-check2-circle me-1"></i>
          Guardar como fórmula nueva
        </button>
      </div>
    </form>
  </div>
</div>
@endsection

@push('scripts')
<script>
(() => {
  const urls = {
    buscar: @json(route('formulas-homeo.buscar')),
    listar: @json(route('formulas-homeo.listar')),
    agregar: @json(route('formulas-homeo.agregar')),
    limpiar: @json(route('formulas-homeo.limpiar')),
    eliminarBase: @json(url('/formulas-homeopaticas/items')),
    medicos: @json(route('medicos.buscar')),
  };
  const csrf = document.querySelector('meta[name="csrf-token"]').content;
  const buscar = document.getElementById('buscarActivoHomeo');
  const resultados = document.getElementById('resultadosHomeo');
  const dilusion = document.getElementById('dilusionHomeo');
  const botonAgregar = document.getElementById('agregarActivo');
  const elegido = document.getElementById('activoElegido');
  const tabla = document.getElementById('itemsHomeo');
  const contador = document.getElementById('contadorActivos');
  const guardar = document.getElementById('guardarCotizacion');
  const mensaje = document.getElementById('mensajeModulo');
  let activoSeleccionado = null;
  let temporizador;
  let temporizadorMedico;
  let cantidadItems = 0;

  function actualizarGuardar() {
    guardar.disabled = cantidadItems === 0 ||
      document.getElementById('cedulaMedicoCotizacion').value === '';
  }

  function avisar(texto, tipo = 'success') {
    mensaje.className = `alert alert-${tipo}`;
    mensaje.textContent = texto;
    window.setTimeout(() => mensaje.classList.add('d-none'), 3000);
  }

  async function peticion(url, options = {}) {
    const response = await fetch(url, {
      ...options,
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrf,
        ...(options.headers || {}),
      },
    });
    if (!response.ok) {
      const data = await response.json().catch(() => ({}));
      throw new Error(Object.values(data.errors || {}).flat()[0] || data.message || 'Ocurrió un error.');
    }
    return response.json();
  }

  function escapar(valor) {
    const div = document.createElement('div');
    div.textContent = valor ?? '';
    return div.innerHTML;
  }

  async function cargarItems() {
    try {
      const items = await peticion(urls.listar);
      cantidadItems = items.length;
      contador.textContent = `${items.length} ${items.length === 1 ? 'activo' : 'activos'}`;
      actualizarGuardar();
      if (!items.length) {
        tabla.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-4">Aún no has seleccionado activos.</td></tr>';
        return;
      }
      tabla.innerHTML = items.map(item => `
        <tr>
          <td class="fw-semibold">${escapar(item.activo)}</td>
          <td>${escapar(item.activo_homeo?.categoria || '—')}</td>
          <td>${escapar(item.dilusion || '')}</td>
          <td class="text-end">
            <button type="button" class="btn btn-sm btn-outline-danger" data-eliminar="${item.id}">
              <i class="bi bi-trash"></i>
            </button>
          </td>
        </tr>`).join('');
    } catch (error) {
      avisar(error.message, 'danger');
    }
  }

  buscar.addEventListener('input', () => {
    activoSeleccionado = null;
    botonAgregar.disabled = true;
    elegido.textContent = 'Selecciona un resultado.';
    clearTimeout(temporizador);
    const q = buscar.value.trim();
    if (!q) {
      resultados.classList.add('d-none');
      resultados.innerHTML = '';
      return;
    }
    temporizador = setTimeout(async () => {
      try {
        const activos = await peticion(`${urls.buscar}?q=${encodeURIComponent(q)}`);
        resultados.innerHTML = activos.length
          ? activos.map(activo => `
              <button type="button" class="list-group-item list-group-item-action homeo-result"
                      data-id="${activo.id}" data-nombre="${escapar(activo.nombre)}"
                      data-categoria="${escapar(activo.categoria)}">
                <span class="fw-semibold">${escapar(activo.nombre)}</span>
                <small class="d-block text-muted">${escapar(activo.categoria)}</small>
              </button>`).join('')
          : '<div class="list-group-item text-muted">No se encontraron activos.</div>';
        resultados.classList.remove('d-none');
      } catch (error) {
        avisar(error.message, 'danger');
      }
    }, 250);
  });

  resultados.addEventListener('click', event => {
    const opcion = event.target.closest('[data-id]');
    if (!opcion) return;
    activoSeleccionado = {
      id: opcion.dataset.id,
      nombre: opcion.dataset.nombre,
      categoria: opcion.dataset.categoria,
    };
    buscar.value = activoSeleccionado.nombre;
    elegido.textContent = `${activoSeleccionado.nombre} · ${activoSeleccionado.categoria}`;
    botonAgregar.disabled = false;
    resultados.classList.add('d-none');
    dilusion.focus();
  });

  botonAgregar.addEventListener('click', async () => {
    if (!activoSeleccionado) return;
    botonAgregar.disabled = true;
    try {
      const data = await peticion(urls.agregar, {
        method: 'POST',
        body: JSON.stringify({
          cod_activo: activoSeleccionado.id,
          dilusion: dilusion.value.trim(),
        }),
      });
      buscar.value = '';
      dilusion.value = '';
      activoSeleccionado = null;
      elegido.textContent = 'Selecciona un resultado.';
      avisar(data.message);
      await cargarItems();
      buscar.focus();
    } catch (error) {
      botonAgregar.disabled = false;
      avisar(error.message, 'danger');
    }
  });

  tabla.addEventListener('click', async event => {
    const boton = event.target.closest('[data-eliminar]');
    if (!boton) return;
    boton.disabled = true;
    try {
      await peticion(`${urls.eliminarBase}/${boton.dataset.eliminar}`, { method: 'DELETE' });
      await cargarItems();
    } catch (error) {
      boton.disabled = false;
      avisar(error.message, 'danger');
    }
  });

  document.getElementById('limpiarActivos').addEventListener('click', async () => {
    try {
      await peticion(urls.limpiar, { method: 'DELETE' });
      await cargarItems();
    } catch (error) {
      avisar(error.message, 'danger');
    }
  });

  document.addEventListener('click', event => {
    if (!event.target.closest('.homeo-search')) resultados.classList.add('d-none');
  });

  const medicoInput = document.getElementById('medicoCotizacion');
  const cedulaMedico = document.getElementById('cedulaMedicoCotizacion');
  const resultadosMedicos = document.getElementById('resultadosMedicosCotizacion');
  const estadoMedico = document.getElementById('estadoMedicoCotizacion');

  medicoInput.addEventListener('input', () => {
    cedulaMedico.value = '';
    actualizarGuardar();
    estadoMedico.className = 'form-text';
    estadoMedico.textContent = 'Selecciona un médico con firma registrada.';
    clearTimeout(temporizadorMedico);
    const q = medicoInput.value.trim();
    if (!q) {
      resultadosMedicos.classList.add('d-none');
      return;
    }

    temporizadorMedico = setTimeout(async () => {
      try {
        const medicos = await peticion(`${urls.medicos}?q=${encodeURIComponent(q)}`);
        resultadosMedicos.innerHTML = medicos.length
          ? medicos.map(medico => `
              <button type="button" class="list-group-item list-group-item-action"
                      data-medico-cedula="${escapar(medico.cedula)}"
                      data-medico-nombre="${escapar(medico.label)}"
                      data-medico-firma="${medico.firma ? '1' : '0'}">
                <div class="d-flex justify-content-between gap-2">
                  <span>
                    <strong>${escapar(medico.label)}</strong>
                    <small class="d-block text-muted">Cédula: ${escapar(medico.cedula)}</small>
                  </span>
                  <span class="badge ${medico.firma ? 'text-bg-success' : 'text-bg-danger'} align-self-center">
                    ${medico.firma ? 'Con firma' : 'Sin firma'}
                  </span>
                </div>
              </button>`).join('')
          : '<div class="list-group-item text-muted">No se encontraron médicos.</div>';
        resultadosMedicos.classList.remove('d-none');
      } catch (error) {
        avisar(error.message, 'danger');
      }
    }, 250);
  });

  resultadosMedicos.addEventListener('click', event => {
    const opcion = event.target.closest('[data-medico-cedula]');
    if (!opcion) return;
    medicoInput.value = opcion.dataset.medicoNombre;
    resultadosMedicos.classList.add('d-none');

    if (opcion.dataset.medicoFirma !== '1') {
      cedulaMedico.value = '';
      estadoMedico.className = 'form-text text-danger';
      estadoMedico.textContent = 'Este médico no tiene firma registrada y no puede agregarse a la fórmula.';
      actualizarGuardar();
      return;
    }

    cedulaMedico.value = opcion.dataset.medicoCedula;
    estadoMedico.className = 'form-text text-success';
    estadoMedico.textContent = `Médico válido · Cédula: ${opcion.dataset.medicoCedula}`;
    actualizarGuardar();
  });

  document.addEventListener('click', event => {
    if (!event.target.closest('#medicoCotizacion') &&
        !event.target.closest('#resultadosMedicosCotizacion')) {
      resultadosMedicos.classList.add('d-none');
    }
  });

  cargarItems();
})();
</script>
@endpush

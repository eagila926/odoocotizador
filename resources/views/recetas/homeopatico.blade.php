@extends('layouts.app')

@section('title', 'Recetas Homeopático')

@push('styles')
<style>
  #resultados-medicos {
    max-height: 220px;
    overflow-y: auto;
    border: 1px solid #ddd;
    border-radius: 4px;
    display: none;
  }

  #resultados-medicos.activo {
    display: block;
  }

  .medico-item {
    padding: 8px;
    margin-bottom: 4px;
    cursor: pointer;
    border: 1px solid #e0e0e0;
    border-radius: 4px;
    transition: all 0.2s;
  }

  .medico-item:hover {
    background-color: #f5f5f5;
    border-color: #007bff;
  }
</style>
@endpush

@section('content')
<div class="container py-4">
  <div class="card">
    <div class="card-header bg-primary text-white">
      <h5 class="mb-0">Crear Recetas Homeopático</h5>
    </div>
    <div class="card-body">
      @if ($errors->any())
        <div class="alert alert-danger">
          <strong>Error:</strong>
          <ul class="mb-0">
            @foreach ($errors->all() as $error)
              <li>{{ $error }}</li>
            @endforeach
          </ul>
        </div>
      @endif

      <form id="formHomeopatico" method="POST" action="{{ route('recetas.homeopatico.store') }}">
        @csrf

        <div class="row">
          <div class="col-md-6 mb-4">
            <label for="medico" class="form-label fw-bold">Seleccionar Médico:</label>
            <input type="text"
                   class="form-control"
                   id="medico"
                   name="medico_search"
                   placeholder="Buscar médico por nombre o cédula"
                   autocomplete="off">
            <div id="resultados-medicos"></div>
            <input type="hidden" id="cedula_medico" name="cedula_medico" value="{{ old('cedula_medico') }}" required>
            <div id="doctor-info" class="mt-3" style="display: none;">
              <label for="medicoSeleccionadoDisplay" class="form-label">Médico seleccionado:</label>
              <input type="text" id="medicoSeleccionadoDisplay" class="form-control" readonly disabled>
              <div class="mt-2">
                <span id="firma-status" class="ms-2"></span>
              </div>
            </div>
          </div>

          <div class="col-md-6 mb-4">
            <label for="producto" class="form-label fw-bold">Producto homeopático:</label>
            <input type="text"
                   class="form-control"
                   id="producto"
                   name="producto"
                   value="{{ old('producto') }}"
                   placeholder="Nombre del producto"
                   required>
          </div>
        </div>

        <div class="row">
          <div class="col-md-8 mb-3">
            <label for="composicion" class="form-label fw-bold">Composición:</label>
            <textarea class="form-control"
                      id="composicion"
                      name="composicion"
                      rows="4"
                      placeholder="Detalle de la composición"
                      required>{{ old('composicion') }}</textarea>
          </div>

          <div class="col-md-4 mb-3">
            <label for="cantidad" class="form-label fw-bold">Cantidad solicitada:</label>
            <input type="number"
                   class="form-control"
                   id="cantidad"
                   name="cantidad"
                   value="{{ old('cantidad', 1) }}"
                   min="1"
                   step="1"
                   required>
            <div class="form-text" id="resumen-recetas"></div>
          </div>
        </div>

        <div class="row mt-3">
          <div class="col-md-4 mb-3">
            <label for="so" class="form-label">SO:</label>
            <input type="text"
                   class="form-control"
                   id="so"
                   name="so"
                   value="{{ old('so') }}"
                   placeholder="Número de SO"
                   required>
          </div>

          <div class="col-md-4 mb-3">
            <label for="paciente" class="form-label">Paciente (opcional):</label>
            <input type="text"
                   class="form-control"
                   id="paciente"
                   name="paciente"
                   value="{{ old('paciente') }}"
                   placeholder="Nombre del paciente">
          </div>

          <div class="col-md-4 mb-3">
            <label for="fecha" class="form-label">Fecha:</label>
            <input type="date"
                   class="form-control"
                   id="fecha"
                   name="fecha"
                   value="{{ old('fecha', date('Y-m-d')) }}"
                   required>
          </div>
        </div>

        <div class="row mt-4">
          <div class="col-12">
            <button type="submit" id="btnGenerar" class="btn btn-success btn-lg" disabled>
              <i class="bi bi-check-circle"></i> Generar Recetas
            </button>
            <a href="{{ route('home') }}" class="btn btn-secondary btn-lg">
              <i class="bi bi-x-circle"></i> Cancelar
            </a>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
  function buscarMedico(term) {
    const lista = document.getElementById('resultados-medicos');

    if (!term || term.trim() === '') {
      lista.innerHTML = '';
      lista.classList.remove('activo');
      return;
    }

    fetch('{{ route("medicos.buscar") }}?q=' + encodeURIComponent(term))
      .then(response => response.json())
      .then(data => {
        let html = '';

        if (!Array.isArray(data) || data.length === 0) {
          html = '<div style="padding: 10px;">No se encontraron médicos</div>';
        } else {
          data.forEach(medico => {
            const firmaText = medico.firma ? 'Con Firma' : 'Sin Firma';
            const firmaClass = medico.firma ? 'text-success' : 'text-danger';
            const medicoNombre = medico.label ?? medico.full_name ?? 'Sin nombre';
            const firmaValue = medico.firma ? 'true' : 'false';

            html += `<div class="medico-item" data-cedula="${medico.cedula}" data-nombre="${medicoNombre}" data-firma="${firmaValue}">
                      <div>
                        <strong>${medicoNombre}</strong>
                        <br>
                        <small class="text-muted">Cédula: ${medico.cedula}</small>
                        <small class="float-end ${firmaClass}">${firmaText}</small>
                      </div>
                      <div class="mt-2 text-end">
                        <button type="button" class="btn btn-sm btn-primary select-medico">Seleccionar</button>
                      </div>
                    </div>`;
          });
        }

        lista.innerHTML = html;
        lista.classList.add('activo');
      });
  }

  function seleccionarMedico(cedula, nombre, conFirma) {
    document.getElementById('cedula_medico').value = cedula;
    document.getElementById('medico').value = nombre;
    document.getElementById('medicoSeleccionadoDisplay').value = nombre;
    document.getElementById('doctor-info').style.display = 'block';
    document.getElementById('resultados-medicos').classList.remove('activo');
    document.getElementById('resultados-medicos').innerHTML = '';
    document.getElementById('firma-status').innerHTML = conFirma
      ? '<span class="badge bg-success">Con Firma</span>'
      : '<span class="badge bg-warning">Sin Firma</span>';

    validarFormulario();
  }

  function distribuirFrascos(cantidad) {
    const total = parseInt(cantidad, 10) || 0;

    if (total <= 0) {
      return [];
    }

    if (total <= 12) {
      return Array(total).fill(1);
    }

    const frascos = [];
    let restantes = total;

    while (restantes > 0) {
      frascos.push(Math.min(6, restantes));
      restantes -= 6;
    }

    return frascos;
  }

  function actualizarResumenRecetas() {
    const frascos = distribuirFrascos(document.getElementById('cantidad').value);
    const resumen = document.getElementById('resumen-recetas');

    if (frascos.length === 0) {
      resumen.textContent = '';
      return;
    }

    resumen.textContent = `${frascos.length} receta(s): ${frascos.join(' + ')} frasco(s)`;
  }

  function validarFormulario() {
    const medicoValido = document.getElementById('cedula_medico').value.trim() !== '';
    const soValido = document.getElementById('so').value.trim() !== '';
    const productoValido = document.getElementById('producto').value.trim() !== '';
    const composicionValida = document.getElementById('composicion').value.trim() !== '';
    const cantidadValida = parseInt(document.getElementById('cantidad').value, 10) > 0;

    document.getElementById('btnGenerar').disabled = !(medicoValido && soValido && productoValido && composicionValida && cantidadValida);
  }

  function limpiarFormularioHomeopatico() {
    document.getElementById('formHomeopatico').reset();
    document.getElementById('cedula_medico').value = '';
    document.getElementById('medico').value = '';
    document.getElementById('medicoSeleccionadoDisplay').value = '';
    document.getElementById('doctor-info').style.display = 'none';
    document.getElementById('firma-status').innerHTML = '';
    document.getElementById('resultados-medicos').innerHTML = '';
    document.getElementById('resultados-medicos').classList.remove('activo');
    document.getElementById('fecha').value = '{{ date('Y-m-d') }}';
    document.getElementById('cantidad').value = '1';
    actualizarResumenRecetas();
    validarFormulario();
  }

  document.addEventListener('DOMContentLoaded', function() {
    ['so', 'producto', 'composicion', 'cantidad'].forEach(id => {
      document.getElementById(id).addEventListener('input', function() {
        actualizarResumenRecetas();
        validarFormulario();
      });
    });

    document.getElementById('medico').addEventListener('input', function() {
      document.getElementById('cedula_medico').value = '';
      document.getElementById('doctor-info').style.display = 'none';
      buscarMedico(this.value);
      validarFormulario();
    });

    document.getElementById('resultados-medicos').addEventListener('click', function(e) {
      const boton = e.target.closest('.select-medico');
      if (!boton) return;

      const item = boton.closest('.medico-item');
      if (!item) return;

      seleccionarMedico(item.dataset.cedula, item.dataset.nombre, item.dataset.firma === 'true');
    });

    document.addEventListener('click', function(e) {
      if (e.target.id !== 'medico' && !e.target.closest('#resultados-medicos')) {
        document.getElementById('resultados-medicos').classList.remove('activo');
      }
    });

    document.getElementById('formHomeopatico').addEventListener('submit', function(e) {
      validarFormulario();

      if (document.getElementById('btnGenerar').disabled) {
        e.preventDefault();
        alert('Complete los datos obligatorios para generar las recetas.');
        return;
      }

      e.preventDefault();
      const form = this;
      const btnGenerar = document.getElementById('btnGenerar');
      btnGenerar.disabled = true;

      window.submitPdfFormWithPublicLinks(form, {
        defaultFilename: 'recetas-homeopatico.pdf',
        errorMessage: 'No se pudo generar las recetas.'
      }).then(() => {
        limpiarFormularioHomeopatico();
      }).catch(err => {
        alert(err.message || 'No se pudo generar las recetas.');
        validarFormulario();
      });
    });

    actualizarResumenRecetas();
    validarFormulario();
  });
</script>
@endsection

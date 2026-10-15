@extends('layouts.app')

@push('styles')
<style>
  .producto-item {
    padding: 8px;
    margin-bottom: 4px;
    cursor: pointer;
    border: 1px solid #e0e0e0;
    border-radius: 4px;
    transition: all 0.2s;
  }
  
  .producto-item:hover {
    background-color: #f5f5f5;
    border-color: #007bff;
  }

  .producto-seleccionado {
    background-color: #e3f2fd;
    border-color: #007bff;
  }

  #resultados-productos {
    max-height: 200px;
    overflow-y: auto;
    border: 1px solid #ddd;
    border-radius: 4px;
    display: none;
  }

  #resultados-productos.activo {
    display: block;
  }

  #resultados-medicos {
    max-height: 200px;
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

  .firma-badge {
    font-size: 0.75rem;
    padding: 2px 6px;
    margin-left: 4px;
  }

  .tabla-productos-seleccionados {
    margin-top: 20px;
  }

  .btn-eliminar-producto {
    padding: 2px 8px;
    font-size: 0.85rem;
  }
</style>
@endpush

@section('title', 'Crear Receta')

@section('content')
<div class="container py-4">
  <div class="card">
    <div class="card-header bg-primary text-white">
      <h5 class="mb-0">Crear Nueva Receta</h5>
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

      <form id="formReceta" method="POST" action="{{ route('recetas.store') }}">
        @csrf

        <div class="row">
          <!-- Selector de Médico -->
          <div class="col-md-6 mb-4">
            <label for="medico" class="form-label fw-bold">Seleccionar Médico:</label>
            <input type="text" 
                   class="form-control" 
                   id="medico" 
                   name="medico_search"
                   placeholder="Buscar médico por nombre o cédula" 
                   autocomplete="off"
                   onkeyup="buscarMedico(this.value)">
            <div id="resultados-medicos"></div>
            <input type="hidden" id="cedula_medico" name="cedula_medico" required>
            <div id="doctor-info" class="mt-3" style="display: none;">
              <label for="medicoSeleccionadoDisplay" class="form-label">Médico seleccionado:</label>
              <input type="text" id="medicoSeleccionadoDisplay" class="form-control" readonly disabled>
              <div class="mt-2">
                <span id="firma-status" class="ms-2"></span>
              </div>
            </div>
          </div>

          <!-- Selector de Productos -->
          <div class="col-md-6 mb-4">
            <label for="producto" class="form-label fw-bold">Agregar Productos:</label>
            <div class="input-group mb-2">
              <input type="text"
                     class="form-control"
                     id="producto"
                     placeholder="Escriba para buscar…"
                     autocomplete="off">
              <button type="button" class="btn btn-primary" id="btnAgregarProducto">Agregar</button>
            </div>
            <input type="hidden" id="producto_codigo" value="">
            <div id="resultados-productos" class="list-group mt-1" style="max-height: 220px; overflow:auto; display:none;"></div>
          </div>
        </div>

        <!-- Tabla de Productos Seleccionados -->
        <div class="tabla-productos-seleccionados">
          <label class="form-label fw-bold">Productos Seleccionados:</label>
          <div class="table-responsive">
            <table class="table table-sm table-hover" id="tablaProductos">
              <thead class="table-light">
                <tr>
                  <th>Nombre del Producto</th>
                  <th>Código</th>
                  <th style="width: 140px;">Cantidad</th>
                  <th class="text-end" style="width: 100px;">Acción</th>
                </tr>
              </thead>
              <tbody>
                <tr id="sin-productos" class="text-center text-muted">
                  <td colspan="4">No hay productos seleccionados</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Información adicional -->
        <div class="row mt-4">
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
                   value="{{ date('Y-m-d') }}">
          </div>
        </div>

        <!-- Botones de Acción -->
        <div class="row mt-4">
          <div class="col-12">
            <button type="submit" id="btnGenerar" class="btn btn-success btn-lg" disabled>
              <i class="bi bi-check-circle"></i> Generar Receta
            </button>
            <a href="{{ auth()->user()?->hasRole(['Admin']) ? route('recetas.index') : route('home') }}" class="btn btn-secondary btn-lg">
              <i class="bi bi-x-circle"></i> Cancelar
            </a>
          </div>
        </div>

        <!-- Campo oculto para almacenar productos seleccionados -->
        <input type="hidden" id="productosSeleccionados" name="productos_seleccionados" value="[]">

      </form>
    </div>
  </div>
</div>

<script>
  // Variables globales
  let productosSeleccionados = [];
  let medicoSeleccionado = null;
  let productoSeleccionado = null;

  function normalizarCodigoProducto(codigo) {
    return String(codigo ?? '').trim();
  }

  // Búsqueda de Médicos
  function buscarMedico(term) {
    if (!term || term.trim() === '') {
      document.getElementById('resultados-medicos').innerHTML = '';
      document.getElementById('resultados-medicos').classList.remove('activo');
      return;
    }

    fetch('{{ route("medicos.buscar") }}?q=' + encodeURIComponent(term))
      .then(response => response.json())
      .then(data => {
        let html = '';
        if (data.length === 0) {
          html = '<div style="padding: 10px; text-muted;">No se encontraron médicos</div>';
        } else {
          data.forEach(medico => {
            const firmaText = medico.firma ? '✓ Con Firma' : '✗ Sin Firma';
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
        document.getElementById('resultados-medicos').innerHTML = html;
        document.getElementById('resultados-medicos').classList.add('activo');
      });
  }

  function seleccionarMedico(cedula, nombre, conFirma) {
    document.getElementById('cedula_medico').value = cedula;
    medicoSeleccionado = { cedula, nombre, conFirma };
    
    document.getElementById('medico').value = nombre;
    document.getElementById('medicoSeleccionadoDisplay').value = nombre;
    document.getElementById('doctor-info').style.display = 'block';
    document.getElementById('resultados-medicos').classList.remove('activo');
    document.getElementById('resultados-medicos').innerHTML = '';
    
    const firmaStatus = document.getElementById('firma-status');
    firmaStatus.innerHTML = conFirma ? '<span class="badge bg-success">✓ Con Firma</span>' : '<span class="badge bg-warning">✗ Sin Firma</span>';
    
    validarFormulario();
  }

  // Búsqueda de Productos
  function buscarProducto(term) {
    const lista = document.getElementById('resultados-productos');
    if (!term || term.trim() === '') {
      lista.innerHTML = '';
      lista.style.display = 'none';
      return;
    }

    fetch('{{ route("recetas.buscarProductos") }}?q=' + encodeURIComponent(term), {
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
      .then(response => response.json())
      .then(data => {
        lista.innerHTML = '';
        if (!Array.isArray(data) || data.length === 0) {
          lista.innerHTML = '<button type="button" class="list-group-item list-group-item-action disabled">No se encontraron productos</button>';
          lista.style.display = 'block';
          return;
        }

        data.forEach(producto => {
          const item = document.createElement('a');
          item.href = '#';
          item.className = 'list-group-item list-group-item-action';
          item.innerHTML = `<div><strong>${producto.nombre}</strong><br><small class="text-muted">Código: ${producto.cod_product}</small></div>`;
          item.addEventListener('click', (event) => {
            event.preventDefault();
            const codProducto = normalizarCodigoProducto(producto.cod_product);
            const nombreProducto = String(producto.nombre ?? '').trim();
            productoSeleccionado = { cod_product: codProducto, nombre: nombreProducto };
            document.getElementById('producto').value = nombreProducto;
            document.getElementById('producto_codigo').value = codProducto;
            lista.innerHTML = '';
            lista.style.display = 'none';
            document.getElementById('producto').focus();
          });
          lista.appendChild(item);
        });
        lista.style.display = 'block';
      })
      .catch(() => {
        lista.innerHTML = '<button type="button" class="list-group-item list-group-item-action disabled">Error al buscar productos</button>';
        lista.style.display = 'block';
      });
  }

  function agregarProductoSeleccionado() {
    const codProducto = normalizarCodigoProducto(productoSeleccionado?.cod_product ?? document.getElementById('producto_codigo').value);
    const nombre = String(productoSeleccionado?.nombre ?? document.getElementById('producto').value).trim();

    if (!codProducto || !nombre) {
      alert('Seleccione un producto válido de la lista de sugerencias.');
      return;
    }

    const yaExiste = productosSeleccionados.some(p => normalizarCodigoProducto(p.cod_product) === codProducto);
    if (yaExiste) {
      alert('Este producto ya está agregado.');
      return;
    }

    productosSeleccionados.push({ cod_product: codProducto, nombre, cantidad: 1 });
    actualizarTablaProductos();
    validarFormulario();

    document.getElementById('producto_codigo').value = '';
    document.getElementById('producto').value = '';
    productoSeleccionado = null;
    const lista = document.getElementById('resultados-productos');
    lista.innerHTML = '';
    lista.style.display = 'none';
  }

  function eliminarProducto(codProducto) {
    const codigoAEliminar = normalizarCodigoProducto(codProducto);
    productosSeleccionados = productosSeleccionados.filter(p => normalizarCodigoProducto(p.cod_product) !== codigoAEliminar);
    actualizarTablaProductos();
    validarFormulario();
  }

  function actualizarCantidadProducto(index, cantidad) {
    const cantidadNormalizada = parseInt(cantidad, 10);
    productosSeleccionados[index].cantidad = Number.isInteger(cantidadNormalizada) && cantidadNormalizada > 0 ? cantidadNormalizada : 1;
    document.getElementById('productosSeleccionados').value = JSON.stringify(productosSeleccionados);
  }

  function actualizarTablaProductos() {
    const tbody = document.querySelector('#tablaProductos tbody');
    
    if (productosSeleccionados.length === 0) {
      tbody.innerHTML = '<tr id="sin-productos" class="text-center text-muted"><td colspan="4">No hay productos seleccionados</td></tr>';
    } else {
      let html = '';
      productosSeleccionados.forEach((producto, index) => {
        const codProducto = normalizarCodigoProducto(producto.cod_product);
        const cantidad = parseInt(producto.cantidad, 10) || 1;
        html += `<tr>
                  <td>${producto.nombre}</td>
                  <td>${codProducto}</td>
                  <td>
                    <input type="number"
                           class="form-control form-control-sm"
                           min="1"
                           step="1"
                           value="${cantidad}"
                           onchange="actualizarCantidadProducto(${index}, this.value)"
                           oninput="actualizarCantidadProducto(${index}, this.value)">
                  </td>
                  <td class="text-end">
                    <button type="button" class="btn btn-sm btn-danger btn-eliminar-producto" onclick="eliminarProducto('${codProducto}')">
                      <i class="bi bi-trash"></i> Eliminar
                    </button>
                  </td>
                </tr>`;
      });
      tbody.innerHTML = html;
    }
    
    // Actualizar el campo oculto
    document.getElementById('productosSeleccionados').value = JSON.stringify(productosSeleccionados);
  }

  function validarFormulario() {
    const medicoValido = document.getElementById('cedula_medico').value !== '';
    const productosValidos = productosSeleccionados.length > 0;
    const soValido = document.getElementById('so').value.trim() !== '';
    const btnGenerar = document.getElementById('btnGenerar');
    
    if (medicoValido && productosValidos && soValido) {
      btnGenerar.disabled = false;
    } else {
      btnGenerar.disabled = true;
    }
  }

  document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('btnAgregarProducto').addEventListener('click', function() {
      agregarProductoSeleccionado();
    });

    document.getElementById('producto').addEventListener('input', function() {
      document.getElementById('producto_codigo').value = '';
      productoSeleccionado = null;
      buscarProducto(this.value);
    });

    document.getElementById('so').addEventListener('input', function() {
      validarFormulario();
    });

    document.getElementById('formReceta').addEventListener('submit', function(e) {
      const medicoValido = document.getElementById('cedula_medico').value !== '';
      const productosValidos = productosSeleccionados.length > 0;
      const soValido = document.getElementById('so').value.trim() !== '';

      if (!medicoValido) {
        e.preventDefault();
        alert('Debe seleccionar un médico');
        return false;
      }

      if (!productosValidos) {
        e.preventDefault();
        alert('Debe seleccionar al menos un producto');
        return false;
      }

      if (!soValido) {
        e.preventDefault();
        alert('Debe ingresar el SO');
        return false;
      }

      e.preventDefault();
      const form = this;
      const btnGenerar = document.getElementById('btnGenerar');
      btnGenerar.disabled = true;

      window.submitPdfFormWithPublicLinks(form, {
        defaultFilename: 'receta.pdf',
        errorMessage: 'No se pudo generar la receta.'
      }).then(() => {
        productosSeleccionados = [];
        medicoSeleccionado = null;
        productoSeleccionado = null;
        form.reset();
        document.getElementById('cedula_medico').value = '';
        document.getElementById('productosSeleccionados').value = '[]';
        document.getElementById('doctor-info').style.display = 'none';
        document.getElementById('firma-status').innerHTML = '';
        actualizarTablaProductos();
        validarFormulario();
      }).catch(err => {
        alert(err.message || 'No se pudo generar la receta.');
        validarFormulario();
      });
    });

    document.getElementById('resultados-medicos').addEventListener('click', function(e) {
      const boton = e.target.closest('.select-medico');
      if (!boton) return;

      const item = boton.closest('.medico-item');
      if (!item) return;

      const cedula = item.dataset.cedula;
      const nombre = item.dataset.nombre;
      const conFirma = item.dataset.firma === 'true';
      seleccionarMedico(cedula, nombre, conFirma);
    });

    document.addEventListener('click', function(e) {
      if (e.target.id !== 'medico' && !e.target.closest('#resultados-medicos')) {
        document.getElementById('resultados-medicos').classList.remove('activo');
      }
      if (e.target.id !== 'producto' && !e.target.closest('#resultados-productos')) {
        const lista = document.getElementById('resultados-productos');
        lista.style.display = 'none';
      }
    });
  });
</script>
@endsection

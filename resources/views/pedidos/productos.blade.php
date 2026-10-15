@extends('layouts.app')
@section('title', 'Pedidos | Productos')

@section('content')
<div class="card">
  <div class="card-body">
    <h5 class="card-title mb-3">Ingrese el nombre de la fórmula</h5>

    {{-- Formulario superior --}}
    <form method="POST" action="{{ route('pedidos.productos.agregar') }}" autocomplete="off" id="formAgregar">
      @csrf
      <div class="row g-3 align-items-end">
        <div class="col-lg-6">
          <label class="form-label">Producto:</label>
          <input type="text" class="form-control" id="buscador" placeholder="Escriba para buscar…">
          <input type="hidden" name="producto_codigo" id="producto_id">
          {{-- sugerencias --}}
          <div id="sugerencias" class="list-group mt-1" style="max-height:200px; overflow:auto; display:none;"></div>
        </div>

        <div class="col-lg-2">
          <label class="form-label">Cantidad:</label>
          <input type="number" step="1" min="1" class="form-control" name="cantidad" id="cantidad" required>
        </div>

        <div class="col-lg-2">
          <label class="form-label">Promoción:</label>
          <select name="promocion" id="promocion" class="form-select">
            <option value="NO" selected>NO</option>
            <option value="SI">SÍ</option>
          </select>
        </div>

        <div class="col-lg-2 text-end">
          <button class="btn btn-primary w-100" type="submit">Añadir</button>
        </div>

        <div class="col-12">
          <label class="form-label">Detalle:</label>
          <input type="text" name="observacion" id="observacion" class="form-control" placeholder="Ingrese una observación para el producto">
        </div>
      </div>
    </form>

    {{-- Botón Finalizar (placeholder) --}}
    <div class="text-end mt-3">
      <form method="POST" action="{{ route('pedidos.finalizar') }}"
            onsubmit="return confirm('¿Desea finalizar y guardar el pedido?');">
        @csrf
        <button class="btn btn-success">Finalizar Pedido</button>
      </form>
    </div>
  </div>
</div>

@if (session('download_url'))
  <a id="autoDownload" href="{{ session('download_url') }}" class="d-none" target="_blank" rel="noopener">Descargar</a>
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const a = document.getElementById('autoDownload');
      if (a) a.click(); // dispara descarga en nueva pestaña
    });
  </script>
@endif

{{-- Tabla de seleccionados --}}
<div class="card mt-3">
  <div class="card-body">
    <div class="d-flex justify-content-between align-items-center mb-2">
      <h6 class="mb-0">Productos Seleccionados</h6>
      <div class="fw-semibold">Total Pedido: ${{ number_format($total, 2) }}</div>
    </div>

    <div class="table-responsive">
      <table class="table table-sm align-middle">
        <thead class="table-light">
          <tr>
            <th>Producto</th>
            <th>Cantidad</th>
            <th>Línea</th>
            <th>Detalle</th>
            <th class="text-end">Precio Unidad</th>
            <th class="text-end">Subtotal</th>
            <th class="text-center">Eliminar</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($pedido as $it)
            <tr>
              <td>
                <div class="fw-semibold">{{ $it->nombre }}</div>
                <small class="text-muted">{{ $it->cod_product }}</small>
                @if($it->promocion === 'SI')
                  <span class="badge text-bg-info ms-1">Promo</span>
                @endif
              </td>
              <td>{{ rtrim(rtrim(number_format($it->cantidad, 2, '.', ''), '0'), '.') }}</td>
              <td>{{ $it->categoria }}</td>
              <td>{{ $it->observacion }}</td>
              <td class="text-end">${{ number_format($it->precio, 2) }}</td>
              <td class="text-end">${{ number_format($it->subtotal, 2) }}</td>
              <td class="text-center">
                <form method="POST" action="{{ route('pedidos.productos.eliminar', $it->id) }}">
                  @csrf @method('DELETE')
                  <button class="btn btn-sm btn-outline-danger" onclick="return confirm('¿Eliminar?')">
                    <i class="bi bi-trash"></i>
                  </button>
                </form>
              </td>
            </tr>
          @empty
            <tr><td colspan="7" class="text-center text-muted">Sin productos aún</td></tr>
          @endforelse
        </tbody>

      </table>
    </div>
  </div>
</div>
@endsection

@push('styles')
<style>
  #sugerencias .list-group-item { cursor: pointer; }
  #sugerencias .badge { font-size: .70rem; }
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
  const buscador = document.getElementById('buscador');
  const lista    = document.getElementById('sugerencias');
  const inputId  = document.getElementById('producto_id');
  const cantidad = document.getElementById('cantidad');

  let timer = null;

  function limpiarSugerencias() {
    lista.innerHTML = '';
    lista.style.display = 'none';
  }

  buscador.addEventListener('input', function() {
    inputId.value = '';
    const q = this.value.trim();
    if (timer) clearTimeout(timer);

    if (q.length < 2) { limpiarSugerencias(); return; }

    timer = setTimeout(() => {
      fetch(`{{ route('pedidos.productos.buscar') }}?q=${encodeURIComponent(q)}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      })
      .then(r => r.json())
      .then(rows => {
        if (!rows.length) { limpiarSugerencias(); return; }
        lista.innerHTML = '';
        rows.forEach(p => {
          const item = document.createElement('a');
          item.className = 'list-group-item list-group-item-action d-flex justify-content-between align-items-center';
          item.innerHTML = `
            <span>
              <span class="fw-semibold">${p.nombre}</span>
              <small class="text-muted ms-2">${p.cod_product}</small>
            </span>
            <span>
              <span class="badge text-bg-secondary me-2">${p.categoria}</span>
              <span class="badge text-bg-light">$${Number(p.precio).toFixed(2)}</span>
            </span>`;
          item.addEventListener('click', () => {
            buscador.value = p.nombre;
            inputId.value  = p.cod_product;
            limpiarSugerencias();
            if (!cantidad.value) cantidad.value = 1;
            buscador.focus();
          });
          lista.appendChild(item);
        });
        lista.style.display = 'block';
      })
      .catch(() => limpiarSugerencias());
    }, 220);
  });

  // Cerrar la lista si se hace click fuera
  document.addEventListener('click', (e) => {
    if (!lista.contains(e.target) && e.target !== buscador) limpiarSugerencias();
  });

  // Validación simple al enviar (debe haber producto_id)
  document.getElementById('formAgregar').addEventListener('submit', (e) => {
    if (!inputId.value) {
      e.preventDefault();
      alert('Seleccione un producto de la lista de sugerencias.');
    }
  });
});
</script>
@endpush

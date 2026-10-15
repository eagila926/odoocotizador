@extends('layouts.app')
@section('title', 'Pedidos | Fórmulas')

@section('content')
<div class="card">
  <div class="card-body">
    <h5 class="card-title mb-3">Ingrese el nombre de la fórmula</h5>

    {{-- Formulario superior --}}
    <form method="POST" action="{{ route('pedidos.formulas.agregar') }}" autocomplete="off" id="formAgregar">
      @csrf
      <div class="row g-3 align-items-end">

        {{-- BUSCADOR --}}
        <div class="col-lg-6">
          <label class="form-label">Fórmula:</label>
          <input type="text" class="form-control" id="buscador" placeholder="Escriba para buscar…">
          <input type="hidden" name="formula_codigo" id="formula_id">
          
          {{-- sugerencias --}}
          <div id="sugerencias" class="list-group mt-1" style="max-height:200px; overflow:auto; display:none;"></div>
        </div>

        {{-- Cantidad --}}
        <div class="col-lg-2">
          <label class="form-label">Cantidad:</label>
          <input type="number" step="1" min="1" class="form-control" name="cantidad" id="cantidad" required>
        </div>

        {{-- Promoción --}}
        <div class="col-lg-2">
          <label class="form-label">Promoción:</label>
          <select name="promocion" id="promocion" class="form-select">
            <option value="NO" selected>NO</option>
            <option value="SI">SÍ</option>
          </select>
        </div>

        {{-- Añadir --}}
        <div class="col-lg-2 text-end">
          <button class="btn btn-primary w-100" type="submit">Añadir</button>
        </div>

        {{-- Observación --}}
        <div class="col-12">
          <label class="form-label">Detalle:</label>
          <input type="text" name="observacion" id="observacion"
                 class="form-control" placeholder="Ingrese un detalle u observación">
        </div>
      </div>
    </form>

    {{-- Botón Finalizar --}}
    <div class="text-end mt-3">
      <form method="POST" action="{{ route('pedidos.formulas.finalizar') }}"
            onsubmit="return confirm('¿Desea finalizar y guardar el pedido?');">
        @csrf
        <button class="btn btn-success">Finalizar Pedido</button>
      </form>
    </div>

  </div>
</div>

{{-- Descarga automática --}}
@if (session('download_url'))
  <a id="autoDownload" href="{{ session('download_url') }}" class="d-none" target="_blank" rel="noopener">Descargar</a>
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const a = document.getElementById('autoDownload');
      if (a) a.click();
    });
  </script>
@endif


{{-- Tabla de seleccionados --}}
<div class="card mt-3">
  <div class="card-body">
    <div class="d-flex justify-content-between align-items-center mb-2">
      <h6 class="mb-0">Fórmulas Seleccionadas</h6>
      <div class="fw-semibold">Total Pedido: ${{ number_format($total, 2) }}</div>
    </div>

    <div class="table-responsive">
      <table class="table table-sm align-middle">
        <thead class="table-light">
          <tr>
            <th>Fórmula</th>
            <th>Cantidad</th>
            <th>Categoría</th>
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
                <div class="fw-semibold">{{ $it->nombre_formula }}</div>
                <small class="text-muted">{{ $it->cod_formula }}</small>
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
                <form method="POST" action="{{ route('pedidos.formulas.eliminar', $it->id) }}">
                  @csrf @method('DELETE')
                  <button class="btn btn-sm btn-outline-danger" onclick="return confirm('¿Eliminar esta fórmula?')">
                    <i class="bi bi-trash"></i>
                  </button>
                </form>
              </td>
            </tr>

          @empty
            <tr><td colspan="6" class="text-center text-muted">Sin fórmulas aún</td></tr>
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
  const inputId  = document.getElementById('formula_id');
  const cantidad = document.getElementById('cantidad');

  let timer = null;

  function limpiarSugerencias() {
    lista.innerHTML = '';
    lista.style.display = 'none';
  }

  // Autocompletar
  buscador.addEventListener('input', function() {
    inputId.value = '';
    const q = this.value.trim();
    if (timer) clearTimeout(timer);

    if (q.length < 2) { limpiarSugerencias(); return; }

    timer = setTimeout(() => {
      fetch(`{{ route('pedidos.formulas.buscar') }}?q=${encodeURIComponent(q)}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      })
      .then(r => r.json())
      .then(rows => {
        if (!rows.length) { limpiarSugerencias(); return; }

        lista.innerHTML = '';
        rows.forEach(f => {
          const item = document.createElement('a');
          item.className = 'list-group-item list-group-item-action';

          item.innerHTML = `
            <div class="fw-semibold">${f.nombre_etiqueta}</div>
            <small class="text-muted">${f.codigo}</small>
          `;

          item.addEventListener('click', () => {
            buscador.value = f.nombre_etiqueta;
            inputId.value  = f.codigo;
            limpiarSugerencias();
            if (!cantidad.value) cantidad.value = 1;
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
    if (!lista.contains(e.target) && e.target !== buscador) {
      limpiarSugerencias();
    }
  });

  // Validación: debe seleccionar fórmula
  document.getElementById('formAgregar').addEventListener('submit', (e) => {
    if (!inputId.value) {
      e.preventDefault();
      alert('Seleccione una fórmula de la lista de sugerencias.');
    }
  });
});
</script>
@endpush

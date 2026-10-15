@extends('layouts.app')
@section('title','Resumen – Cápsulas | Ortomed')

@section('content')

@php
    $puedeVerDetalles = auth()->check() && in_array(auth()->user()->rol, ['Admin', 'Laboratorio'], true);

    // SWITCHES (dejar en false para deshabilitar)
    $habilitarEdicionPrecios    = false;  // cambia a true si luego quieres permitir editar
    $habilitarSeleccionCapsula  = false;  // cambia a true si luego quieres permitir elegir 00/0/auto

    // Precios base (si no vienen del controller, calcula)
    $precio_med_v = isset($precio_med) ? (float)$precio_med : max(12, (float)($totalGeneral ?? 0) * 30);
    $precio_dis_v = isset($precio_dis) ? (float)$precio_dis : $precio_med_v * 0.65;
    $precio_pvp_v = isset($precio_pvp) ? (float)$precio_pvp : $precio_med_v * (4/3); // +33.333...%
@endphp

<div class="card">
  <div class="card-body">
    <h4 class="mb-3">Resumen de Fórmula en Cápsulas</h4>

    <form action="{{ route('formulas.guardar') }}" method="POST" class="mb-3">
      @csrf

      <div class="row g-2 align-items-end">
        <div class="col-12 col-md-4">
          <label class="form-label mb-1">Nombre etiqueta</label>
          <input type="text" name="nombre_etiqueta" class="form-control"
                 value="{{ old('nombre_etiqueta') }}"
                 placeholder="Ej. SUEÑO PROFUNDO" autocomplete="off">
        </div>

        <div class="col-12 col-md-4">
          <label class="form-label mb-1">Médico</label>
          <input type="text" name="medico" id="medico" class="form-control"
                 value="{{ old('medico') }}"
                 placeholder="Buscar médico (min. 2 letras)" autocomplete="off">
        </div>

        <div class="col-12 col-md-4">
          <label class="form-label mb-1">Tipo de cápsula</label>
          <select id="capsulaSelect" class="form-select" {{ $habilitarSeleccionCapsula ? '' : 'disabled' }}>
            <option value="auto" {{ ($capsulaReq ?? 'auto') === 'auto' ? 'selected' : '' }}>
              Automático (menor estearato)
            </option>
            <option value="00" {{ ($capsulaReq ?? 'auto') === '00' ? 'selected' : '' }}>
              CAP 00
            </option>
            <option value="0" {{ ($capsulaReq ?? 'auto') === '0' ? 'selected' : '' }}>
              CAP 0
            </option>
          </select>

          {{-- Se envía al guardar() aunque el select esté disabled --}}
          <input type="hidden" name="capsula" id="capsulaHidden" value="{{ $capsulaReq ?? 'auto' }}">

          <input type="hidden" name="cod_formula" value="{{ $codFormula ?? '' }}">
        </div>
      </div>

      {{-- Hidden que se envían al guardar --}}
      <input type="hidden" name="precio_medico"       value="{{ number_format($precio_med_v, 2, '.', '') }}">
      <input type="hidden" name="precio_publico"      value="{{ number_format($precio_pvp_v, 2, '.', '') }}">
      <input type="hidden" name="precio_distribuidor" value="{{ number_format($precio_dis_v, 2, '.', '') }}">
      <input type="hidden" name="tomas_diarias"       value="{{ $capsDiaElegida ?? 0 }}">

      <div class="mt-3 d-flex gap-2">
        <button type="submit" class="btn btn-primary">Guardar fórmula</button>
        <a href="{{ route('formulas.nuevas') }}" class="btn btn-outline-secondary">Volver</a>
      </div>
    </form>

    <div class="alert alert-info mb-3">
      Tratamiento: <strong>30 días</strong><br>
      Cápsulas por día: <strong>{{ $capsDiaElegida ?? 0 }}</strong><br>
      Total 30 días: <strong>{{ $totalCapsElegida ?? 0 }}</strong>
    </div>

    <div class="table-responsive">
      <table class="table" id="tabla-resumen">
        <thead>
          <tr>
            <th class="d-none d-md-table-cell">#</th>
            <th class="d-none d-md-table-cell">Cod. Odoo</th>
            <th>Activo</th>
            <th class="text-end">Cant.</th>
            <th>Unidad</th>
            <th class="text-end">Equiv. (mg/día)</th>
          </tr>
        </thead>
        <tbody>
          @foreach($rows as $i => $r)
            @php
              $cantidad = $r['cantidad'] ?? null;
              $unidad   = $r['unidad'] ?? '—';
              $mg       = $r['mg'] ?? null; // puede venir null en cápsulas/pastillero
            @endphp
            <tr>
              <td class="d-none d-md-table-cell">{{ $i+1 }}</td>
              <td class="d-none d-md-table-cell">{{ $r['cod_odoo'] ?? '—' }}</td>
              <td>{{ $r['activo'] ?? '—' }}</td>
              <td class="text-end">
                @if(!is_null($cantidad))
                  {{ rtrim(rtrim(number_format((float)$cantidad, 3), '0'), '.') }}
                @else
                  —
                @endif
              </td>
              <td>{{ $unidad }}</td>
              <td class="text-end">
                @if(!is_null($mg))
                  {{ number_format((float)$mg, 2) }}
                @else
                  —
                @endif
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>

  </div>
</div>

{{-- ===== Precios de la fórmula ===== --}}
<div class="card mt-3">
  <div class="card-body">
    <h4 class="mb-3">Precio de la Fórmula</h4>

    <div class="row g-2">
      <div class="col-12 col-md-4">
        <label class="form-label">Precio Médico</label>
        <input type="number" step="0.01" min="0" id="precio_med_input"
               class="form-control"
               value="{{ number_format($precio_med_v, 2, '.', '') }}"
               {{ $habilitarEdicionPrecios ? '' : 'readonly' }}>
      </div>

      <div class="col-12 col-md-4">
        <label class="form-label">Precio Público</label>
        <input type="text" id="precio_pvp_out" class="form-control" readonly
               value="{{ number_format($precio_pvp_v, 2, '.', '') }}">
      </div>

      <div class="col-12 col-md-4">
        <label class="form-label">Precio Distribuidor</label>
        <input type="text" id="precio_dis_out" class="form-control" readonly
               value="{{ number_format($precio_dis_v, 2, '.', '') }}">
      </div>
    </div>

  </div>
</div>

{{-- ===== Detalle de la fórmula (solo Admin/Lab) ===== --}}
@if($puedeVerDetalles)
<div class="card mt-3">
  <div class="card-body">
    <h4 class="mb-3">Detalle de precios</h4>

    <div class="table-responsive">
      <table class="table" id="tabla-detalle">
        <thead>
          <tr>
            <th class="d-none d-md-table-cell">#</th>
            <th class="d-none d-md-table-cell">Cod. Odoo</th>
            <th>Activo</th>
            <th class="text-end">Cant.</th>
            <th>Unidad</th>
            <th class="text-end">Cant. total</th>
            <th class="text-end">Valor costo</th>
            <th class="text-end">Factor venta</th>
            <th class="text-end">Subtotal</th>
          </tr>
        </thead>
        <tbody>
          @php $totalGeneralDetalle = 0; @endphp
          @foreach($rows as $i => $r)
            @php
              $totalGeneralDetalle += (float)($r['subtotal'] ?? 0);
              $isEsterato = ((int)($r['cod_odoo'] ?? 0) === 1101);
              $isCaps     = in_array((int)($r['cod_odoo'] ?? 0), [1077,1078], true);
            @endphp
            <tr class="{{ ($isEsterato || $isCaps) ? 'table-light' : '' }}">
              <td class="d-none d-md-table-cell">{{ $i+1 }}</td>
              <td class="d-none d-md-table-cell">{{ $r['cod_odoo'] ?? '—' }}</td>
              <td>
                @if($isEsterato)
                  <em>{{ $r['activo'] ?? '—' }}</em>
                @elseif($isCaps)
                  <strong>{{ $r['activo'] ?? '—' }}</strong>
                @else
                  {{ $r['activo'] ?? '—' }}
                @endif
              </td>
              <td class="text-end">
                @if(!is_null($r['cantidad'] ?? null))
                  {{ rtrim(rtrim(number_format((float)$r['cantidad'], 3), '0'), '.') }}
                @else
                  —
                @endif
              </td>
              <td>{{ $r['unidad'] ?? '—' }}</td>
              <td class="text-end">
                @if(!is_null($r['cantidad_total'] ?? null))
                  {{ rtrim(rtrim(number_format((float)$r['cantidad_total'], 3), '0'), '.') }}
                @else
                  —
                @endif
              </td>
              <td class="text-end">
                @if(isset($r['valor_costo']))
                  {{ rtrim(rtrim(number_format((float)$r['valor_costo'], 8), '0'), '.') }}
                @else
                  —
                @endif
              </td>
              <td class="text-end">
                @if(isset($r['factor_venta']))
                  {{ rtrim(rtrim(number_format((float)$r['factor_venta'], 4), '0'), '.') }}
                @else
                  —
                @endif
              </td>
              <td class="text-end">{{ number_format((float)($r['subtotal'] ?? 0), 2) }}</td>
            </tr>
          @endforeach
        </tbody>
        <tfoot>
          <tr>
            <th colspan="8" class="text-end">Total fórmula</th>
            <th class="text-end">{{ number_format($totalGeneralDetalle, 2) }}</th>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>
</div>
@endif

{{-- ===== Detalle de pesaje (solo Admin/Lab) ===== --}}
@if($puedeVerDetalles)
<div class="card mt-3">
  <div class="card-body">
    <h4 class="mb-3">Detalle de pesaje</h4>

    <div class="table-responsive">
      <table class="table" id="tabla-pesaje">
        <thead>
          <tr>
            <th class="d-none d-md-table-cell">#</th>
            <th class="d-none d-md-table-cell">Cod. Odoo</th>
            <th>Activo</th>
            <th class="text-end">Cant.</th>
            <th>Unidad</th>
            <th class="text-end">Cant. total (g/día / #caps)</th>
            <th class="text-end">Vol. (ml/día)</th>
            <th class="text-end">Vol. (ml/mes)</th>
            <th class="text-end">Masa f Mes (g)</th>
          </tr>
        </thead>
        <tbody>
          @foreach($rows as $i => $r)
            @php
              $cod = (int)($r['cod_odoo'] ?? 0);
              $isEsterato = ($cod === 1101);
              $isCaps     = in_array($cod, [1077, 1078], true);
              $isPast     = in_array($cod, [1219, 1220], true);
            @endphp
            <tr class="{{ ($isEsterato || $isCaps) ? 'table-light' : '' }}">
              <td class="d-none d-md-table-cell">{{ $i+1 }}</td>
              <td class="d-none d-md-table-cell">{{ $r['cod_odoo'] ?? '—' }}</td>
              <td>
                @if($isEsterato)
                  <em>{{ $r['activo'] ?? '—' }}</em>
                @elseif($isCaps)
                  <strong>{{ $r['activo'] ?? '—' }}</strong>
                @elseif($isPast)
                  <strong><em>{{ $r['activo'] ?? '—' }}</em></strong>
                @else
                  {{ $r['activo'] ?? '—' }}
                @endif
              </td>

              <td class="text-end">
                @if(!is_null($r['cantidad'] ?? null))
                  {{ rtrim(rtrim(number_format((float)$r['cantidad'], 3), '0'), '.') }}
                @else
                  —
                @endif
              </td>
              <td>{{ $r['unidad'] ?? '—' }}</td>

              <td class="text-end">
                @if(!is_null($r['cant_total_pesaje'] ?? null))
                  {{ rtrim(rtrim(number_format((float)$r['cant_total_pesaje'], 4), '0'), '.') }}
                @else
                  —
                @endif
              </td>

              <td class="text-end">
                @if(!is_null($r['vol_ml'] ?? null))
                  {{ rtrim(rtrim(number_format((float)$r['vol_ml'], 4), '0'), '.') }}
                @else
                  —
                @endif
              </td>

              <td class="text-end">
                @if(!is_null($r['vol_ml'] ?? null))
                  {{ rtrim(rtrim(number_format(((float)$r['vol_ml'] * 30), 3), '0'), '.') }}
                @else
                  —
                @endif
              </td>

              <td class="text-end">
                @if(!is_null($r['masa_mes'] ?? null))
                  {{ rtrim(rtrim(number_format((float)$r['masa_mes'], 4), '0'), '.') }}
                @else
                  —
                @endif
              </td>
            </tr>
          @endforeach
        </tbody>

        <tfoot>
          <tr>
            <th colspan="6" class="text-end">Totales</th>
            <th class="text-end">{{ number_format($totalVolMl ?? 0, 3) }}</th>
            <th class="text-end">{{ number_format(($totalVolMl ?? 0) * 30, 3) }}</th>
            <th class="text-end">{{ number_format($totalMasaMes ?? 0, 3) }}</th>
          </tr>
        </tfoot>
      </table>
    </div>

    <div class="mt-3 p-3 border rounded bg-light">
      <div><strong>CAP 00</strong> → Caps/día: {{ $capsDia95 ?? 0 }}, Total 30d: {{ $totalCaps95 ?? 0 }}, Capacidad total: {{ number_format($capacidadTotal95 ?? 0, 3) }} ml, Esterato: {{ number_format($esterato95 ?? 0, 3) }} mg</div>
      <div><strong>CAP 0</strong> → Caps/día: {{ $capsDia68 ?? 0 }}, Total 30d: {{ $totalCaps68 ?? 0 }}, Capacidad total: {{ number_format($capacidadTotal68 ?? 0, 3) }} ml, Esterato: {{ number_format($esterato68 ?? 0, 3) }} mg</div>
      <hr>
      <div><strong>Cápsula elegida:</strong> {{ $capsulaElegida ?? '—' }} |
        <strong>Caps/día:</strong> {{ $capsDiaElegida ?? 0 }} |
        <strong>Total 30d:</strong> {{ $totalCapsElegida ?? 0 }} |
        <strong>Esterato final:</strong> {{ number_format($esteratoFinal ?? 0, 3) }} mg
      </div>
    </div>
  </div>
</div>
@endif

{{-- ========== Scripts (SIN duplicados) ========== --}}
<script>
document.addEventListener("DOMContentLoaded", () => {
  const ENABLE_CAPS_SELECT = @json($habilitarSeleccionCapsula);
  const ENABLE_PRICE_EDIT  = @json($habilitarEdicionPrecios);

  // 1) Selector cápsula (solo si está habilitado)
  const capsSel = document.getElementById('capsulaSelect');
  const capsHid = document.getElementById('capsulaHidden');

  if (capsSel && ENABLE_CAPS_SELECT) {
    capsSel.addEventListener('change', () => {
      const val = capsSel.value || 'auto';
      if (capsHid) capsHid.value = val;

      const url = new URL(window.location.href);
      url.searchParams.set('capsula', val);
      window.location.href = url.toString();
    });
  }

  // 2) Precios (si edición está deshabilitada, igual sincroniza hidden/out una vez)
  const medInput = document.getElementById('precio_med_input');
  const pvpOut   = document.getElementById('precio_pvp_out');
  const disOut   = document.getElementById('precio_dis_out');

  const hidMed = document.querySelector("input[name='precio_medico']");
  const hidPvp = document.querySelector("input[name='precio_publico']");
  const hidDis = document.querySelector("input[name='precio_distribuidor']");

  const round2 = (n) => (Math.round((n + Number.EPSILON) * 100) / 100).toFixed(2);

  const recalcPrecios = () => {
    if (!medInput) return;

    let med = parseFloat(medInput.value || '0');
    if (isNaN(med)) med = 0;

    // Piso $12
    if (med > 0 && med < 12) med = 12;

    const dis = med * 0.65;
    const pvp = med * (4 / 3);

    if (ENABLE_PRICE_EDIT) {
      medInput.value = round2(med);
    }

    if (disOut) disOut.value = round2(dis);
    if (pvpOut) pvpOut.value = round2(pvp);

    if (hidMed) hidMed.value = round2(med);
    if (hidDis) hidDis.value = round2(dis);
    if (hidPvp) hidPvp.value = round2(pvp);
  };

  if (medInput) {
    if (ENABLE_PRICE_EDIT) {
      medInput.addEventListener('input', recalcPrecios);
    }
    recalcPrecios();
  }

  // 3) Mayúsculas (sin romper si algún input no existe)
  const soloMayusculas = (input, permitirTodo = false) => {
    if (!input) return;
    input.addEventListener("input", () => {
      let valor = (input.value || '')
        .normalize("NFD").replace(/[\u0300-\u036f]/g, "")
        .toUpperCase();

      if (!permitirTodo) {
        valor = valor.replace(/[^A-Z\s]/g, "");
      }

      input.value = valor;
    });
  };

  soloMayusculas(document.querySelector("[name='nombre_etiqueta']"), true);
  soloMayusculas(document.querySelector("[name='medico']"));
  const pac = document.querySelector("[name='paciente']");
  if (pac) soloMayusculas(pac);
});
</script>

<script>
$(function() {
  $("#medico").autocomplete({
    source: function(request, response) {
      $.ajax({
        url: "{{ route('medicos.buscar') }}",
        data: { q: request.term },
        success: function(data) {
          response(data);
        }
      });
    },
    minLength: 2
  });
});
</script>

@endsection

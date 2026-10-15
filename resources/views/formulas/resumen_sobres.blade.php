@extends('layouts.app')
@section('title','Resumen – Sobres | Ortomed')

@section('content')

@php
  // SWITCH: deshabilitar/habilitar edición de precio médico
  $habilitarEdicionPrecios = false; // cambia a true si luego quieres permitir editar

  // Valores desde backend (si vienen)
  $precio_med_v = (float)($precio_med ?? 0);
  $precio_pvp_v = (float)($precio_pvp ?? 0);
  $precio_dis_v = (float)($precio_dis ?? 0);

  // Si por alguna razón vienen en 0, puedes recalcular base mínima aquí (opcional)
  // NOTA: en sobres normalmente ya los calculas en el controller
  if ($precio_med_v > 0 && $precio_med_v < 12) $precio_med_v = 12;
  if ($precio_med_v > 0) {
    $precio_dis_v = $precio_med_v * 0.65;
    $precio_pvp_v = $precio_med_v * (4/3);
  }
@endphp

<div class="card">
  <div class="card-body">
    <h4 class="mb-3">Resumen de Fórmula en Sobres</h4>

    {{-- === Formulario de cabecera (guardar) === --}}
    <form action="{{ route('formulas.guardar_sobres') }}" method="POST" class="mb-3">
      @csrf

      <div class="row g-2 align-items-end">
        <div class="col-12 col-md-4">
          <label class="form-label mb-1">Nombre etiqueta</label>
          <input type="text" name="nombre_etiqueta" class="form-control"
                 value="{{ old('nombre_etiqueta') }}"
                 placeholder="Ej. SUEÑO PROFUNDO" required autocomplete="off">
        </div>

        <div class="col-12 col-md-4">
          <label class="form-label mb-1">Médico</label>
          <input type="text" name="medico" id="medico" class="form-control"
                 value="{{ old('medico') }}"
                 placeholder="Dr(a). NOMBRE APELLIDO" required autocomplete="off">
        </div>

        <div class="col-12 col-md-4">
          <input type="hidden" name="cod_formula" value="{{ $codFormula ?? '' }}">
        </div>
      </div>

      {{-- Hidden enviados al guardar (se sincronizan desde JS) --}}
      <input type="hidden" name="precio_medico"       value="{{ number_format($precio_med_v, 2, '.', '') }}">
      <input type="hidden" name="precio_publico"      value="{{ number_format($precio_pvp_v, 2, '.', '') }}">
      <input type="hidden" name="precio_distribuidor" value="{{ number_format($precio_dis_v, 2, '.', '') }}">
      <input type="hidden" name="tomas_diarias"       value="{{ $tomasDiarias ?? 1 }}">

      <div class="mt-3 d-flex gap-2">
        <button type="submit" class="btn btn-primary">Guardar fórmula (Sobres)</button>
        <a href="{{ route('formulas.nuevas') }}" class="btn btn-outline-secondary">Volver</a>
      </div>
    </form>

    {{-- === Alertas de tratamiento === --}}
    <div class="alert alert-info mb-3">
      Tratamiento: <strong>{{ $diasTratamiento ?? 30 }} días</strong><br>
      Sobres por día: <strong>{{ $tomasDiarias ?? 1 }}</strong><br>
    </div>

    {{-- === Tabla principal === --}}
    <div class="table-responsive">
      <table class="table" id="tabla-resumen">
        <thead>
          <tr>
            <th class="d-none d-md-table-cell">#</th>
            <th class="d-none d-md-table-cell">Cod. Odoo</th>
            <th>Activo</th>
            <th class="text-end">Cant.</th>
            <th>Unidad</th>
          </tr>
        </thead>
        <tbody>
          @foreach($rows as $i => $r)
            @php
              $fijos  = [70277, 70299, 70256, 9585]; // CAJA, SOBRES, CLIGHT, SUCARALOSA
              $cod    = (int)($r['cod_odoo'] ?? 0);
              $isFijo = in_array($cod, $fijos, true);

              $cant   = $r['cantidad'] ?? null;
              $unidad = $r['unidad'] ?? '—';
            @endphp
            <tr class="{{ $isFijo ? 'table-light' : '' }}">
              <td class="d-none d-md-table-cell">{{ $i+1 }}</td>
              <td class="d-none d-md-table-cell">{{ $r['cod_odoo'] ?? '—' }}</td>
              <td>{{ $r['activo'] ?? '—' }}</td>
              <td class="text-end">
                @if(!is_null($cant))
                  {{ rtrim(rtrim(number_format((float)$cant, 3), '0'), '.') }}
                @else
                  —
                @endif
              </td>
              <td>{{ $unidad }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
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
            <!-- <small class="text-muted">Piso $12. Público: +33.333% (4/3). Distribuidor: 65%.</small> -->
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

    {{-- ===== Detalle de pesaje ===== --}}
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
                <th class="text-end">Cant. total</th>
                <th class="text-end">Densidad</th>
                <th class="text-end">Vol ml</th>
                <th class="text-end">Masa f Mes</th>
              </tr>
            </thead>

            <tbody>
              @foreach($rows as $i => $r)
                @php
                  $cod = (int)($r['cod_odoo'] ?? 0);
                  $isFijo = in_array($cod, [70299, 70256, 9585], true);

                  $cant = $r['cantidad'] ?? null;
                @endphp
                <tr class="{{ $isFijo ? 'table-light' : '' }}">
                  <td class="d-none d-md-table-cell">{{ $i+1 }}</td>
                  <td class="d-none d-md-table-cell">{{ $r['cod_odoo'] ?? '—' }}</td>
                  <td>{{ $r['activo'] ?? '—' }}</td>

                  <td class="text-end">
                    @if(!is_null($cant))
                      {{ rtrim(rtrim(number_format((float)$cant, 3), '0'), '.') }}
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
                    @if(!is_null($r['densidad'] ?? null))
                      {{ rtrim(rtrim(number_format((float)$r['densidad'], 6), '0'), '.') }}
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
                <th colspan="7" class="text-end">Totales</th>
                <th class="text-end">{{ number_format($totalVolMl ?? 0, 3) }}</th>
                <th class="text-end">{{ number_format($totalMasaMes ?? 0, 3) }}</th>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>
    </div>

  </div>
</div>

{{-- ========== Scripts (SIN duplicados) ========== --}}
<script>
document.addEventListener("DOMContentLoaded", () => {
  const ENABLE_PRICE_EDIT = @json($habilitarEdicionPrecios);

  // 1) Mayúsculas (robusto)
  const soloMayusculas = (input, permitirTodo = false) => {
    if (!input) return;
    input.addEventListener("input", () => {
      let valor = (input.value || '')
        .normalize("NFD").replace(/[\u0300-\u036f]/g, "")
        .toUpperCase();

      if (!permitirTodo) valor = valor.replace(/[^A-Z\s]/g, "");
      input.value = valor;
    });
  };

  soloMayusculas(document.querySelector("[name='nombre_etiqueta']"), true);
  soloMayusculas(document.querySelector("[name='medico']"));
  const pac = document.querySelector("[name='paciente']");
  if (pac) soloMayusculas(pac);

  // 2) Precios: aunque edición esté deshabilitada, sincroniza outputs + hidden una vez
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

    // Piso $12 (solo si >0)
    if (med > 0 && med < 12) med = 12;

    const dis = med * 0.65;
    const pvp = med * (4/3);

    // Si está habilitado, fuerza el valor formateado en el input
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

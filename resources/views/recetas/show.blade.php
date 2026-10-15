@extends('layouts.app')

@section('title','Receta #'.$receta->id_receta)

@section('content')
<div class="container py-3">
  @if (empty($publicView))
    <a href="{{ route('recetas.index') }}" class="btn btn-link">&larr; Volver</a>
  @else
    <div class="alert alert-info d-flex flex-column flex-md-row gap-2 justify-content-between align-items-md-center">
      <div>Enlace público de la receta</div>
      <a href="{{ url()->current() }}" target="_blank" rel="noopener" class="btn btn-sm btn-primary">
        Abrir enlace
      </a>
    </div>
  @endif

  <div class="card mb-4">
    <div class="card-header"><strong>Receta #{{ $receta->id_receta }}</strong></div>
    <div class="card-body">
      <dl class="row">
        <dt class="col-sm-3">SO</dt>
        <dd class="col-sm-9">{{ $receta->so ?: 'N/A' }}</dd>

        <dt class="col-sm-3">Código fórmula</dt>
        <dd class="col-sm-9">{{ $receta->codigo_formula }}</dd>

        <dt class="col-sm-3">Fecha</dt>
        <dd class="col-sm-9">{{ \Carbon\Carbon::parse($receta->fecha)->format('d/m/Y') }}</dd>

        <dt class="col-sm-3">Médico</dt>
        <dd class="col-sm-9">
          {{ $receta->cedula_medico }}
          @if ($medico)
            <br><small class="text-muted">{{ $medico->full_name }}</small>
            @if ($medico->firma)
              <br><span class="badge bg-success">✓ Con Firma</span>
            @else
              <br><span class="badge bg-warning">✗ Sin Firma</span>
            @endif
          @endif
        </dd>

        <dt class="col-sm-3">Paciente</dt>
        <dd class="col-sm-9">{{ $receta->paciente }}</dd>

        @if ($firmaUrl)
          <dt class="col-sm-3">Firma del Médico</dt>
          <dd class="col-sm-9">
            <img src="{{ $firmaUrl }}" alt="Firma del médico" style="max-width: 200px; max-height: 100px;">
          </dd>
        @endif
      </dl>
    </div>
  </div>

  <!-- Mostrar productos si es una receta personalizada -->
  @if ($productos && $productos->count() > 0)
    <div class="card">
      <div class="card-header">
        <strong>Productos Seleccionados</strong> ({{ $productos->count() }})
      </div>
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-sm table-hover">
            <thead class="table-light">
              <tr>
                <th>Código</th>
                <th>Nombre del Producto</th>
                <th>Cantidad</th>
              </tr>
            </thead>
            <tbody>
              @foreach ($productos as $producto)
                <tr>
                  <td><code>{{ $producto->cod_product }}</code></td>
                  <td>{{ $producto->nombre }}</td>
                  <td>{{ $producto->cantidad ?? 1 }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    </div>
  @elseif (!empty($homeopatico))
    <div class="card">
      <div class="card-header">
        <strong>Receta Homeopática</strong>
      </div>
      <div class="card-body">
        <dl class="row mb-0">
          <dt class="col-sm-3">Producto</dt>
          <dd class="col-sm-9">{{ $homeopatico->producto }}</dd>

          <dt class="col-sm-3">Composición</dt>
          <dd class="col-sm-9" style="white-space: pre-line;">{{ $homeopatico->composicion }}</dd>

          <dt class="col-sm-3">Cantidad solicitada</dt>
          <dd class="col-sm-9">{{ $homeopatico->cantidad_solicitada }}</dd>

          <dt class="col-sm-3">Frascos en esta receta</dt>
          <dd class="col-sm-9">{{ $receta->num_frascos }}</dd>

          <dt class="col-sm-3">Duración del tratamiento</dt>
          <dd class="col-sm-9">
            {{ $receta->num_frascos }} {{ (int) $receta->num_frascos === 1 ? 'mes' : 'meses' }}
          </dd>
        </dl>
      </div>
    </div>
  @elseif ($formula && $items && $items->count() > 0)
    <!-- Mostrar ítems de fórmula si es una receta tradicional -->
    <div class="card">
      <div class="card-header">
        <strong>Ítems de la Fórmula</strong> ({{ $items->count() }})
      </div>
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-sm table-hover">
            <thead class="table-light">
              <tr>
                <th>Código</th>
                <th>Activo</th>
                <th>Unidad</th>
                <th>Masa (mes)</th>
              </tr>
            </thead>
            <tbody>
              @foreach ($items as $item)
                <tr>
                  <td><code>{{ $item->cod_odoo }}</code></td>
                  <td>{{ $item->activo }}</td>
                  <td>{{ $item->unidad ?: 'mg' }}</td>
                  <td>{{ $item->masa_mes ? number_format($item->masa_mes, 2) : '-' }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    </div>
  @else
    <div class="alert alert-info">
      No hay ítems o productos asociados a esta receta.
    </div>
  @endif

</div>
@endsection

@extends('layouts.app')

@section('content')
<div class="container py-3">
  <div class="d-flex align-items-center justify-content-between gap-3 mb-3">
    <h4 class="mb-0">Activos</h4>

    <form class="d-flex gap-2" method="GET" action="{{ route('activos.index') }}">
      <input type="text" name="q" value="{{ $q }}" class="form-control" placeholder="Buscar por código o nombre">
      <button class="btn btn-primary">Buscar</button>
      <a class="btn btn-outline-secondary" href="{{ route('activos.index') }}">Limpiar</a>
    </form>
  </div>

  @if(session('ok'))
    <div class="alert alert-success">{{ session('ok') }}</div>
  @endif

  <div class="card shadow-sm">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>Código</th>
            <th>Nombre</th>
            <th class="text-end">Costo</th>
            <th class="text-end">Factor</th>
            <th>Unidad</th>
            <th class="text-end">F. Venta</th>
            <th class="text-end">Densidad</th>
            <th class="text-end" style="width:120px;">Acciones</th>
          </tr>
        </thead>
        <tbody>
          @forelse($activos as $a)
            <tr>
              <td>{{ $a->cod_odoo }}</td>
              <td class="text-truncate" style="max-width:420px;">{{ $a->nombre }}</td>
              <td class="text-end">{{ $a->valor_costo }}</td>
              <td class="text-end">{{ $a->factor }}</td>
              <td>{{ $a->unidad }}</td>
              <td class="text-end">{{ $a->factor_venta }}</td>
              <td class="text-end">{{ $a->densidad }}</td>
              <td class="text-end">
                <a class="btn btn-sm btn-outline-primary" href="{{ route('activos.edit', $a) }}">
                  Editar
                </a>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="8" class="text-center py-4 text-muted">Sin resultados</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div class="card-body">
      {{ $activos->links() }}
    </div>
  </div>
</div>
@endsection

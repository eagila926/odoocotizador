@extends('layouts.app')

@section('content')
<div class="container py-3" style="max-width: 920px;">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="mb-0">Editar activo</h4>
    <a class="btn btn-outline-secondary" href="{{ route('activos.index') }}">Volver</a>
  </div>

  <div class="card shadow-sm">
    <div class="card-body">
      <form method="POST" action="{{ route('activos.update', $activo) }}">
        @csrf
        @method('PUT')

        <div class="row g-3">
          <div class="col-md-3">
            <label class="form-label">Código (Odoo)</label>
            <input class="form-control" value="{{ $activo->cod_odoo }}" disabled>
            <input type="hidden" name="cod_odoo" value="{{ $activo->cod_odoo }}">
          </div>

          <div class="col-md-9">
            <label class="form-label">Nombre</label>
            <input name="nombre" class="form-control @error('nombre') is-invalid @enderror"
                   value="{{ old('nombre', $activo->nombre) }}">
            @error('nombre') <div class="invalid-feedback">{{ $message }}</div> @enderror
          </div>

          <div class="col-md-3">
            <label class="form-label">Valor costo</label>
            <input name="valor_costo" type="number" step="0.0001"
                   class="form-control @error('valor_costo') is-invalid @enderror"
                   value="{{ old('valor_costo', $activo->valor_costo) }}">
            @error('valor_costo') <div class="invalid-feedback">{{ $message }}</div> @enderror
          </div>

          <div class="col-md-3">
            <label class="form-label">Factor</label>
            <input name="factor" type="number" step="0.0001"
                   class="form-control @error('factor') is-invalid @enderror"
                   value="{{ old('factor', $activo->factor) }}">
            @error('factor') <div class="invalid-feedback">{{ $message }}</div> @enderror
          </div>

          <div class="col-md-3">
            <label class="form-label">Mínimo</label>
            <input name="minimo" type="text" step="0.0001"
                   class="form-control @error('minimo') is-invalid @enderror"
                   value="{{ old('minimo', $activo->minimo) }}">
            @error('minimo') <div class="invalid-feedback">{{ $message }}</div> @enderror
          </div>

          <div class="col-md-3">
            <label class="form-label">Máximo</label>
            <input name="maximo" type="text" step="0.0001"
                   class="form-control @error('maximo') is-invalid @enderror"
                   value="{{ old('maximo', $activo->maximo) }}">
            @error('maximo') <div class="invalid-feedback">{{ $message }}</div> @enderror
          </div>

          <div class="col-md-3">
            <label class="form-label">Unidad</label>
            @php $u = old('unidad', $activo->unidad); @endphp
            <select name="unidad" class="form-select @error('unidad') is-invalid @enderror">
              <option value="mg"  {{ $u==='mg' ? 'selected' : '' }}>mg</option>
              <option value="mcg"   {{ $u==='mcg' ? 'selected' : '' }}>mcg</option>
              <option value="UI"  {{ $u==='UI' ? 'selected' : '' }}>UI</option>
              <option value="und" {{ $u==='und' ? 'selected' : '' }}>und</option>
            </select>
            @error('unidad') <div class="invalid-feedback">{{ $message }}</div> @enderror
          </div>

          <div class="col-md-3">
            <label class="form-label">Factor venta</label>
            <input name="factor_venta" type="number" step="0.0001"
                   class="form-control @error('factor_venta') is-invalid @enderror"
                   value="{{ old('factor_venta', $activo->factor_venta) }}">
            @error('factor_venta') <div class="invalid-feedback">{{ $message }}</div> @enderror
          </div>

          <div class="col-md-3">
            <label class="form-label">Densidad</label>
            <input name="densidad" type="number" step="0.0001"
                   class="form-control @error('densidad') is-invalid @enderror"
                   value="{{ old('densidad', $activo->densidad) }}">
            @error('densidad') <div class="invalid-feedback">{{ $message }}</div> @enderror
          </div>
        </div>

        <div class="mt-4 d-flex gap-2">
          <button class="btn btn-primary">Guardar cambios</button>
          <a class="btn btn-outline-secondary" href="{{ route('activos.index') }}">Cancelar</a>
        </div>

      </form>
    </div>
  </div>
</div>
@endsection

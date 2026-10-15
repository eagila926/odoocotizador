@extends('layouts.app')
@section('title', 'Últimas Fórmulas')

@push('styles')
<style>
  /* ====== SCOPE SOLO PARA ESTA VISTA ====== */
  .recientes-scope .table th, 
  .recientes-scope .table td { vertical-align: middle; }

  .recientes-scope .text-nowrap { white-space: nowrap; }

  /* Botón compacto solo-ícono */
  .recientes-scope .btn-icon{
    width:28px;height:28px;
    display:inline-flex;align-items:center;justify-content:center;
    padding:0;line-height:1;overflow:hidden;
  }

  /* --- NORMALIZADORES ANTI-REGLAS GLOBALES --- */
  /* Si usas Bootstrap Icons por <i class="bi ..."> */
  .recientes-scope .btn-icon i.bi{
    font-size:16px !important;
    line-height:1 !important;
    width:16px !important;
    height:16px !important;
    display:inline-block !important;
    position:static !important;
  }
  .recientes-scope .btn-icon i.bi::before{
    line-height:1 !important;
    vertical-align:middle !important;
  }

  /* Si usas SVG inline */
  .recientes-scope .btn-icon svg{
    width:16px !important;
    height:16px !important;
    display:block !important;
    position:static !important;
    max-width:none !important;
    max-height:none !important;
  }

  /* Resetea cualquier regla salvaje dentro de la tabla (por si hay carruseles/temas) */
  .recientes-scope .table i,
  .recientes-scope .table .bi,
  .recientes-scope .table svg{
    transform:none !important;
    filter:none !important;
  }

  /* Compacta filas */
  .recientes-scope .table td, 
  .recientes-scope .table th{ padding-top:.45rem; padding-bottom:.45rem; }
</style>
@endpush

@section('content')
<div class="card recientes-scope">
  <div class="card-body">
    <h4 class="mb-3">Últimas Fórmulas</h4>

    <form method="GET" action="{{ route('formulas.recientes') }}" class="mb-3">
      <div class="row g-2 align-items-end">
        <div class="col-12 col-md-3">
          <label class="form-label mb-1">Nombre / Código</label>
          <input type="text" name="nombre" class="form-control"
                value="{{ request('nombre') }}"
                placeholder="Ej. SUEÑO PROFUNDO o FOEAG5.123456">
        </div>

        <div class="col-12 col-md-3">
          <label class="form-label mb-1">Médico</label>
          <input type="text" name="medico" class="form-control"
                value="{{ request('medico') }}"
                placeholder="Nombre del médico">
        </div>

        <div class="col-6 col-md-2">
          <label class="form-label mb-1">Desde</label>
          <input type="date" name="desde" class="form-control"
                value="{{ request('desde') }}">
        </div>

        <div class="col-6 col-md-2">
          <label class="form-label mb-1">Hasta</label>
          <input type="date" name="hasta" class="form-control"
                value="{{ request('hasta') }}">
        </div>

        <div class="col-12 col-md-2 d-flex gap-2">
          <button type="submit" class="btn btn-primary w-100">
            Filtrar
          </button>
          <a href="{{ route('formulas.recientes') }}" class="btn btn-outline-secondary w-100">
            Limpiar
          </a>
        </div>
      </div>
    </form>


    <div class="table-responsive">
      <table class="table align-middle table-hover">
        <thead>
          <tr>
            <th style="width:60px">#</th>
            <th>Código Fórmula</th>
            <th>Nombre Fórmula</th>
            <th>Médico</th>   {{-- nueva columna --}}
            <th class="text-end">Precio Médico</th>
            <th>Fecha</th>
            <th style="width:140px">Acciones</th>
          </tr>
        </thead>

        <tbody>
          @forelse ($formulas as $i => $f)
            <tr>
              <td>{{ ($formulas->firstItem() ?? 1) + $i }}</td>
              <td>{{ $f->codigo }}</td>
              <td>{{ $f->nombre_etiqueta }}</td>
              <td>{{ $f->medico }}</td> {{-- aquí mostramos el médico --}}
              <td class="text-end">{{ number_format((float)($f->precio_medico ?? 0), 2) }}</td>
              <td>{{ optional($f->created_at)->format('Y-m-d') }}</td>
              <td class="text-nowrap">
                {{-- EDITAR --}}
                <a href="{{ route('formulas.editar.cargar', $f->id) }}"
                  class="btn btn-outline-primary btn-icon me-1"
                  title="Editar" aria-label="Editar">
                  <i class="bi bi-pencil"></i>
                </a>
                {{-- EXPORTAR --}}
                <a class="btn btn-success btn-icon"
                  href="{{ route('fe.items',$f->id) }}"
                  title="Ver ítems" aria-label="Ver ítems">
                  <i class="bi bi-file-earmark-excel"></i>
                </a>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="7" class="text-center text-muted py-4">
                No tienes fórmulas registradas aún.
              </td>
            </tr>
          @endforelse
        </tbody>

      </table>
    </div>

    <div class="d-flex justify-content-between align-items-center mt-2">
        <div class="text-muted small">Página {{ $formulas->currentPage() }}</div>
        <div>{{ $formulas->links() }}</div>
    </div>


  </div>
</div>
@endsection 

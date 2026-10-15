@extends('layouts.app')
@section('title','Fórmulas Establecidas | Ortomed')

@section('content')
<div class="card mb-3">
  <div class="card-body">
    <h4 class="mb-3">Fórmulas Establecidas</h4>

    <form action="{{ route('fe.add') }}" method="POST" class="row g-2 align-items-end" autocomplete="off">
      @csrf
      <div class="col-12 col-md-6 position-relative">
        <label class="form-label">Fórmula:</label>
        <input type="text" id="buscador" class="form-control" placeholder="Ingrese el código o nombre de la fórmula">
        <input type="hidden" name="formula_id" id="formula_id">
        <div id="sugerencias" class="list-group position-absolute w-100 shadow-sm"
             style="z-index:1000; display:none; max-height:260px; overflow:auto;"></div>
      </div>
      <div class="col-auto">
        <button type="submit" id="btn-add" class="btn btn-primary" disabled>Añadir</button>
      </div>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-body">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h5 class="mb-0">Fórmulas seleccionadas</h5>
      <form action="{{ route('fe.clear') }}" method="POST" onsubmit="return confirm('¿Eliminar todas?');">
        @csrf @method('DELETE')
        <button class="btn btn-danger btn-sm">Eliminar todas las fórmulas</button>
      </form>
    </div>

    <div class="table-responsive">
      <table class="table align-middle">
        <thead>
          <tr>
            <th>Nombre Fórmula</th>
            <th style="width:260px">Tipo Etiqueta</th>
            <th>Acciones</th>
            <th>P Médico</th>
            <th>P Distribuidor</th>
            <th>P Paciente</th>
            <th>Eliminar</th>
          </tr>
        </thead>
        <tbody>
          @forelse($rows as $r)
            <tr data-id="{{ $r->id }}">
              <td>
                <div class="fw-semibold">{{ $r->codigo }}</div>
                <small class="text-muted">{{ $r->nombre_etiqueta }}</small>
              </td>
              <td>
                <select class="form-select form-select-sm sel-tipo">
                  @foreach($tipos as $t)
                    <option value="{{ $t }}" {{ $r->tipo === $t ? 'selected' : '' }}>{{ $t }}</option>
                  @endforeach
                </select>
              </td>
              <td class="text-nowrap">
                <a class="btn btn-secondary btn-sm" href="{{ route('fe.print',$r->id) }}" target="_blank" title="Imprimir">
                  <i class="bi bi-printer"></i>
                </a>

                <a class="btn btn-success btn-sm" href="{{ route('fe.items',$r->id) }}" title="Ver ítems">
                  <i class="bi bi-file-earmark-excel"></i>
                </a>
                {{-- Editar (carga ítems en activo_temps y redirige a /formulas/nuevas) --}}
              <a class="btn btn-primary btn-sm"
                href="{{ route('formulas.editar.cargar', $r->id) }}"
                title="Editar">
                <i class="bi bi-pencil-square"></i>
              </a>
                {{-- Generar receta PDF --}}
                <button type="button" class="btn btn-outline-secondary btn-sm btn-receta-pdf" data-id="{{ $r->id }}" data-codigo="{{ $r->codigo }}" title="Receta PDF">
                  <i class="bi bi-file-earmark-pdf"></i>
                </button>

              </td>
              <td>{{ number_format($r->precio_medico,2) }}</td>
              <td>{{ number_format($r->precio_distribuidor,2) }}</td>
              <td>{{ number_format($r->precio_publico,2) }}</td>
              <td>
                <form action="{{ route('fe.remove',$r->id) }}" method="POST" onsubmit="return confirm('¿Eliminar esta fila?');">
                  @csrf @method('DELETE')
                  <button class="btn btn-outline-danger btn-sm">Eliminar</button>
                </form>
              </td>
            </tr>
          @empty
            <tr><td colspan="7" class="text-center text-muted">Sin registros.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>

{{-- Iconos Bootstrap si no están en tu layout --}}
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

<script>
(function(){
  const $buscador = document.getElementById('buscador');
  const $sugs     = document.getElementById('sugerencias');
  const $idHidden = document.getElementById('formula_id');
  const $btnAdd   = document.getElementById('btn-add');

  let t=null;
  $buscador.addEventListener('input', function(){
    const q = this.value.trim();
    $idHidden.value=''; $btnAdd.disabled = true;    

    if (t) clearTimeout(t);
    if (q.length < 2) { $sugs.style.display='none'; $sugs.innerHTML=''; return; }

    t=setTimeout(()=>{
      fetch(`{{ route('fe.buscar') }}?q=`+encodeURIComponent(q))
        .then(r=>r.json()).then(data=>{
          $sugs.innerHTML='';
          if (!data.length){ $sugs.style.display='none'; return; }
          data.forEach(it=>{
            const a=document.createElement('a');
            a.href='#'; a.className='list-group-item list-group-item-action';
            a.textContent = it.display;
            a.onclick=(e)=>{ e.preventDefault();
              $buscador.value = it.display;
              $idHidden.value = it.id;
              $btnAdd.disabled = false;
              $sugs.style.display='none'; $sugs.innerHTML='';
            };
            $sugs.appendChild(a);
          });
          $sugs.style.display='block';
        });
    },220);
  });

  document.addEventListener('click',(e)=>{
    if (!e.target.closest('#sugerencias') && e.target!==$buscador) $sugs.style.display='none';
  });

  document.querySelectorAll('.sel-tipo').forEach(sel=>{
    sel.addEventListener('change', function(){
      const id = this.closest('tr').dataset.id;
      fetch(`{{ route('fe.update') }}`,{
        method:'POST',
        headers:{'Content-Type':'application/json','X-CSRF-TOKEN':'{{ csrf_token() }}'},
        body: JSON.stringify({ formula_id:id, tipo:this.value })
      });
    });
  });
})();

</script>
<!-- Modal: crear receta para fórmula (usable por botón PDF) -->
<div class="modal fade" id="modalCrearRecetaFE" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form id="formCrearRecetaFE" method="POST">
        @csrf
        <div class="modal-header"><h5 class="modal-title">Generar Receta</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="_token" value="{{ csrf_token() }}">
          <div class="mb-2">
            <label class="form-label">Fórmula</label>
            <input type="text" id="receta_formula_display" class="form-control" readonly>
          </div>
          <div class="mb-2">
            <label class="form-label">SO</label>
            <input type="text" name="so" class="form-control" required pattern="\d+">
          </div>
          <div class="mb-2">
            <label class="form-label">Buscar médico</label>
            <input type="text" id="buscaMedReceta" class="form-control" placeholder="Nombre o cédula" autocomplete="off">
            <input type="hidden" name="cedula_medico" id="cedula_medico">
            <div id="medicoStatusReceta" class="form-text text-danger d-none">Debe seleccionar un médico con firma para descargar el PDF.</div>
            <div id="resBuscaMedReceta" class="list-group mt-1" style="max-height:180px; overflow:auto; display:none;"></div>
          </div>
          <div class="mb-2">
            <label class="form-label">Paciente (opcional)</label>
            <input type="text" name="paciente" class="form-control">
          </div>
          <div class="mb-2">
            <label class="form-label">N° frascos</label>
            <input type="number" name="num_frascos" class="form-control" min="1" value="1">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
          <button type="button" id="btnGenerarRecetaFE" class="btn btn-primary">Generar y descargar PDF</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function(){
  const modalEl = document.getElementById('modalCrearRecetaFE');
  const modal = new bootstrap.Modal(modalEl);
  const btnGenerar = document.getElementById('btnGenerarRecetaFE');
  const form = document.getElementById('formCrearRecetaFE');
  const status = document.getElementById('medicoStatusReceta');
  let medicoFirmaOk = false;
  btnGenerar.disabled = true;
  if (status) {
    status.classList.add('d-none');
    status.textContent = '';
  }
  const errorBox = document.createElement('div');
  errorBox.className = 'alert alert-danger d-none';
  errorBox.id = 'errorRecetaFE';
  form.querySelector('.modal-body').insertBefore(errorBox, form.querySelector('.modal-body').firstChild);

  document.querySelectorAll('.btn-receta-pdf').forEach(btn=>{
    btn.addEventListener('click', function(){
      const id = this.dataset.id;
      const codigo = this.dataset.codigo;
      document.getElementById('receta_formula_display').value = codigo;
      form.action = `{{ url('formulas/establecidas') }}/${id}/receta`;
      form.reset();
      document.getElementById('receta_formula_display').value = codigo;
      document.getElementById('buscaMedReceta').value = '';
      document.getElementById('cedula_medico').value = '';
      document.getElementById('resBuscaMedReceta').innerHTML = '';
      document.getElementById('resBuscaMedReceta').style.display = 'none';
      errorBox.classList.add('d-none');
      errorBox.textContent = '';
      if (status) {
        status.classList.add('d-none');
        status.textContent = '';
      }
      medicoFirmaOk = false;
      btnGenerar.disabled = true;
      modal.show();
    });
  });

  btnGenerar.addEventListener('click', function(){
    errorBox.classList.add('d-none');
    errorBox.textContent = '';
    btnGenerar.disabled = true;
    window.submitPdfFormWithPublicLinks(form, {
      defaultFilename: 'receta.pdf',
      errorMessage: 'Error al generar la receta.'
    }).then(() => {
      btnGenerar.disabled = false;
      modal.hide();
    }).catch(err => {
      btnGenerar.disabled = false;
      errorBox.textContent = err.message || 'No se pudo generar el PDF. Intente otra vez.';
      errorBox.classList.remove('d-none');
    });
  });
});
</script>

<script>
document.addEventListener('DOMContentLoaded', function(){
  const $input = document.getElementById('buscaMedReceta');
  const $res   = document.getElementById('resBuscaMedReceta');
  const $hidden= document.getElementById('cedula_medico');
  const $btnGenerar = document.getElementById('btnGenerarRecetaFE');
  const $status = document.getElementById('medicoStatusReceta');
  if (!$input) return;
  let tt=null;
  let medicoFirmaOk = false;
  if ($btnGenerar) $btnGenerar.disabled = true;
  if ($status) $status.classList.add('d-none');

  $input.addEventListener('input', function(){
    const q = this.value.trim();
    $res.innerHTML = '';
    $res.style.display = 'none';
    $hidden.value = '';
    medicoFirmaOk = false;
    if ($btnGenerar) $btnGenerar.disabled = true;
    if ($status) $status.classList.add('d-none');
    if (tt) clearTimeout(tt);
    if (q.length < 2) return;
    tt = setTimeout(()=>{
      fetch(`{{ route('medicos.buscar') }}?q=`+encodeURIComponent(q))
        .then(r=>r.json()).then(data=>{
          $res.innerHTML='';
          if (!Array.isArray(data) || data.length===0) { $res.innerHTML='<div class="list-group-item">No se encontraron médicos.</div>'; $res.style.display='block'; return; }
          data.forEach(it=>{
            const firmaOk = !!it.firma;
            const btn = document.createElement('button');
            btn.type='button'; btn.className='list-group-item list-group-item-action d-flex justify-content-between align-items-center';
            btn.innerHTML = `<div><strong>${it.label}</strong><div class="small text-muted">${it.cedula}</div></div>` + (firmaOk? '<span class="badge bg-success">Firma</span>':'<span class="badge bg-danger">Sin firma</span>');
            btn.addEventListener('click', function(){
              $hidden.value = it.cedula;
              $input.value = it.label;
              $res.innerHTML=''; $res.style.display='none';
              medicoFirmaOk = firmaOk;
              if (!firmaOk) {
                if ($status) {
                  $status.textContent = 'El médico seleccionado no tiene firma. No se puede generar el PDF.';
                  $status.classList.remove('d-none');
                }
                if ($btnGenerar) $btnGenerar.disabled = true;
              } else {
                if ($status) {
                  $status.classList.add('d-none');
                  $status.textContent = '';
                }
                if ($btnGenerar) $btnGenerar.disabled = false;
              }
            });
            $res.appendChild(btn);
          });
          $res.style.display='block';
        }).catch(()=>{});
    },180);
  });

  // cerrar sugerencias al click fuera
  document.addEventListener('click', function(e){ if (!e.target.closest('#resBuscaMedReceta') && e.target!==$input) { $res.style.display='none'; } });
});
</script>

@endsection

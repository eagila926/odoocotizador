<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<style>
  @page { margin: 28px; }
  body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color:#000; }

  h1 { font-size: 20px; margin: 0 0 12px; font-weight: 700; }

  .row { width:100%; }
  .col-6 { display:inline-block; width:49%; vertical-align:top; }
  .right { text-align:right; }

  .label { font-weight:700; }

  .mt-1{ margin-top:6px; }
  .mt-2{ margin-top:10px; }
  .mt-3{ margin-top:16px; }
  .mt-4{ margin-top:22px; }

  .mb-1{ margin-bottom:6px; }
  .mb-2{ margin-bottom:10px; }
  .mb-3{ margin-bottom:16px; }

  .hr { border:0; height:1px; background:#000; margin: 10px 0 14px; }

  .comp { width:100%; border-collapse:collapse; }
  .comp td { padding:3px 0; }
  .comp td:nth-child(1){ font-weight:700; }
  .comp td:nth-child(2){
    text-align:right;
    font-weight:700;
    white-space:nowrap;
  }

  /* Encabezado centrado */
  .header-center { text-align:center; margin-bottom: 8px; }
  .header-line { line-height:1.25; }
  .header-centro { font-weight:700; font-size:13px; }
  .header-doctor { font-weight:700; font-size:13px; text-transform:uppercase; }
  .header-sub { font-size:12px; }

  /* Firma */
  
  .firma-box { 
    margin-top:40px; 
    text-align:center;   /* centrada */
  }
    
  .firma-img { 
    height:160px;        /* aumenta tamaño */
    width:auto;          /* mantiene proporción */
  }

  /* salto de página entre recetas */
  .page-break { page-break-after: always; }
</style>
</head>
<body>

@php
  // Seguridad: si llega null o falta alguna key
  $lote = $lote ?? collect();
@endphp

@foreach($lote as $i => $pack)
  @php
    $receta      = data_get($pack, 'r');
    $formula     = data_get($pack, 'formula');
    $items       = collect(data_get($pack, 'items', []));
    $medico      = data_get($pack, 'medico');
    $firmaBase64 = data_get($pack, 'firmaBase64');

    // Encabezado según tu tabla medicos
    $centro = trim((string) data_get($medico, 'centro_medico', ''));
    $nombre = trim((string) data_get($medico, 'full_name', ''));
    $dir    = trim((string) data_get($medico, 'direccion', ''));
    $tel    = trim((string) data_get($medico, 'telefono', ''));

    // FallBack por si full_name viene vacío
    if ($nombre === '') {
      $nombre = trim((string) data_get($pack, 'doctorDisplay', ''));
    }

    // Posología
    $tomas = (int) (data_get($formula, 'tomas_diarias', 2));
    $esFormulaSobres = str_starts_with(strtoupper((string) data_get($formula, 'codigo', '')), 'SFO');

    // Excluir auxiliares
    $excluir = [70274,70272,70275,70273,1101,1078,1077,1219,70276,70271,71497];
    if ($esFormulaSobres) {
      $excluir = array_merge($excluir, [70256, 70277, 70299]);
    }
    $itemsPdf = $items->filter(fn($it) => !in_array((int) data_get($it, 'cod_odoo', 0), $excluir))->values();
  @endphp

  {{-- ================== ENCABEZADO ================== --}}
  <div class="header-center">
    @if($centro !== '')
      <div class="header-line header-centro">{{ $centro }}</div>
    @endif

    @if($nombre !== '')
      <div class="header-line header-doctor">
        {{ 'DR.(a) ' . mb_strtoupper($nombre, 'UTF-8') }}
      </div>
    @else
      <div class="header-line header-doctor">DR.(a)</div>
    @endif

    @if($dir !== '')
      <div class="header-line header-sub">{{ $dir }}</div>
    @endif

    @if($tel !== '')
      <div class="header-line header-sub">{{ $tel }}</div>
    @endif
  </div>

  <hr class="hr">

  {{-- ================== FECHA / SO / PACIENTE ================== --}}
  <div class="row">
    <div class="col-6">
      <div class="mb-1">
        <span class="label">Fecha:</span>
        {{ $receta? \Carbon\Carbon::parse($receta->fecha)->format('d-m-Y') : '' }}
      </div>
      <div class="mb-1">
        <span class="label">SO:</span>
        {{ $receta->so ?? '' }}
      </div>
    </div>

    <div class="col-6 right">
      <div class="mb-1">
        <span class="label">Paciente:</span>
        {{ $receta->paciente ?? '' }}
      </div>
    </div>
  </div>

  {{-- ================== PRODUCTO ================== --}}
  <div class="mt-3">
    <div class="label">Producto:</div>
    <div>
      {{ data_get($formula, 'nombre_etiqueta') ?? ($receta->codigo_formula ?? '') }}
    </div>

    <p>
      <strong>N.º de {{ $esFormulaSobres ? 'cajas' : 'frascos' }}:</strong> {{ $receta->num_frascos ?? 1 }}
    </p>
    @if($esFormulaSobres)
      <p>
        <strong>Duración del tratamiento:</strong>
        {{ $receta->num_frascos ?? 1 }} {{ (int) ($receta->num_frascos ?? 1) === 1 ? 'mes' : 'meses' }}
      </p>
    @endif
  </div>

  {{-- ================== POSOLOGÍA ================== --}}
  <div class="mt-3">
    <div class="label">Posología:</div>
    <div class="label">
      {{ $esFormulaSobres ? 'TOMAR 1 SOBRE DIARIO' : "TOMAR {$tomas} CÁPSULAS DIARIAS" }}
    </div>
  </div>

  {{-- ================== COMPOSICIÓN ================== --}}
  <div class="mt-3">
    <div class="label">Composición:</div>

    <table class="comp">
      @foreach($itemsPdf as $it)
        <tr>
          <td>{{ data_get($it, 'activo') }}</td>
          <td>
            {{ number_format((float) data_get($it, 'cantidad', 0), 2) }}
            {{ data_get($it, 'unidad', 'mg') }}
          </td>
        </tr>
      @endforeach
    </table>
  </div>

  {{-- ================== FIRMA (solo firma) ================== --}}
  <div class="firma-box">
    @if(!empty($firmaBase64))
      <img class="firma-img" src="data:image/png;base64,{{ $firmaBase64 }}" alt="Firma">
    @endif
    <div class="mt-2">---------------------</div>
    <div>Firma</div>
  </div>

  {{-- salto página excepto última --}}
  @if($i < count($lote) - 1)
    <div class="page-break"></div>
  @endif
@endforeach

</body>
</html>

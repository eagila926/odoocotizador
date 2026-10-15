<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Receta #{{ $receta->id_receta }}</title>
<style>
  @page { margin: 28px; }
  * { box-sizing: border-box; }
  body {
    margin: 0;
    background: #eef1f4;
    font-family: DejaVu Sans, Arial, sans-serif;
    font-size: 12px;
    color: #000;
  }
  .toolbar {
    position: sticky;
    top: 0;
    z-index: 10;
    display: flex;
    gap: 8px;
    justify-content: center;
    padding: 12px;
    background: #fff;
    border-bottom: 1px solid #d8dee4;
  }
  .toolbar button,
  .toolbar a {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 36px;
    padding: 7px 14px;
    border: 1px solid #0d6efd;
    border-radius: 4px;
    background: #0d6efd;
    color: #fff;
    font: 600 14px Arial, sans-serif;
    text-decoration: none;
    cursor: pointer;
  }
  .toolbar a {
    background: #fff;
    color: #0d6efd;
  }
  .sheet {
    width: 210mm;
    min-height: 297mm;
    margin: 18px auto;
    padding: 28px;
    background: #fff;
    box-shadow: 0 10px 30px rgba(15, 23, 42, .18);
  }
  h1 { font-size: 20px; margin: 0 0 12px; font-weight: 700; }
  .row { width:100%; }
  .col-6 { display:inline-block; width:49%; vertical-align:top; }
  .right { text-align:right; }
  .label { font-weight:700; }
  .mt-1{ margin-top:6px; }
  .mt-2{ margin-top:10px; }
  .mt-3{ margin-top:16px; }
  .mb-1{ margin-bottom:6px; }
  .hr { border:0; height:1px; background:#000; margin: 10px 0 14px; }
  .comp { width:100%; border-collapse:collapse; }
  .comp td { padding:3px 0; }
  .comp td:nth-child(1){ font-weight:700; }
  .comp td:nth-child(2){
    text-align:right;
    font-weight:700;
    white-space:nowrap;
  }
  .box { border:1px solid #000; padding:10px; min-height:70px; white-space:pre-line; }
  .firma-box { margin-top:30px; text-align:center; }
  .firma-img { height:70px; width:auto; }
  .header-center { text-align:center; margin-bottom: 8px; }
  .header-line { line-height:1.25; }
  .header-centro { font-weight:700; font-size:13px; }
  .header-doctor { font-weight:700; font-size:13px; text-transform:uppercase; }
  .header-sub { font-size:12px; }

  @media (max-width: 760px) {
    .sheet {
      width: calc(100% - 20px);
      min-height: auto;
      margin: 10px;
    }
  }
  @media print {
    body { background: #fff; }
    .toolbar { display: none; }
    .sheet {
      width: auto;
      min-height: auto;
      margin: 0;
      padding: 0;
      box-shadow: none;
    }
  }
</style>
</head>
<body>
<div class="toolbar">
  <button type="button" onclick="window.print()">Imprimir receta</button>
  <a href="{{ url()->current() }}" target="_blank" rel="noopener">Abrir enlace</a>
</div>

<main class="sheet">
@php
  $centro = trim((string)($medico->centro_medico ?? ''));
  $nombre = trim((string)($medico->full_name ?? ''));
  $dir    = trim((string)($medico->direccion ?? ''));
  $tel    = trim((string)($medico->telefono ?? ''));
@endphp

<div class="header-center">
  @if($centro !== '')
    <div class="header-line header-centro">{{ $centro }}</div>
  @endif

  <div class="header-line header-doctor">
    {{ 'DR.(a) ' . mb_strtoupper($nombre, 'UTF-8') }}
  </div>

  @if($dir !== '')
    <div class="header-line header-sub">{{ $dir }}</div>
  @endif

  @if($tel !== '')
    <div class="header-line header-sub">{{ $tel }}</div>
  @endif
</div>

<hr class="hr">

<div class="row">
  <div class="col-6">
    <div class="mb-1">
      <span class="label">Fecha:</span>
      {{ \Carbon\Carbon::parse($receta->fecha)->format('d-m-Y') }}
    </div>
    <div class="mb-1">
      <span class="label">SO:</span>
      {{ $receta->so }}
    </div>
  </div>

  <div class="col-6 right">
    <div class="mb-1">
      <span class="label">Paciente:</span>
      {{ $receta->paciente }}
    </div>
  </div>
</div>

<div class="mt-3">
  @if(!empty($homeopatico))
    <div class="label">Producto homeopático:</div>
    <div>{{ $homeopatico->producto }}</div>

    <div class="mt-2">
      <strong>N.º de frascos:</strong> {{ $receta->num_frascos }}
    </div>

    <div class="mt-2">
      <strong>Duración del tratamiento:</strong>
      {{ $receta->num_frascos }} {{ (int) $receta->num_frascos === 1 ? 'mes' : 'meses' }}
    </div>

    <div class="mt-3">
      <div class="label">Composición:</div>
      <div class="box">{{ $homeopatico->composicion }}</div>
    </div>
  @elseif(!empty($formula))
    @php
      $esFormulaSobres = str_starts_with(strtoupper((string) ($formula->codigo ?? '')), 'SFO');
    @endphp
    <div class="label">Producto:</div>
    <div>{{ $formula->nombre_etiqueta ?? $receta->codigo_formula }}</div>

    <p>
      <strong>N.º de {{ $esFormulaSobres ? 'cajas' : 'frascos' }}:</strong> {{ $receta->num_frascos }}
    </p>

    @if($esFormulaSobres)
      <p>
        <strong>Duración del tratamiento:</strong>
        {{ $receta->num_frascos }} {{ (int) $receta->num_frascos === 1 ? 'mes' : 'meses' }}
      </p>
    @endif

    @php
      $tomas = (int) ($formula->tomas_diarias ?? 2);
    @endphp

    <div class="mt-3">
      <div class="label">Posología:</div>
      <div class="label">{{ $esFormulaSobres ? 'TOMAR 1 SOBRE DIARIO' : "TOMAR {$tomas} CÁPSULAS DIARIAS" }}</div>
    </div>

    <div class="mt-3">
      <div class="label">Composición:</div>
      <table class="comp">
        @php
          $excluir = [70274,70272,70275,70273,1101,1078,1077,1219,70276,70271,71497];
          if ($esFormulaSobres) {
            $excluir = array_merge($excluir, [70256, 70277, 70299]);
          }
          $itemsPdf = collect($items ?? [])
            ->filter(fn($it) => !in_array((int)($it->cod_odoo ?? 0), $excluir));
        @endphp

        @foreach($itemsPdf as $it)
          <tr>
            <td>{{ $it->activo }}</td>
            <td>
              {{ number_format((float)$it->cantidad, 2) }}
              {{ $it->unidad ?? 'mg' }}
            </td>
          </tr>
        @endforeach
      </table>
    </div>
  @else
    <div class="label">Productos Seleccionados:</div>
    <table class="comp">
      @foreach(collect($productos ?? []) as $producto)
        <tr>
          <td>{{ data_get($producto, 'nombre') }}</td>
          <td>Cant: {{ data_get($producto, 'cantidad', 1) }}</td>
        </tr>
      @endforeach
    </table>
  @endif
</div>

<div class="firma-box">
  @if(!empty($firmaBase64 ?? null))
    <img class="firma-img" src="data:image/png;base64,{{ $firmaBase64 }}" alt="Firma">
  @endif

  <div class="mt-2">---------------------</div>
  <div>Firma del doctor</div>
</div>
</main>
</body>
</html>

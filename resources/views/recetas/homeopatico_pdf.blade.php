<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<style>
  @page { margin: 28px; }
  body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color:#000; }
  .page { page-break-after: always; }
  .page:last-child { page-break-after: auto; }
  .row { width:100%; }
  .col-6 { display:inline-block; width:49%; vertical-align:top; }
  .right { text-align:right; }
  .label { font-weight:700; }
  .mt-1{ margin-top:6px; }
  .mt-2{ margin-top:10px; }
  .mt-3{ margin-top:16px; }
  .mb-1{ margin-bottom:6px; }
  .hr { border:0; height:1px; background:#000; margin: 10px 0 14px; }
  .header-center { text-align:center; margin-bottom: 8px; }
  .header-line { line-height:1.25; }
  .header-centro { font-weight:700; font-size:13px; }
  .header-doctor { font-weight:700; font-size:13px; text-transform:uppercase; }
  .header-sub { font-size:12px; }
  .box { border:1px solid #000; padding:10px; min-height:70px; white-space:pre-line; }
  .firma-box { margin-top:32px; text-align:center; }
  .firma-img { height:70px; }
</style>
</head>
<body>
@foreach($packs as $pack)
  @php
    $receta = $pack['receta'];
    $homeopatico = $pack['homeopatico'];
    $medicoPack = $pack['medico'] ?? $medico;
    $firmaPack = $pack['firmaBase64'] ?? ($firmaBase64 ?? null);
    $centro = trim((string)($medicoPack->centro_medico ?? ''));
    $nombre = trim((string)($medicoPack->full_name ?? ''));
    $dir    = trim((string)($medicoPack->direccion ?? ''));
    $tel    = trim((string)($medicoPack->telefono ?? ''));
  @endphp

  <div class="page">
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
      <div class="label">Producto homeopático:</div>
      <div>{{ $homeopatico->producto }}</div>
    </div>

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

    <div class="firma-box">
      @if(!empty($firmaPack))
        <img class="firma-img" src="data:image/png;base64,{{ $firmaPack }}" alt="Firma">
      @endif

      <div class="mt-2">---------------------</div>
      <div>Firma del doctor</div>
    </div>
  </div>
@endforeach
</body>
</html>

@php
  $fmt = fn($n) => number_format($n, 2);
@endphp
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Pedido {{ $pedido->codigo }}</title>
  <style>
    *{ font-family: DejaVu Sans, sans-serif; }
    body{ font-size:12px; color:#222; }
    h1{ font-size:20px; margin:0 0 6px; }
    .muted{ color:#666; }
    .meta{ margin-bottom:12px; }
    table{ width:100%; border-collapse:collapse; }
    th, td{ border:1px solid #ddd; padding:6px; }
    th{ background:#f5f5f5; text-align:left; }
    tfoot td{ font-weight:bold; }
    .text-end{ text-align:right; }
    .text-center{ text-align:center; }
    .badge{ display:inline-block; padding:2px 6px; border:1px solid #999; border-radius:4px; font-size:10px; }
  </style>
</head>
<body>
  <h1>Pedido de Fórmulas</h1>

  <div class="meta">
    <div><strong>Código:</strong> {{ $pedido->codigo }}</div>
    <div><strong>Fecha:</strong> {{ \Carbon\Carbon::parse($pedido->fecha)->format('Y-m-d H:i') }}</div>

    @if($pedido->relationLoaded('user') && $pedido->user)
      <div class="muted">
        <strong>Usuario:</strong> {{ $pedido->user->nombre ?? '' }} {{ $pedido->user->apellido ?? '' }}
      </div>
    @endif
  </div>

  <table>
    <thead>
      <tr>
        <th>Fórmula</th>
        <th>Detalle</th>
        <th style="width:10%" class="text-end">Cant.</th>
        <th style="width:14%" class="text-end">Precio</th>
        <th style="width:14%" class="text-end">Subtotal</th>
        <th style="width:8%"  class="text-center">Promo</th>
      </tr>
    </thead>

    <tbody>
      @foreach($pedido->items as $it)
        <tr>
          <td>
            <div>{{ $it->nombre_formula }}</div>
            <small class="muted">{{ $it->cod_formula }}</small>
          </td>

          <td>{{ $it->detalle }}</td>

          <td class="text-end">
            {{ rtrim(rtrim(number_format($it->cantidad, 2, '.', ''), '0'), '.') }}
          </td>

          <td class="text-end">$ {{ $fmt($it->precio_unidad) }}</td>

          <td class="text-end">$ {{ $fmt($it->subtotal) }}</td>

          <td class="text-center">
            <span class="badge">{{ $it->promocion }}</span>
          </td>
        </tr>
      @endforeach
    </tbody>

    <tfoot>
      <tr>
        <td colspan="4" class="text-end">TOTAL</td>
        <td class="text-end">$ {{ $fmt($pedido->total) }}</td>
        <td></td>
      </tr>
    </tfoot>

  </table>
</body>
</html>

@extends('layouts.app')

@section('title', 'Mis pedidos')

@section('content')
<div class="container">
    <h3 class="mb-3">Mis pedidos</h3>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if ($pedidos->isEmpty())
        <div class="alert alert-info">
            Aún no tienes pedidos registrados.
        </div>
    @else
        <div class="table-responsive">
            <table class="table table-sm table-bordered align-middle">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Código</th>
                        <th>Fecha</th>
                        <th>Total</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($pedidos as $idx => $pedido)
                        @php
                            $rowId = 'items-pedido-'.$pedido->id;
                        @endphp

                        {{-- Fila principal del pedido --}}
                        <tr>
                            <td>{{ $loop->iteration + ($pedidos->currentPage() - 1) * $pedidos->perPage() }}</td>
                            <td>{{ $pedido->codigo }}</td>
                            <td>
                                @if($pedido->fecha instanceof \Illuminate\Support\Carbon)
                                    {{ $pedido->fecha->format('d/m/Y H:i') }}
                                @else
                                    {{ \Carbon\Carbon::parse($pedido->fecha)->format('d/m/Y H:i') }}
                                @endif
                            </td>
                            <td>${{ number_format($pedido->total, 2) }}</td>
                            <td>
                                <button type="button"
                                        class="btn btn-sm btn-outline-primary"
                                        data-bs-toggle="collapse"
                                        data-bs-target="#{{ $rowId }}"
                                        aria-expanded="false"
                                        aria-controls="{{ $rowId }}">
                                    Ver items
                                </button>

                                {{-- Opcional: botón para PDF --}}
                                <a href="{{ route('pedidos.pdf', $pedido) }}"
                                   class="btn btn-sm btn-outline-secondary">
                                    PDF
                                </a>
                            </td>
                        </tr>

                        {{-- Fila colapsable con los items del pedido --}}
                        <tr class="collapse" id="{{ $rowId }}">
                            <td colspan="5">
                                <strong>Items del pedido {{ $pedido->codigo }}:</strong>
                                <div class="table-responsive mt-2">
                                    <table class="table table-sm table-striped mb-0">
                                        <thead>
                                            <tr>
                                                <th>Código producto</th>
                                                <th>Nombre</th>
                                                <th>Categoría</th>
                                                <th>Cantidad</th>
                                                <th>Promoción</th>
                                                <th>Precio unidad</th>
                                                <th>Subtotal</th>
                                                <th>Observación</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse ($pedido->items as $item)
                                                <tr>
                                                    <td>{{ $item->cod_product }}</td>
                                                    <td>{{ $item->nombre }}</td>
                                                    <td>{{ $item->categoria }}</td>
                                                    <td>{{ $item->cantidad }}</td>
                                                    <td>{{ $item->promocion }}</td>
                                                    <td>${{ number_format($item->precio_unidad, 2) }}</td>
                                                    <td>${{ number_format($item->subtotal, 2) }}</td>
                                                    <td>{{ $item->detalle }}</td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="8" class="text-muted">
                                                        Este pedido no tiene items registrados.
                                                    </td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Paginación (si usas paginate) --}}
        <div class="mt-3">
            {{ $pedidos->links() }}
        </div>
    @endif

    <div class="mt-3">
        <a href="{{ route('pedidos.productos') }}" class="btn btn-secondary">
            Volver a hacer pedido
        </a>
    </div>
</div>
@endsection

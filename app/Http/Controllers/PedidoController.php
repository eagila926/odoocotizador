<?php

namespace App\Http\Controllers;

use App\Models\Pedido;
use App\Models\PedidoItem;
use App\Models\Producto;
use App\Models\CarritoItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Barryvdh\DomPDF\Facade\Pdf;

class PedidoController extends Controller
{
    // Página principal
    public function productos(Request $request)
    {
        $user = Auth::user();
        $userId = $user->id_user ?? $user->id;

        // Items del carrito guardados en BD (carrito temporal)
        $pedido = CarritoItem::where('user_id', $userId)
                    ->orderBy('id')
                    ->get();

        // Total
        $total = $pedido->sum('subtotal');

        return view('pedidos.productos', compact('pedido', 'total'));
    }

    // Autocompletado (todas las categorías)
    public function buscarProductos(Request $request)
    {
        $q = trim($request->get('q', ''));
        if ($q === '') {
            return response()->json([]);
        }

        $items = Producto::query()
            ->where(function ($s) use ($q) {
                $s->where('nombre', 'like', "%{$q}%")
                  ->orWhere('cod_product', 'like', "%{$q}%");
            })
            ->orderBy('nombre')
            ->limit(10)
            ->get(['cod_product', 'nombre', 'categoria', 'precio']);

        return response()->json($items);
    }

    // Agregar item al pedido (carrito temporal en BD)
    public function agregarProducto(Request $request)
    {
        $data = $request->validate([
            'producto_codigo' => 'required|string|exists:productos,cod_product',
            'cantidad'    => 'required|numeric|min:1',
            'promocion'   => 'required|in:SI,NO',
            'observacion' => 'nullable|string|max:500',
        ]);

        $p = Producto::where('cod_product', $data['producto_codigo'])->firstOrFail();

        $precioUnit = ($data['promocion'] === 'SI') ? 0 : (float)$p->precio;
        $subtotal   = $precioUnit * (float)$data['cantidad'];

        $user = Auth::user();
        $userId = $user->id_user ?? $user->id;

        // Creas el registro en la tabla temporal
        CarritoItem::create([
            'user_id'     => $userId,
            'cod_product' => $p->cod_product,
            'nombre'      => $p->nombre,
            'categoria'   => $p->categoria,
            'cantidad'    => (float)$data['cantidad'],
            'promocion'   => $data['promocion'],
            'observacion' => $data['observacion'] ?? '',
            'precio'      => $precioUnit,
            'subtotal'    => $subtotal,
        ]);

        return back();
    }

    // Eliminar item por ID (ya no por índice de sesión)
    public function eliminarProducto($id)
    {
        $user = Auth::user();
        $userId = $user->id_user ?? $user->id;

        CarritoItem::where('id', $id)
            ->where('user_id', $userId)
            ->delete();

        return back();
    }

    public function finalizar()
    {
        $user = Auth::user();
        $userId = $user->id_user ?? $user->id;

        // Lista desde la tabla temporal
        $lista = CarritoItem::where('user_id', $userId)->get();

        if ($lista->isEmpty()) {
            return back()->with('error', 'No hay productos en el pedido.');
        }

        $total = $lista->sum('subtotal');

        $iniciales = strtoupper(substr($user->nombre, 0, 1) . substr($user->apellido, 0, 1));

        $ultimo = Pedido::where('user_id', $userId)
                ->orderByDesc('id')->value('codigo');

        $consecutivo = 1;
        if ($ultimo && preg_match('/(\d+)$/', $ultimo, $m)) {
            $consecutivo = intval($m[1]) + 1;
        }
        $codigo = sprintf('PE-%s-%03d', $iniciales, $consecutivo);

        $pedidoId = null;

        DB::transaction(function () use ($lista, $total, $codigo, $userId, &$pedidoId) {
            $pedido = Pedido::create([
                'codigo'  => $codigo,
                'fecha'   => now(),
                'total'   => $total,
                'user_id' => $userId,
            ]);

            foreach ($lista as $it) {
                PedidoItem::create([
                    'pedido_id'     => $pedido->id,
                    'codigo'        => $codigo,
                    'cod_product'   => $it->cod_product,
                    'nombre'        => $it->nombre,
                    'categoria'     => $it->categoria,
                    'cantidad'      => $it->cantidad,
                    'detalle'       => $it->observacion ?? '',
                    'precio_unidad' => $it->precio,
                    'subtotal'      => $it->subtotal,
                    'promocion'     => $it->promocion,
                ]);
            }

            // limpiar carrito temporal de ese usuario
            CarritoItem::where('user_id', $userId)->delete();

            $pedidoId = $pedido->id;
        });

        return redirect()
            ->route('pedidos.productos')
            ->with('success', "Pedido {$codigo} guardado correctamente.")
            ->with('download_url', route('pedidos.pdf', $pedidoId));
    }

    public function pdf(Pedido $pedido)
    {
        $pedido->load(['items', 'user']);

        $pdf = Pdf::loadView('pedidos.pdf', compact('pedido'))
                ->setPaper('A4', 'portrait');

        return $pdf->download('Pedido-'.$pedido->codigo.'.pdf');
    }

    public function misPedidos()
    {
        $user = Auth::user();
        $userId = $user->id_user ?? $user->id;

        $pedidos = Pedido::query()
            ->where('user_id', $userId)
            ->with('items')
            ->orderByDesc('fecha')
            ->paginate(10);

        return view('pedidos.mis-pedidos', compact('pedidos'));
    }
}
